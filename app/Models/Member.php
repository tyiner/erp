<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Member extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $table = 'members';
    protected $guarded = [];
    protected $appends = ['type_cn', 'status_cn'];

    const AUTH_NAME = 'member';

    /** @var int 普通用户 */
    const TYPE_NORMAL = 0;
    /** @var int 金牌用户 */
    const TYPE_GOLD = 1;
    /** @var int 推广用户 */
    const TYPE_PROMOTE = 2;

    /** @var int 正常 */
    const STATUS_NORMAL = 0;
    /** @var int 黑名单（禁止分佣） */
    const STATUS_FORBID = -1;

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
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
     * 关联团长
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function head()
    {
        return $this->hasOne('App\Models\Member', 'id', 'head_id');
    }

    /**
     * 关联邀请者
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function inviter()
    {
        return $this->hasOne('App\Models\Member', 'id', 'inviter_id');
    }

    /**
     * 允许操作的订单状态
     * @return array
     */
    public static function authOrderStatus()
    {
        return [
            Order::STATUS_CLOSE,
            Order::STATUS_COMPLETE,
        ];
    }

    /**
     * 获取客户类型选项
     * @return string[]
     */
    public static function getTypeOptions(): array
    {
        return [
            self::TYPE_NORMAL => '普通用户',
            self::TYPE_GOLD => '金牌用户',
            self::TYPE_PROMOTE => '推广用户',
        ];
    }

    /**
     * 获取客户类型文本
     * @return string
     */
    public function getTypeCnAttribute()
    {
        $options = self::getTypeOptions();
        return $options[$this->type] ?? "未知($this->type)";
    }

    /**
     * 获取客户状态选项
     * @return string[]
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_NORMAL => '正常',
            self::STATUS_FORBID => '禁佣',
        ];
    }

    /**
     * 获取客户状态文本
     * @return string
     */
    public function getStatusCnAttribute()
    {
        $options = self::getStatusOptions();
        return $options[$this->status] ?? "未知($this->status)";
    }

    /**
     * 能否推广分享
     * @return bool
     */
    public function canPromoteShare() {
        if (in_array($this->type, [Member::TYPE_GOLD, Member::TYPE_PROMOTE])) {
            return true;
        }
        return false;
    }

    /**
     * 能否进行分佣
     * @return bool
     */
    public function canCommission() {
        if (self::STATUS_NORMAL == $this->status) {
            return true;
        }
        return false;
    }

}
