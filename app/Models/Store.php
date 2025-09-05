<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $fillable = [
        'user_id',
        'store_name',
        'due',
        'store_status',
        'store_verify',
        'copr_logo',
        'team_num',
        'msg_num',
    ];

    /**
     * 关联订单评论
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comments()
    {
        return $this->hasMany('App\Models\OrderComment', 'store_id', 'id');
    }

    /**
     * 关联订单列表
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orders()
    {
        return $this->hasMany('App\Models\Order', 'store_id', 'id');
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
     * 关联分销设置
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function distribution_setting() {
        return $this->hasOne('App\Models\DistributionSetting', 'store_id', 'id');
    }

    /**
     * 关联提现设置
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function withdrawal_setting() {
        return $this->hasOne('App\Models\WithdrawalSetting', 'store_id', 'id');
    }
}
