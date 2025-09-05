<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Distribution extends Model
{
    protected $appends = ['commission_status_cn'];

    /** @var int 待入账 */
    const STATUS_AWAIT = 0;
    /** @var int 已入账 */
    const STATUS_OBTAIN = 1;
    /** @var int 已关闭 */
    const STATUS_CLOSE = -1;

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
     * 金牌佣金原本去向
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function gold_user()
    {
        return $this->hasOne('App\Models\Member', 'id', 'gold_initial_user_id');
    }

    /**
     * 金牌佣金实际去向
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function gold_current_user()
    {
        return $this->hasOne('App\Models\Member', 'id', 'gold_current_user_id');
    }

    /**
     * 推广佣金原本去向
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function promote_user()
    {
        return $this->hasOne('App\Models\Member', 'id', 'promote_initial_user_id');
    }

    /**
     * 推广佣金实际去向
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function promote_current_user()
    {
        return $this->hasOne('App\Models\Member', 'id', 'promote_current_user_id');
    }

    /**
     * 获取佣金状态列表
     * @return string[]
     */
    public static function getCommissionStatusOptions(): array
    {
        return [
            self::STATUS_CLOSE => '已关闭',
            self::STATUS_AWAIT => '待入账',
            self::STATUS_OBTAIN => '已入账',
        ];
    }

    /**
     * 获取佣金状态文本
     * @return string
     */
    public function getCommissionStatusCnAttribute(): string
    {
        $options = self::getCommissionStatusOptions();
        return $options[$this->commission_status] ?? "未知($this->commission_status)";
    }
}
