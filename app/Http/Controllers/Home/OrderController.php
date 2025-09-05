<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Http\Resources\Home\OrderResource\OrderCollection;
use App\Http\Resources\Home\OrderResource\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    // 获取订单列表
    public function index()
    {
        $order_service = new OrderService();
        $list = $order_service->getOrders('member')['data'];
        return $this->success(new OrderCollection($list));
    }

    /**
     * 获取订单详情
     * @param OrderService $service
     * @param $id
     * @return array
     */
    public function show(OrderService $service, $id)
    {
        try {
            $order = $service->getOrderInfoById($id);
            return $this->success(new OrderResource($order));
        } catch (\Exception $e) {
            Log::error($e->getCode() . '-' . $e->getMessage());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    /**
     * 创建订单
     * @param OrderService $service
     * @return array
     */
    public function store(OrderService $service)
    {
        try {
            // 参数校验
            $goodsList = json_decode(request()->goods_list ?? request()->goods);
            $isCart = request()->is_cart ?? 0;
            $addressId = request()->address_id;
            $remark = request()->remark;

            if (empty($goodsList)) {
                $this->throwException('没有选择商品', 4310);
            }

            if (empty($addressId)) {
                $this->throwException('没有选择收货地址', 4311);
            }

            $data = $service->addOrder($goodsList, $isCart, $addressId, $remark, Order::SOURCE_WECHAT_MINI);
            return $this->success($data);
        } catch (\Exception $e) {
            Log::error($e->getCode() . '-' . $e->getMessage());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    /**
     * 创建订单前
     * @param OrderService $service
     * @return array
     */
    public function createOrderBefore(OrderService $service)
    {
        try {
            // 参数校验
            $goodsList = json_decode(request()->goods_list ?? request()->goods);
            $isCart = request()->is_cart ?? 0;

            if (empty($goodsList)) {
                $this->throwException('没有选择商品', 4310);
            }

            $data = $service->orderFormat($goodsList, $isCart);
            return $this->success($data);
        } catch (\Exception $e) {
            Log::error($e->getCode() . '-' . $e->getMessage());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    // 创建订单后
    public function createOrderAfter()
    {
        $order_service = new OrderService;
        $rs = $order_service->createOrderAfter();
        return $rs['status'] ? $this->success($rs['data']) : $this->error($rs['msg']);
    }

    /**
     * 支付订单
     * @param Request $request
     * @param OrderService $service
     * @return array
     */
    public function pay(Request $request, OrderService $service)
    {
        try {
            $orderId = $request->order_id;
            $paymentName = $request->payment_name;

            // 判断订单号是否为空
            if (empty($orderId)) {
                throw new \Exception('order_id不能为空', 4530);
            }

            // 检查支付方式是否传过来
            if (empty($paymentName)) {
                throw new \Exception('请选择支付方式', 4531);
            }

            $data = $service->payOrder($orderId, $paymentName);
            return $this->success($data);
        } catch (\Exception $e) {
            Log::error($e->getCode() . '-' . $e->getMessage());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    /**
     * 取消订单
     * @param OrderService $service
     * @param $id 订单ID
     * @return array
     */
    public function cancel(OrderService $service, $id)
    {
        try {
            $service->closeOrder($id);
            return $this->success();
        } catch (\Exception $e) {
            Log::error($e->getCode() . '-' . $e->getMessage());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    /**
     * 确定收货
     * @param OrderService $service
     * @param $id 订单ID
     * @return array
     */
    public function confirm(OrderService $service, $id)
    {
        try {
            $service->confirmOrder($id);
            return $this->success();
        } catch (\Exception $e) {
            Log::error($e->getCode() . '-' . $e->getMessage());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }
}
