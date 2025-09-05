<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPay extends Model
{
    protected $guarded = [];
    protected $appends = ['payment_name_cn', 'pay_type_cn'];

    // 支付方式 pay_type

    /** @var int 微信APP支付 */
    const PAY_TYPE_WECHAT_APP = 1;
    /** @var int 微信公众号支付 */
    const PAY_TYPE_WECHAT_PUBLIC = 2;
    /** @var int 微信小程序支付 */
    const PAY_TYPE_WECHAT_MINI = 3;
    /** @var int 微信扫码支付 */
    const PAY_TYPE_WECHAT_SCAN = 4;
    /** @var int 微信WAP/H5支付 */
    const PAY_TYPE_WECHAT_H5 = 5;

    // 支付方式 payment_name

    /** @var string 微信WAP支付 */
    const PAYMENT_WECHAT_H5 = 'wechat_h5';
    /** @var string 微信公众号支付 */
    const PAYMENT_WECHAT_PUBLIC = 'wechat_public';
    /** @var string 微信APP支付 */
    const PAYMENT_WECHAT_APP = 'wechat_app';
    /** @var string 微信小程序支付 */
    const PAYMENT_WECHAT_MINI = 'wechat_mini';
    /** @var string 微信扫码支付 */
    const PAYMENT_WECHAT_SCAN = 'wechat_scan';
    /** @var string 支付宝WAP支付 */
    const PAYMENT_ALI_H5 = 'ali_h5';
    /** @var string 支付宝APP支付 */
    const PAYMENT_ALI_APP = 'ali_app';
    /** @var string 支付宝小程序支付 */
    const PAYMENT_ALI_MINI = 'ali_mini';
    /** @var string 支付宝扫码支付 */
    const PAYMENT_ALI_SCAN = 'ali_scan';
    /** @var string 余额支付 */
    const PAYMENT_MONEY = 'money';

    /**
     * 关联订单
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function order()
    {
        return $this->hasOne('App\Models\Order', 'id', 'order_id');
    }

    /**
     * 获取支付方式列表
     * @return string[]
     */
    public static function getPaymentNameOptions(): array
    {
        return [
            self::PAYMENT_WECHAT_APP => '微信APP支付',
            self::PAYMENT_WECHAT_H5 => '微信H5支付',
            self::PAYMENT_WECHAT_MINI => '微信小程序支付',
            self::PAYMENT_WECHAT_PUBLIC => '微信公众号支付',
            self::PAYMENT_WECHAT_SCAN => '微信扫码支付',
            self::PAYMENT_ALI_APP => '支付宝APP支付',
            self::PAYMENT_ALI_H5 => '支付宝WAP支付',
            self::PAYMENT_ALI_MINI => '支付宝小程序支付',
            self::PAYMENT_ALI_SCAN => '支付宝扫码支付',
            self::PAYMENT_MONEY => '余额支付',
        ];
    }

    /**
     * 获取支付方式文本
     * @return string
     */
    public function getPaymentNameCnAttribute(): string
    {
        $options = self::getPaymentNameOptions();
        return $options[$this->payment_name] ?? "未知($this->payment_name)";
    }

    /**
     * 获取支付方式（余额、微信和支付宝）
     * @return string
     */
    public function getPayTypeCnAttribute(): string
    {

        if (empty($this->payment_name)) {
            return '-';
        }

        if (self::PAYMENT_MONEY == $this->payment_name) {
            return '余额支付';
        }

        if (in_array($this->payment_name, [
            self::PAYMENT_WECHAT_APP,
            self::PAYMENT_WECHAT_H5,
            self::PAYMENT_WECHAT_MINI,
            self::PAYMENT_WECHAT_PUBLIC,
            self::PAYMENT_WECHAT_SCAN,
        ])) {
            return '微信支付';
        }

        if (in_array($this->payment_name, [
            self::PAYMENT_ALI_APP,
            self::PAYMENT_ALI_H5,
            self::PAYMENT_ALI_MINI,
            self::PAYMENT_ALI_SCAN,
        ])) {
            return '支付宝支付';
        }

        return "未知($this->payment_name)";
    }
}
