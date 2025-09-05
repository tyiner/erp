<?php
namespace App\Services;

use App\Models\OrderPay;

class OrderPayService extends BaseService
{
    /**
     * 获取订单支付信息
     * @param $orderId
     * @return mixed
     * @throws \Exception
     */
    public function getOrderPayInfoByOrderId($orderId) {
        $orderPay = new OrderPay();

        if ($this->isMember()) {
            $orderPay = $orderPay->where('user_id', $this->getUserId());
        } else if ($this->isSeller()) {
            $orderPay = $orderPay->where('store_id', $this->getStoreId());
        }

        $orderPay = $orderPay->where('order_id', $orderId)->orderByDesc('id')->first();

        if (!$orderPay) {
            $this->throwException('订单支付不存在', 4502);
        }

        return $orderPay;
    }
}
