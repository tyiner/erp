<?php

namespace App\Traits;

use App\Models\Admin;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Log;

trait UserTrait
{
    /**
     * 获取店铺ID
     * @return mixed
     */
    protected function getStoreId()
    {
        return auth()->user()->store->id;
    }

    /**
     * 获取用户ID
     * @return mixed
     */
    protected function getUserId()
    {
        return auth()->user()->id;
    }

    /**
     * 获取用户信息
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    protected function getUser()
    {
        return auth()->user();
    }

    /**
     * 获取微信OpenID
     * @return mixed
     */
    protected function getOpenId()
    {
        return auth()->user()->openid;
    }

    /**
     * 是否小程序客户
     * @return bool
     */
    protected function isMember()
    {
        return Member::class == get_class(auth()->user());
    }

    /**
     * 是否分销商
     * @return bool
     */
    protected function isSeller()
    {
        return User::class == get_class(auth()->user());
    }

    /**
     * 获取邀请者ID
     * @return mixed
     */
    protected function getInviterId()
    {
        return auth()->user()->inviter_id;
    }

    /**
     * 获取推广者ID
     * @param $userId
     * @return mixed
     */
    protected function getPromoterId($userId)
    {
        if (0 == $userId) {
            return $userId;
        }

        $member = Member::find($userId);
        if (Member::TYPE_PROMOTE == $member->type) {
            return $member->id;
        }

        return $this->getPromoterId($member->inviter_id);
    }

    /**
     * 获取推广者
     * @return null
     */
    protected function getPromoter()
    {
        $promoterId = $this->getPromoterId($this->getUserId());
        if (!$promoterId) {
            return null;
        }

        return Member::find($promoterId);
    }
}
