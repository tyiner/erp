<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];
    protected $appends = [
        'order_status_cn',
        'order_refund_status',
        'order_refund_status_cn',
        'order_source_cn',
        'delivery_type_cn',
        'delivery_name',
    ];

    // 订单状态

    /** @var int 已关闭 */
    const STATUS_CLOSE = 0;
    /** @var int 代付款 */
    const STATUS_WAIT_PAY = 1;
    /** @var int 待发货（已支付） */
    const STATUS_WAIT_SEND = 2;
    /** @var int 待收货（已发货） */
    const STATUS_WAIT_CONFIRM = 3;
    /** @var int 已完成（确定收货） */
    const STATUS_COMPLETE = 4;
    /** @var int 申请售后 */
    const STATUS_APPLY_REFUND = 5;
    /** @var int 已结束 */
    const STATUS_FINISH = 6;

    // 订单类型

    /** @var int 销售单 */
    const TYPE_NEW = 0;
    /** @var int 换货单 */
    const TYPE_EXCHANGE = 1;

    // 配送方式

    /** @var int 上门自提 */
    const DELIVERY_TYPE_SELF = 0;
    /** @var int 快递配送 */
    const DELIVERY_TYPE_SEND = 1;

    /** @var int 后台创建 */
    const SOURCE_ADMIN = 0;
    /** @var int 微信小程序 */
    const SOURCE_WECHAT_MINI = 1;

    /**
     * 关联订单商品
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function order_goods()
    {
        return $this->hasMany('App\Models\OrderGoods', 'order_id', 'id');
    }

    /**
     * 关联店铺
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function store()
    {
        return $this->hasOne('App\Models\Store', 'id', 'store_id');
    }

    /**
     * 关联客户
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function member()
    {
        return $this->hasOne('App\Models\Member', 'id', 'user_id');
    }

    /**
     * 关联售后
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function refund()
    {
        return $this->hasMany('App\Models\Refund', 'order_id', 'id');
    }

    /**
     * 关联分销日志
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function distribution()
    {
        return $this->hasMany('App\Models\DistributionLog', 'order_id', 'id');
    }

    /**
     * 关联订单支付
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function order_pay()
    {
        return $this->hasMany('App\Models\OrderPay', 'order_id', 'id');
    }

    /**
     * 获取订单状态选项
     * @return string[]
     */
    public static function getOrderStatusOptions(): array
    {
        return [
            self::STATUS_CLOSE => '已关闭',
            self::STATUS_WAIT_PAY => '待付款',
            self::STATUS_WAIT_SEND => '待发货',
            self::STATUS_WAIT_CONFIRM => '待收货',
            self::STATUS_COMPLETE => '已完成',
            self::STATUS_APPLY_REFUND => '申请售后中',
            self::STATUS_FINISH => '已结束',
        ];
    }

    /**
     * 获取订单状态文本
     * @return string
     */
    public function getOrderStatusCnAttribute(): string
    {
        $options = self::getOrderStatusOptions();
        return $options[$this->order_status] ?? "未知($this->order_status)";
    }

    /**
     * 获取订单+售后状态
     * @return int|mixed
     */
    public function getOrderRefundStatusAttribute()
    {
        $orderStatus = $this->order_status;
        if ($this->isApplyRefund()) {
            $orderStatus = self::STATUS_APPLY_REFUND;
        }
        return $orderStatus;
    }

    /**
     * 是否申请售后中
     * @return bool
     */
    public function isApplyRefund()
    {
        // 已关闭是最后环节，权重最大
        if (self::STATUS_CLOSE == $this->order_status) return false;

        $applyRefund = [
            Refund::VERIFY_WAIT_PROCESS,
            Refund::VERIFY_AGREE,
        ];
        if (isset($this->refund[0]) && in_array($this->refund[0]->refund_verify, $applyRefund)) {
            return true;
        }
        return false;
    }

    /**
     * 获取订单状态步骤
     * @return int
     */
    public function getOrderStatusStep(): int
    {
        $options = [
            self::STATUS_WAIT_PAY => 0,
            self::STATUS_WAIT_SEND => 1,
            self::STATUS_WAIT_CONFIRM => 2,
            self::STATUS_COMPLETE => 3,
            self::STATUS_APPLY_REFUND => 4,
            self::STATUS_CLOSE => 5,
        ];
        return $options[$this->order_refund_status];
    }

    /**
     * 获取订单来源选项
     * @return string[]
     */
    public static function getOrderSourceOptions(): array
    {
        return [
            self::SOURCE_ADMIN => '后台创建',
            self::SOURCE_WECHAT_MINI => '微信小程序',
        ];
    }

    /**
     * 获取订单来源文本
     * @return string
     */
    public function getOrderSourceCnAttribute(): string
    {
        $options = self::getOrderSourceOptions();
        return $options[$this->order_source] ?? "未知($this->order_source)";
    }

    /**
     * 获取物流方式选项
     * @return string[]
     */
    public static function getDeliveryTypeOptions(): array
    {
        return [
            0 => '自提',
            1 => '快递',
        ];
    }

    /**
     * 获取物流方式文本
     * @return string
     */
    public function getDeliveryTypeCnAttribute(): string
    {
        $options = self::getDeliveryTypeOptions();
        return $options[$this->delivery_type] ?? "未知($this->delivery_type)";
    }

    /**
     * 获取物流名称
     * @return mixed
     */
    public function getDeliveryNameAttribute()
    {
        $express = Express::where('code', $this->delivery_code)->first('name');
        return $express->name ?? '';
    }

    /**
     * 能否关闭订单
     * @return bool
     */
    public function canClose()
    {
        $allow = [
            self::STATUS_WAIT_PAY,
        ];
        return true/*in_array($this->order_status, $allow)*/;
    }

    /**
     * 能否确定收货
     * @return bool
     */
    public function canConfirm()
    {
        $allow = [
            self::STATUS_WAIT_CONFIRM,
        ];
        return in_array($this->order_status, $allow);
    }

    /**
     * 能否申请退款
     * @return bool
     */
    public function canRefund()
    {
        $allow = [
            self::STATUS_WAIT_SEND,
            self::STATUS_WAIT_CONFIRM,
            self::STATUS_COMPLETE,
        ];
        return in_array($this->order_status, $allow);
    }

    /**
     * 能否申请退货退款
     * @return bool
     */
    public function canReturnRefund()
    {
        $allow = [
            self::STATUS_WAIT_CONFIRM,
            self::STATUS_COMPLETE,
        ];
        return in_array($this->order_status, $allow);
    }

    /**
     * 能否申请换货
     * @return bool
     */
    public function canExchange()
    {
        $allow = [
            self::STATUS_WAIT_CONFIRM,
            self::STATUS_COMPLETE,
        ];
        return in_array($this->order_status, $allow);
    }
}
