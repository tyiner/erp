<?php

namespace App\Services;

use App\Http\Resources\Home\OrderResource\CreateOrderAfterCollection;
use App\Models\Cart;
use App\Models\CouponLog;
use App\Models\Freight;
use App\Models\Goods;
use App\Models\Member;
use App\Models\Order;
use App\Models\OrderGoods;
use App\Models\OrderPay;
use App\Models\Refund;
use App\Models\StoreGoods;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService extends BaseService
{
    /**
     * 待删除购物车ID
     * @var array
     */
    protected $cartIds = [];

    /**
     * 获取订单商品列表
     * @param $goodsList
     * @param $isCart
     * @return array
     * @throws \Exception
     */
    public function orderFormat($goodsList, $isCart)
    {
        $list = [];
        $this->cartIds = [];
        $storeId = $this->getStoreId();
        foreach ($goodsList as $v) {
            $goods = Goods::where('id', $v->goods_id)
                ->select([
                    'id',
                    'goods_name',
                    'goods_master_image',
                ])
                ->with(['store_goods' => function ($q) {
                    // TODO 以下指定字段查询将导致结果为null
//                    $q->select(
//                        'goods_price',
//                        'goods_market_price',
//                        'cost_price',
//                        'goods_stock'
//                    );
                }])
                ->whereHas('store_goods', function ($q) use ($storeId) {
                    $q->where('store_id', $storeId);
                })
                ->first();
            if (!$goods) {
                Log::error("商铺#{$storeId}商品#{$v->goods_id}不存在");
                throw new \Exception('商品不存在', 3401);
            }

            $data = [];
            $data['buy_num'] = abs(intval($v->buy_num));
            $data['goods_id'] = $goods->id;
            $data['goods_name'] = $goods->goods_name;
            $data['goods_master_image'] = $this->thumb($goods->goods_master_image, 150);
            $data['goods_price'] = $goods->store_goods->goods_price;
            $data['total_price'] = round($v->buy_num * $goods->store_goods->goods_price, 2);
            $data['total_weight'] = round($v->buy_num * $goods->store_goods->goods_weight, 2);
            $data['freight_id'] = $goods->store_goods->freight_id;

            $list[$storeId]['goods_list'][] = $data;

            if (empty($list[$storeId]['store_total_price'])) {
                $list[$storeId]['store_total_price'] = 0;
            }
            $list[$storeId]['store_total_price'] += $data['total_price'];

            // 判断库存是否足够
            if ($v->buy_num > $goods->store_goods->goods_stock) {
                Log::error("商铺#{$storeId}商品#{$v->goods_id}库存不足");
                throw new \Exception('商品库存不足', 3402);
            }

            // 判断是否是购物车
            if ($isCart && isset($v->cart_id)) {
                $this->cartIds[] = $v->cart_id;
            }
        }

        $list = array_merge($list, []); // 重建数组索引

        return $list;
    }

    /**
     * 生成订单
     * @param $goodsList
     * @param $isCart
     * @param $addressId
     * @param $remark
     * @param int $source
     * @return array
     * @throws \Exception
     */
    public function addOrder($goodsList, $isCart, $addressId, $remark, $source = Order::SOURCE_ADMIN)
    {
        $addressService = new AddressService();
        $address = $addressService->get($addressId);

        // 循环生成订单 多个商家则生成多个订单
        try {
            DB::beginTransaction();
            $newOrder = [];
            $goodsIds = [];
            $orderGoodsList = $this->orderFormat($goodsList, $isCart);
            foreach ($orderGoodsList as $k => $v) {
                $orderNo = $this->orderNo('XS'); // 生成订单号
                $orderData = [
                    'order_no' => $orderNo, // 订单号
                    'user_id' => $this->getUserId(), // 用户ID
                    'store_id' => $this->getStoreId(), // 店铺ID
                    'order_name' => $v['goods_list'][0]['goods_name'], // 保存第一个商品名称
                    'order_image' => $v['goods_list'][0]['goods_master_image'], // 同上 商品图片
                    'receive_name' => $address->receive_name, // 收件人姓名
                    'receive_tel' => $address->receive_tel, // 收件人电话
                    'receive_area' => $address->area_info, // 收件人地区
                    'receive_address' => $address->address, // 详细地址
                    'remark' => $remark ?? '', // 备注
                    'order_source' => $source,
                ];
                $order = Order::create($orderData); // 订单数据插入数据库

                // 初始化其他费用
                $totalPrice = 0; // 总金额
                $orderPrice = 0; // 订单总金额
                $totalWeight = 0; // 总重量
                $freightMoney = 0; // 运费
                $couponMoney = 0; // 优惠券 金额

                // 循环插入订单商品
                foreach ($v['goods_list'] as $vo) {
                    $orderGoodsData = [
                        'order_id' => $order->id, // 订单ID
                        'user_id' => $orderData['user_id'], // 用户ID
                        'store_id' => $orderData['store_id'], // 店铺ID
//                        'sku_id' => $vo['sku_id'], // skuid
//                        'sku_name' => $vo['sku_name'], // sku名称
                        'goods_id' => $vo['goods_id'], // 商品id
                        'goods_name' => $vo['goods_name'], // 商品名称
                        'goods_image' => $vo['goods_master_image'], // 商品图片
                        'buy_num' => $vo['buy_num'], // 购买数量
                        'goods_price' => $vo['goods_price'], // 商品价格
                        'total_price' => $vo['total_price'], // 总价格
                        'total_weight' => $vo['total_weight'], // 总重量
                    ];
                    $orderGoods = OrderGoods::create($orderGoodsData); // 插入订单商品表

                    // 秒杀
//                    $seckill_info = $seckill_service->getSeckillInfoByGoodsId($orderGoodsData['goods_id']);
//                    if ($seckill_info['status']) {
//                        $couponMoney += $orderGoodsData['total_price'] * ($seckill_info['data']['discount'] / 100);
//                    }

                    // 开始减去库存
                    $this->orderStock($orderGoodsData['goods_id'], $orderGoodsData['buy_num']);

                    // 将商品总总量加起来
                    $totalWeight += $orderGoodsData['total_weight'];

                    // 将金额全部计算加载到订单里面
                    $orderPrice += $orderGoodsData['total_price'];

                    $goodsIds[] = $vo['goods_id'];
                }

                // 获取优惠券的金额 并插入数据库
//                if ($orderData['coupon_id'] > 0) {
//                    $cpli = $coupon_log_model->where('id', $orderData['coupon_id'])
//                        ->where('user_id', $orderData['user_id'])
//                        ->where('store_id', $orderData['store_id'])
//                        ->where('use_money', '<=', ($orderPrice + $freightMoney))
//                        ->where('start_time', '<', now())->where('end_time', '>', now())
//                        ->where('status', 0)->first();
//                    if (empty($cpli)) {
//                        $orderData['coupon_id'] = 0;
//                    } else {
//                        $couponMoney = $cpli['money'];
//                        $cpli->status = 1;
//                        $cpli->order_id = $order->id;
//                        $cpli->save();
//                    }
//                }

                // 满减
//                $full_reduction_resp = $full_reduction_service->getFullReductionInfoByStoreId($orderData['store_id'], ($orderPrice + $freightMoney));
//                if ($full_reduction_resp['status']) {
//                    $couponMoney += $full_reduction_resp['data']['money'];
//                }

                // 判断是否是拼团 如果是拼团减去他的金额
//                $collective_id = $v['goods_list'][0]['collective_id'] ?? 0;
//                if ($collective_id != 0) {
//                    $collective_resp = $collective_service->getCollectiveInfoByGoodsId($v['goods_list'][0]['id']);
//                    if ($collective_resp['status']) {
//                        $couponMoney += $orderPrice * ($collective_resp['data']['discount'] / 100); // 得出拼团减去的钱
//                    }
//                    $collective_id = $collective_service->createCollectiveLog($collective_id, $collective_resp, $orderGoodsData);
//                }

                $freightMoney = $this->sumFreight($v['goods_list'][0]['freight_id'], $totalWeight, $orderData['store_id'], $address['province_id']); // 直接计算运费，如果多个不同的商品取第一个商品的运费

                // 订单总金额做修改，然后保存
                $totalPrice = $orderPrice + $freightMoney - $couponMoney; // 暂时总金额等于[订单金额+运费-优惠金额]
                $order->total_price = round($totalPrice, 2);
                $order->order_price = $orderPrice;
                $order->freight_money = $freightMoney; // 运费
                $order->coupon_money = $couponMoney; // 优惠金额修改
//                $order->coupon_id = $orderData['coupon_id']; // 优惠券ID修改 ，以免非法ID传入
//                $order->collective_id = $collective_id; // 团购ID修改
                $order->save(); // 保存入数据库

                $newOrder['order_id'][] = $order->id;
                $newOrder['order_no'][] = $orderNo;
            }

            // 删除购物车订单
            if ($isCart) {
                if (count($this->cartIds)) {
                    Cart::whereIn('id', $this->cartIds)->delete();
                } else {
                    Cart::where('user_id', $this->getUserId())
                        ->whereIn('goods_id', $goodsIds)
                        ->delete();
                }
            }

            DB::commit();
            return $newOrder;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }

    // 创建订单后处理
    public function createOrderAfter()
    {
        $order_id = explode(',', request()->order_id);
        $list = Order::whereIn('id', $order_id)->with('order_goods')->get();

        return $this->format(new CreateOrderAfterCollection($list));
    }

    /**
     * 库存消减增加
     * @param $goodsId
     * @param $num
     * @param int $isType 0-减少 1-增加
     * @return bool
     * @throws \Exception
     */
    public function orderStock($goodsId, $num, $isType = 0)
    {
        try {
            $storeGoods = StoreGoods::where([
                'goods_id' => $goodsId,
                'store_id' => $this->getStoreId(),
            ]);
            if (empty($isType)) {
                $storeGoods->decrement('goods_stock', $num);
            } else {
                $storeGoods->increment('goods_stock', $num);
            }
            return true;
        } catch (\Exception $e) {
            throw new \Exception('库存消减增加失败', 4510);
        }
    }

    /**
     * 销量消减增加
     * @param $goodsId
     * @param $num
     * @param int $isType
     * @throws \Exception
     */
    public function orderSale($goodsId, $num, $isType = 0)
    {
        try {
            $storeGoods = StoreGoods::where([
                'goods_id' => $goodsId,
                'store_id' => $this->getStoreId(),
            ]);
            if (empty($isType)) {
                $storeGoods->decrement('goods_sale', $num);
            } else {
                $storeGoods->increment('goods_sale', $num);
            }
        } catch (\Exception $e) {
            throw new \Exception('销量消减增加失败', 4520);
        }
    }

    /**
     * 生成支付订单 & 调用第三方支付
     * @param int $orderId
     * @param string $paymentName
     * @return array
     * @throws \Exception
     */
    public function payOrder(int $orderId, string $paymentName)
    {

        $orderPay = $this->addPayOrder($orderId, $paymentName);
        $paymentService = new PaymentService();
        $rs = $paymentService->pay($orderPay);
        return $rs;
    }

    /**
     * 生成支付订单
     * @param int $orderId
     * @param string $paymentName
     * @return mixed
     * @throws \Exception
     */
    protected function addPayOrder(int $orderId, string $paymentName)
    {
        $order = $this->getOrderInfoById($orderId);
        if (Order::STATUS_CLOSE == $order->order_status) {
            throw new \Exception('此订单已关闭', 4600);
        }
        if (Order::STATUS_WAIT_PAY < $order->order_status) {
            throw new \Exception('此订单已支付', 4601);
        }

        $data = [
            'user_id' => $this->getUserId(),
            'store_id' => $this->getStoreId(),
            'order_id' => $orderId,
            'pay_no' => $this->orderNo('ZF'), // 生成支付号
            'payment_name' => $paymentName,
            'total_price' => $order->total_price, // 订单总金额
            'order_balance' => $order->order_balance, // 余额支付金额
        ];

        $orderPay = OrderPay::create($data);
        if (empty($orderPay)) {
            throw new \Exception('创建支付订单失败', 4602);
        }

        return $orderPay;
    }

    /**
     * 修改订单状态
     *
     * @param int $order_id
     * @param int $order_status
     * @param string $auth 用户身份（member、user、admin）
     * @return array
     * @throws \Exception
     */
    public function editOrderStatus($order_id, $order_status, $auth = "user")
    {
        $order_model = new Order;
        $order_model = $order_model->where('id', $order_id);
        if (Member::AUTH_NAME == $auth) {
            $user_service = new UserService;
            $user_info = $user_service->getUserInfo();
            if (empty($user_info)) {
                return $this->format_error(__('users.no_token'));
            }
            // 客户只允许取消订单和确定收货
            if (!in_array($order_status, Member::authOrderStatus())) {
                return $this->format_error('非法操作', ['errno' => 2001]);
            }
            $order_model = $order_model->where('user_id', $user_info['id']);
        }
        $order_model = $order_model->first();

        if (empty($order_model)) {
            return $this->format_error(__('users.error_token'));
        }

        switch ($order_status) {
            case Order::STATUS_CLOSE: // 取消订单
                if ($order_model->order_status != 1) { // 只有待支付的订单能取消
                    return $this->format_error(__('base.error') . ' - 0');
                }
                $og_model = new OrderGoods();
                $og_list = $og_model->select('goods_id', 'sku_id', 'buy_num')->where('order_id', $order_id)->get();
                foreach ($og_list as $v) {
                    $this->orderStock($v['goods_id'], $v['sku_id'], $v['buy_num'], 1);
                }
                // 如果有优惠券则修改优惠券
                $coupon_log_model = new CouponLog();
                $coupon_log_model->where('order_id', $order_id)->update(['status' => 0, 'order_id' => 0]);

                // 库存修改
                break;
            case 1: // 等待支付
                break;
            case 2: // 等待发货
                break;
            case 3: // 确认收货
                if (empty($order_model->delivery_no) || empty($order_model->delivery_code)) { // 只有待支付的订单能取消
                    return $this->format_error(__('base.error') . ' - 3');
                }
                break;
            case 4: // 等待评论
                break;
            case 5: // 5售后
                break;
            case 6: // 6订单完成
                break;
        }
        $order_model->order_status = $order_status;
        $order_model->save();
        return $this->format([$order_status], __('base.success'));
    }

    // 计算运费
    // @param mixed $freight_id 运费模版
    // @param mixed $total_weight 总重量
    // @param mixed $store_id 店铺ID
    // @param mixed $area_id 省份ID
    protected function sumFreight($freight_id, $total_weight, $store_id, $area_id)
    {
        $freight_model = new Freight();
        $default_info = $freight_model->where('is_type', 0)->where('store_id', $store_id)->first(); // 默认快递模版
        if ($freight_id == 0) { // 默认模版
            $info = $default_info;
        } else {
            $info = $freight_model->find($freight_id);
        }

        if (empty($info) || ($info->f_weight == 0 && $info->f_price == 0)) {
            return 0;
        }

        $area = [];
        if (!empty($info->area_id)) {
            $area = explode(',', $info->area_id);
        }
        // 如果配送地址不存在 商家配置的地址则走默认的
        if (!in_array($area_id, $area)) {
            $info = $default_info;
        }

        // 如果没有设置则为0
        if ($info->f_weight == 0 && $info->f_price == 0) {
            return 0;
        }

        // 如果设置了运费，没设置重量则代表无限重量 同一运费
        if ($info->f_weight == 0 && $info->f_price > 0) {
            return $info->f_price;
        }

        // 如果首重和首价格设置了
        if (($info->f_weight > 0 && $info->f_price > 0)) {
            // 判断是否重量有超过
            if ($info->f_weight >= $total_weight) {
                return $info->f_price;
            } else {
                // 超过了则开始分析是否有超出另外计价
                if ($info->o_weight == 0 && $info->o_price == 0) {
                    return $info->f_price;
                }
                // 超过了则开始分析是否有配置超出另外计价
                if ($info->o_weight == 0 && $info->o_price == 0) {
                    return $info->f_price;
                }
                // 超过了则开始分析是否有超出另外计价
                if ($info->o_weight > 0) {
                    $o_num = ceil(($total_weight - $info->f_weight) / $info->o_weight); // 超出的倍数
                    return round($info->f_price + ($o_num * $info->o_price), 2);
                }
            }
        }

        return 0;
    }

    // 获取订单
    public function getOrders($type = "user")
    {
        $order_model = new Order();

        if ($type == 'member') {
            $user_service = new UserService;
            $user_info = $user_service->getUserInfo('member');
            $order_model = $order_model->where('user_id', $user_info['id']);
        } else if ($type == 'seller') {
            $store_id = $this->get_store(true);
            $order_model = $order_model->where('store_id', $store_id);
        }

        $order_model = $order_model->with([
            'store' => function ($q) {
                return $q->select('id', 'store_name');
            },
            'member' => function ($q) {
                return $q->orderByDesc('created_at');
            },
            'order_goods',
            'order_pay' => function ($q) {
                return $q->orderByDesc('created_at');
            },
            'refund' => function ($q) {
                return $q->orderByDesc('created_at');
            },
        ]);

        // 订单号
        $order_no = request()->order_no;
        if (!empty($order_no)) {
            $order_model = $order_model->where('order_no', 'like', '%' . $order_no . '%');
        }

        // 拼团订单ID查询
        $collective_id = request()->collective_id;
        if (!empty($collective_id)) {
            $order_model = $order_model->where('collective_id', $collective_id);
        }

        // 用户ID
        $user_id = request()->user_id;
        if (!empty($user_id)) {
            $order_model = $order_model->where('user_id', $user_id);
        }

        // 店铺ID
        $store_id = request()->store_id;
        if (!empty($store_id)) {
            $order_model = $order_model->where('store_id', $store_id);
        }

        // 下单时间
        $created_at = request()->created_at;
        if (!empty($created_at)) {
            $order_model = $order_model->whereBetween('created_at', [$created_at[0], $created_at[1]]);
        }

        // 订单状态
        $order_status = request()->order_status;
        if (isset($order_status) && $order_status >= 0) {
            $order_model = $order_model->where('order_status', request()->order_status);
        }

        // 获取售后订单
        if (isset(request()->is_refund)) {
            $order_model = $order_model->has('refund');
        }

        // 获取退货订单
        if (isset(request()->is_return)) {
            $order_model = $order_model->whereHas('refund', function ($q) {
                $q->whereIn('refund_type', [Refund::TYPE_RETURN_REFUND, Refund::TYPE_EXCHANGE]);
            });
        }

        if ($this->isSeller()) { // 分销商订单中心
            // 获取新订单
            if (isset(request()->is_new)) {
                $order_model = $order_model
                    ->whereIn('order_status', [
                        Order::STATUS_WAIT_PAY,
                        Order::STATUS_WAIT_SEND,
                    ])
                    ->whereNull('audit_time')
                    ->orderByDesc('created_at');
            }

            // 订单查询——已查询已审核的订单
            if (isset(request()->is_query)) {
                $order_model = $order_model->whereNotNull('audit_time');
            }

            // 获取已取消订单
            if (isset(request()->is_cancel)) {
                $order_model = $order_model
                    ->where('order_status', Order::STATUS_CLOSE)
                    ->orderByDesc('close_time');
            }

            // 获取自提订单
            if (isset(request()->is_selfdelivery)) {
                $order_model = $order_model
                    ->where('delivery_type', 0);
            }
        }

        $order_model = $order_model->orderBy('id', 'desc')
            ->paginate(request()->per_page ?? 30);
        return $this->format($order_model);
    }

    /**
     * 获取订单信息（通过订单ID）
     * @param $id
     * @param string $auth
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     * @throws \Exception
     */
    public function getOrderInfoById($id)
    {
        $order = new Order();
        if ($this->isMember()) {
            $order = $order->where('user_id', $this->getUserId());
        } else if ($this->isSeller()) {
            $order = $order->where('store_id', $this->getStoreId());
        }

        $order = $order
            ->with([
                'order_goods',
                'order_pay' => function ($q) {
                    return $q->orderByDesc('created_at');
                },
                'member',
                'refund' => function ($q) {
                    return $q->orderByDesc('created_at');
                },
            ])
            ->where('id', $id)
            ->first();

        if (empty($order)) {
            $this->throwException('订单不存在', 4002);
        }

        return $order;
    }

    /**
     * 关闭订单
     * @param $id
     * @param string $auth
     * @return bool
     * @throws \Exception
     */
    public function closeOrder($id)
    {
        try {
            DB::beginTransaction();
            $order = $this->getOrderInfoById($id);

            // 客户只允许取消订单和确定收货
            if ($this->isMember() && !in_array(Order::STATUS_CLOSE, Member::authOrderStatus())) {
                $this->throwException('该角色无权取消订单', 4032);
            }

            if (!$order->canClose()) {
                $this->throwException('无法取消订单——' . $order->order_status, 4033);
            }

            $order->order_status = Order::STATUS_CLOSE;
            $order->close_time = date('Y-m-d H:i:s');
            $order->save();

            // 库存修改
            $orderGoods = OrderGoods::select(['goods_id', 'sku_id', 'buy_num',])
                ->where('order_id', $id)
                ->get();

            foreach ($orderGoods as $item) {
                $this->orderStock($item->goods_id, $item->sku_id, $item->buy_num, 1);
            }

            // TODO 优惠券修改

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->throwException($e->getMessage(), $e->getCode());
        }
    }

    /**
     * 确认收货
     * @param $id
     * @param string $auth
     * @return bool
     * @throws \Exception
     */
    public function confirmOrder($id, $auth = Member::AUTH_NAME)
    {
        try {
            DB::beginTransaction();
            $order = Order::where('id', $id)->first();
            if (empty($order)) {
                $this->throwException('订单获取失败', 4002);
            }

            // 客户只能操作自己订单，且只允许取消订单和确定收货
            if (Member::AUTH_NAME == $auth) {
                if (!in_array(Order::STATUS_COMPLETE, Member::authOrderStatus())) {
                    $this->throwException('该角色无权确定收货', 4042);
                }
                if ($order->user_id != $this->getUserId()) {
                    $this->throwException('不能操作他人订单', 4031);
                }
            }

            if (!$order->canConfirm()) {
                $this->throwException('无法确定收货——' . $order->order_status, 4314);
            }

            $order->order_status = Order::STATUS_COMPLETE;
            $order->receipt_time = date('Y-m-d H:i:s');
            $order->save();

            // 如果是换货单，用户确定收货，需要修改退货步骤
            if (Order::TYPE_EXCHANGE == $order->order_type) {
                $refund = Refund::where('exchange_order_id', $order->id)->first();
                $refund->refund_step = Refund::STEP_USER_CONFIRM;
                $refund->save();
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->throwException($e->getMessage(), $e->getCode());
        }
    }

    /**
     * 发货&填写订单快递信息
     * @param $id
     * @param $deliveryNo
     * @param $deliveryCode
     * @return mixed
     * @throws \Exception
     */
    public function fillDelivery($id, $deliveryNo, $deliveryCode)
    {
        $order = $this->getOrderInfoById($id);
        $order->order_status = Order::STATUS_WAIT_CONFIRM;
        $order->delivery_type = Order::DELIVERY_TYPE_SEND;
        $order->delivery_no = $deliveryNo;
        $order->delivery_code = $deliveryCode;
        $order->delivery_time = date('Y-m-d H:i:s');
        $rs = $order->save();
        return $rs;
    }

    /**
     * 生成换货单
     * @param $orderId
     * @throws \Exception
     */
    public function addExchangeOrder($orderId)
    {
        try {
            DB::beginTransaction();
            // 生成换货单
            $order = $this->getOrderInfoById($orderId);
            $exchangeOrder = $order->replicate();
            $exchangeOrder->order_type = Order::TYPE_EXCHANGE;
            $exchangeOrder->order_no = $this->orderNo('HH');
            $exchangeOrder->order_status = Order::STATUS_WAIT_SEND;
            $exchangeOrder->delivery_type = Order::DELIVERY_TYPE_SELF;
            $exchangeOrder->delivery_no = '';
            $exchangeOrder->delivery_code = '';
            $exchangeOrder->delivery_time = null;
            $exchangeOrder->ref_order_id = $orderId;
            $exchangeOrder->save();

            // 商家确定收货并退款或换货
            $refundService = new RefundService();
            $refund = $refundService->getRefundInfoByOrderId($orderId);
            $refund->refund_step = Refund::STEP_MERCHANT_CONFIRM;
            $refund->exchange_order_id = $exchangeOrder->id;
            $refund->save();

            // 换货商品
            $exchangeList = isset($refund->goods_list) ? json_decode($refund->goods_list) : [];
            array_walk($exchangeList, function ($item) use ($orderId, $exchangeOrder) {
                if (!$item->from_goods_id || !$item->to_goods_id) {
                    return;
                }

                $goods = Goods::where('id', $item->to_goods_id)->whereHas('store_goods', function ($q) {
                    $q->where('store_id', $this->getStoreId());
                })->first();
                if (!$goods) {
                    return;
                }

                $orderGoods = OrderGoods::where(['order_id' => $orderId, 'goods_id' => $item->from_goods_id])->first();
                if (!$orderGoods) {
                    return;
                }

                $orderGoods = $orderGoods->replicate();
                $orderGoods->order_id = $exchangeOrder->id; // 换货单ID
                $orderGoods->goods_id = $item->to_goods_id;
                $orderGoods->goods_name = $goods->goods_name;
                $orderGoods->goods_image = $this->thumb($goods->goods_master_image, 150);
                $orderGoods->save();
            });
            // 关闭订单
            $this->closeOrder($orderId);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->throwException($e->getMessage(), $e->getCode());
        }
    }

}
