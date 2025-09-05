<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderPay;
use App\Models\Refund;
use App\Traits\HelperTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yansongda\Pay\Pay;

/**
 * Class PaymentService
 * @package App\Services
 */
class PaymentService extends BaseService
{
    /**
     * 发起支付
     * @param OrderPay $orderPay
     * @return mixed
     * @throws \Exception
     */
    public function pay(OrderPay $orderPay)
    {
        if (OrderPay::PAYMENT_WECHAT_MINI == $orderPay->payment_name) {
            $params = [
                'pay_type' => OrderPay::PAY_TYPE_WECHAT_MINI,
                'total_fee' => $orderPay->total_price,
                'pay_no' => $orderPay->pay_no,
                'openid' => $this->getOpenId(),
                'body' => $orderPay->order->order_name,
            ];
            return $this->wechatPay($params);
        } else {
            throw new \Exception('暂只支持微信小程序支付', 5112);
        }
    }

    /**
     * 发起退款
     * @param $id
     * @return mixed
     * @throws \Exception
     */
    public function refund($id)
    {
        $refundService = new RefundService();
        $refund = $refundService->getRefundInfoById($id);

        $orderPayService = new OrderPayService();
        $orderPay = $orderPayService->getOrderPayInfoByOrderId($refund->order_id);

        if (OrderPay::PAYMENT_WECHAT_MINI == $orderPay->payment_name) {
            $params = [
                'pay_center_no' => $orderPay->pay_center_no,
                'out_trade_no' => $orderPay->out_trade_no,
                'refund_amount' => $orderPay->total_price,
                'refund_no' => $refund->refund_no,
            ];
            return $this->wechatRefund($params);
        } else {
            throw new \Exception('暂只支持微信小程序支付', 5112);
        }
    }

    /**
     * 支付回调
     * @param $paymentName
     * @param $tradeNo
     * @param $outPayNo
     * @param $amount
     * @throws \Exception
     */
    public function payNotify($paymentName, $tradeNo, $payNo, $amount)
    {
        try {
            DB::beginTransaction();
            $orderPay = OrderPay::where('pay_no', $payNo)->first();
            if (empty($orderPay)) {
                throw new \Exception('支付订单获取失败', 4402);
            }
            $orderPay->out_trade_no = $tradeNo;
            $orderPay->pay_status = 1;
            $orderPay->pay_time = date('Y-m-d H:i:s');
            $orderPay->pay_amount = $amount;
            $orderPay->save();

            $order = Order::find($orderPay->order_id);
            $order->order_status = Order::STATUS_WAIT_SEND;
            $order->save();

            // 订单送积分
//            $config_service = new ConfigService();
//            $config_service->giveIntegral('order');

            // 建立分销信息
//            $distribution_service = new DistributionService();
//            $distribution_service->addDisLog($orderIds);

            // 金额日志 用户账户变更
//            $ml_service = new MoneyLogService();
//            $rs = $ml_service->editMoney('订单支付', $orderPay->user_id, -$orderPay->total_price);
//            if (!$rs['status']) {
//                throw new \Exception($rs['msg'], 4404);
//            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->throwException($e->getMessage(), $e->getCode());
        }
    }

    /**
     * 微信支付
     * @param array $params
     * @return mixed
     */
    protected function wechatPay(array $params)
    {
        $url = 'https://paycenter.lititong.com/api/pay/wx_pay';
        Log::debug('wechatPay url=' . $url);
        $data = json_encode([
            'code' => '01hotel', // 商户编号（平台添加，值唯一）
            'timeout_express' => 5, // 单位分钟
            'total_fee' => $params['total_fee'], // 单位元
            'merchant_order_no' => $params['pay_no'],
            'merchant_notify_url' => env('APP_URL') . '/api/wxpay/notify',
            'paytype' => $params['pay_type'],
            'openid' => $params['openid'],
            'body' => $params['body'],
        ]);
        Log::debug('wechatPay data=' . $data);
        $rs = json_decode($this->postRequest($url, $data));
        Log::debug('wechatPay rs=' . json_encode($rs));

        if ($rs->code) {
            $orderPay = OrderPay::where('pay_no', $params['pay_no'])->first();
            $orderPay->pay_center_no = $rs->content->pay_order_id;
            $orderPay->save();
        }
        return $rs->content;
    }

    /**
     * 微信退款
     * @param $params
     * @return mixed
     */
    protected function wechatRefund($params)
    {
        $url = 'https://paycenter.lititong.com/api/pay/refund';
        Log::debug('wechatRefund url=' . $url);
        $data = json_encode([
            'code' => '01hotel',
            'timestamp' => time(),
            'pay_order_id' => $params['pay_center_no'],
            'pay_platform_id' => $params['out_trade_no'],
            'merchant_notify_url' => env('APP_URL') . '/api/wxpay/refund',
            'refund_amount' => $params['refund_amount'],
            'merchant_refund_no' => $params['refund_no'],
        ]);
        Log::debug('wechatRefund data=' . $data);
        $rs = json_decode($this->postRequest($url, $data));
        Log::debug('wechatRefund rs=' . json_encode($rs));

        if (200 != $rs->status) {
            $this->throwException($rs->msg, 5515);
        }

        // TODO 后面通过回调接口更新退款成功状态
        $refund = Refund::where('refund_no', $params['refund_no'])->first();
        $refund->refund_status = Refund::STATUS_SUCCESS;
        $refund->save();

        return $rs;
    }
}
