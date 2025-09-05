<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Order;
use App\Models\OrderPay;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RefundService extends BaseService
{

    /**
     * 获取售后信息（通过订单ID）
     * @param $orderId
     * @param string $auth
     * @throws \Exception
     */
    public function getRefundInfoByOrderId($orderId)
    {
        $refund = new Refund();

        if ($this->isMember()) {
            $refund = $refund->where('user_id', $this->getUserId());
        } else if ($this->isSeller()) {
            $refund = $refund->where('store_id', $this->getStoreId());
        }

        $refund = $refund->where('order_id', $orderId)->orderByDesc('id')->first();

        if (!$refund) {
            $this->throwException('订单售后不存在', 4502);
        }

        return $refund;
    }

    /**
     * 获取售后信息
     * @param $id
     * @param string $auth
     * @return mixed
     * @throws \Exception
     */
    public function getRefundInfoById($id)
    {
        $refund = new Refund();

        if ($this->isMember()) {
            $refund = $refund->where('user_id', $this->getUserId());
        } else if ($this->isSeller()) {
            $refund = $refund->where('store_id', $this->getStoreId());
        }

        $refund = $refund->where('id', $id)->first();

        if (!$refund) {
            $this->throwException('订单售后不存在', 4503);
        }

        return $refund;
    }

    /**
     * 添加售后
     * @param $orderId
     * @param $refundType
     * @param $refundReason
     * @param $images
     * @param $goodsList
     * @return bool
     * @throws \Exception
     */
    public function add($orderId, $refundType, $refundReason, $images, $goodsList)
    {
        try {
            DB::beginTransaction();

            if (!Arr::exists(Refund::getRefundTypeOptions(), $refundType)) {
                $this->throwException('售后类型不正确——' . $refundType, 4302);
            }

            $orderService = new OrderService();
            $order = $orderService->getOrderInfoById($orderId);

            // 申请退款
            if (Refund::TYPE_REFUND == $refundType && !$order->canRefund()) {
                $this->throwException('无法申请退款——' . $order->order_status, 4304);
            }

            // 申请退货退款
            if (Refund::TYPE_RETURN_REFUND == $refundType && !$order->canReturnRefund()) {
                $this->throwException('无法申请退货退款——' . $order->order_status, 4304);
            }

            // 申请换货
            if (Refund::TYPE_EXCHANGE == $refundType && !$order->canExchange()) {
                $this->throwException('无法申请换货——' . $order->order_status, 4305);
            }

            $refund = Refund::where([
                ['user_id', '=', $this->getUserId()],
                ['order_id', '=', $orderId],
                ['refund_verify', '=', Refund::VERIFY_WAIT_PROCESS],
            ]);
            if ($refund->exists()) {
                $this->throwException('该订单正在申请售后中', 4306);
            }

            $refund = new Refund();
            $refund->user_id = $this->getUserId();
            $refund->order_id = $orderId;
            $refund->store_id = $order->store_id;
            $refund->refund_no = $this->orderNo('TH');
            $refund->refund_type = $refundType;
            $refund->refund_reason = $refundReason;
            $refund->images = $images ?? '';
            $refund->goods_list = json_encode($goodsList);
            $refund->save();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->throwException($e->getMessage(), $e->getCode());
        }
    }

    // 修改
    public function edit($id)
    {
        $refund = $this->getRefundInfoById($id);

        if (isset(request()->delivery_no) && !empty(request()->delivery_no) && $auth == 'user' && $refund->refund_verify == 1) {
            $refund->delivery_no = request()->delivery_no ?? '';
            $refund->delivery_code = request()->delivery_code ?? '';
            $refund->refund_step = 1;
        }

        if (isset(request()->re_delivery_no) && !empty(request()->re_delivery_no) && $auth == 'seller' && $refund->refund_step == 1) {
            $refund->re_delivery_no = request()->re_delivery_no ?? '';
            $refund->re_delivery_code = request()->re_delivery_code ?? '';
            $refund->refund_step = 2;
        }

        if (isset(request()->refund_step) && !empty(request()->refund_step) && $auth == 'user' && $refund->refund_verify == 1 && $refund->refund_step == 2) {
            $refund->refund_step = 3;

            // 修改订单状态
            $order_model = new Order();
            $order_info = $order_model->where('id', $id)->where('user_id', $refund->user_id)->first();
            $order_info->order_status = 6;
            $order_info->refund_status = 2;
            $order_info->save();
        }

        $rs = $refund->save();
        return $this->format($rs, __('base.success'));

    }

    /**
     * 申请售后——同意
     * @param $id
     * @return mixed
     * @throws \Exception
     */
    public function agree($id)
    {
        try {
            DB::beginTransaction();
            $refund = $this->getRefundInfoById($id);
            $refund->refund_verify = Refund::VERIFY_AGREE;
            $refund->save();

            // 用户申请退款
            if (Refund::TYPE_REFUND == $refund->refund_type) {
                $this->refund($id);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->throwException($e->getMessage(), $e->getCode());
        }
    }

    /**
     * 申请售后——拒绝
     * @param $id
     * @param $refuseReason
     * @return mixed
     * @throws \Exception
     */
    public function refuse($id, $refuseReason)
    {
        $refund = $this->getRefundInfoById($id);
        $refund->refund_verify = Refund::VERIFY_REFUSE;
        $refund->refuse_reason = $refuseReason;
        $rs = $refund->save();
        return $rs;
    }

    /**
     * 已收到退货&退款
     * @param $id
     * @return mixed
     */
    public function refund($id)
    {
        try {
            DB::beginTransaction();
            $refund = $this->getRefundInfoById($id);
            $refund->refund_step = Refund::STEP_MERCHANT_CONFIRM;
            $refund->save();

            // 发起退款
            $paymentService = new PaymentService();
            $paymentService->refund($id);

            // 退款成功——关闭订单
            $orderService = new OrderService();
            $orderService->closeOrder($refund->order_id);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->throwException($e->getMessage(), $e->getCode());
        }
    }

    /**
     * 用户填写退货快递信息
     * @param $id
     * @param $deliveryNo
     * @param $deliveryCode
     * @return mixed
     * @throws \Exception
     */
    public function userDelivery($id, $deliveryNo, $deliveryCode)
    {
        $refund = $this->getRefundInfoById($id);

        if (Refund::VERIFY_AGREE != $refund->refund_verify) {
            $this->throwException('订单售后未通过', 5111);
        }

        $refund->delivery_no = $deliveryNo;
        $refund->delivery_code = $deliveryCode;
        $refund->refund_step = Refund::STEP_WAIT_MERCHANT;
        $rs = $refund->save();
        return $rs;
    }

    /**
     * 商家确定收货并发货
     * @param $id
     * @param $deliveryNo
     * @param $deliveryCode
     * @return mixed
     * @throws \Exception
     */
    public function merchantDelivery($id, $deliveryNo, $deliveryCode)
    {
        $refund = $this->getRefundInfoById($id);
        $refund->re_delivery_no = $deliveryNo;
        $refund->re_delivery_code = $deliveryCode;
        $refund->refund_step = Refund::STEP_MERCHANT_CONFIRM;
        $rs = $refund->save();
        return $rs;
    }

    /**
     * 用户确定收货订单成功
     * @param $id
     * @return mixed
     * @throws \Exception
     */
    public function userConfirm($id)
    {
        $refund = $this->getRefundInfoById($id);
        $refund->refund_step = Refund::STEP_USER_CONFIRM;
        $rs = $refund->save();
        return $rs;
    }

    /**
     * 取消申请售后
     * @param $id
     * @return mixed
     * @throws \Exception
     */
    public function cancel($id)
    {
        $refund = $this->getRefundInfoById($id);
        if (Refund::STATUS_SUCCESS == $refund->refund_status) {
            $this->throwException('无法取消，此申请售后已退款成功');
        }
        $refund->refund_verify = Refund::VERIFY_CANCEL;
        $rs = $refund->save();
        return $rs;
    }
}
