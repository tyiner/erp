<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    protected $appends = ['withdrawal_way_cn', 'withdrawal_status_cn'];

    // 提现方式

    /** @var int 微信 */
    const WAY_WECHAT = 1;
    /** @var int 支付宝 */
    const WAY_ALIPAY = 2;

    /** @var int 申请中 */
    const STATUS_APPLY = 0;
    /** @var int 已同意（未到账） */
    const STATUS_AGREE = 1;
    /** @var int 已拒绝 */
    const STATUS_REFUSE = 2;
    /** @var int 已完成（已入账） */
    const STATUS_FINISH = 3;

    /**
     * 关联店铺信息
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function store()
    {
        return $this->hasOne('App\Models\Store', 'id', 'store_id');
    }

    /**
     * 关联订单信息
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function order()
    {
        return $this->hasOne('App\Models\Order', 'id', 'order_id');
    }

    /**
     * 关联用户信息
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function member()
    {
        return $this->hasOne('App\Models\Member', 'id', 'user_id');
    }

    /**
     * 获取提现方式列表
     * @return string[]
     */
    public static function getWithdrawalWayOptions(): array
    {
        return [
            self::WAY_WECHAT => '微信',
            self::WAY_ALIPAY => '支付宝',
        ];
    }

    /**
     * 获取提现方式文本
     * @return string
     */
    public function getWithdrawalWayCnAttribute(): string
    {
        $options = self::getWithdrawalWayOptions();
        return $options[$this->withdrawal_way] ?? "未知($this->withdrawal_way)";
    }

    /**
     * 获取提现状态列表
     * @return string[]
     */
    public static function getWithdrawalStatusOptions(): array
    {
        return [
            self::STATUS_APPLY => '申请中',
            self::STATUS_AGREE => '已同意',
            self::STATUS_REFUSE => '已拒绝',
            self::STATUS_FINISH => '已完成',
        ];
    }

    /**
     * 获取提现状态文本
     * @return string
     */
    public function getWithdrawalStatusCnAttribute(): string
    {
        $options = self::getWithdrawalStatusOptions();
        return $options[$this->withdrawal_status] ?? "未知($this->withdrawal_status)";
    }
}
