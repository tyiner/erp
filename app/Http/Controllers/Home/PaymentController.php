<?php
namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderPay;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private $wxPaymentName = [
        OrderPay::PAY_TYPE_WECHAT_APP => OrderPay::PAYMENT_WECHAT_APP,
        OrderPay::PAY_TYPE_WECHAT_PUBLIC => OrderPay::PAYMENT_WECHAT_PUBLIC,
        OrderPay::PAY_TYPE_WECHAT_MINI => OrderPay::PAYMENT_WECHAT_MINI,
        OrderPay::PAY_TYPE_WECHAT_SCAN => OrderPay::PAYMENT_WECHAT_SCAN,
        OrderPay::PAY_TYPE_WECHAT_H5 => OrderPay::PAYMENT_WECHAT_H5,
    ];

    /**
     * 微信支付回调
     *
     * @param  PaymentService $service
     * @return array
     */
    public function wxNotify(PaymentService $service)
    {
        try {
            $payType = request()->pay_type; // 支付类型：1-APP支付 2-公众号支付 3-小程序支付 4-扫码支付 5-WAP支付
            $resultCode = request()->result_code;
            $payNo = request()->pay_no; // 支付订单号
            $amount = request()->amount;
            $tradeNo = request()->trade_no; // 支付交易号

            Log::debug('wxNotify pay_type='.$payType);
            Log::debug('wxNotify result_code='.$resultCode);
            Log::debug('wxNotify pay_no='.$payNo);
            Log::debug('wxNotify amount='.$amount);
            Log::debug('wxNotify trade_no='.$tradeNo);

            if (!empty($resultCode)) {
                throw new \Exception('支付失败：' . $resultCode, 4401);
            }

            $paymentName = $this->wxPaymentName[$payType];
            $service->payNotify($paymentName, $tradeNo, $payNo, $amount);
            return $this->success();
        } catch (\Exception $e) {
            Log::error($e->getCode() . '-' . $e->getMessage());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }
}
