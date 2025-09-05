<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MemberService extends BaseService
{
    const WXAPPLET_APPID = 'wx7906bbe78c24bd21';
    const WXAPPLET_APPSECRET = '5f3a8038ab1faad0184ad7eee8578e8e';
    const JSCODE2SESSION_URL = 'https://api.weixin.qq.com/sns/jscode2session?appid={APPID}&secret={SECRET}&js_code={JSCODE}&grant_type=authorization_code';

    /**
     * 获取微信openID
     * @param $js_code
     * @return mixed
     * @throws \Exception
     */
    public function getOpenidByCode($js_code)
    {
        $url = str_replace([
            '{APPID}',
            '{SECRET}',
            '{JSCODE}'
        ], [
            self::WXAPPLET_APPID,
            self::WXAPPLET_APPSECRET,
            $js_code,
        ], self::JSCODE2SESSION_URL);

        $res = json_decode($this->request($url), true);
        if (empty($res['openid'])) {
            $this->throwException('获取openid失败', 1002);
        }

        return $res;
    }

    /**
     * 微信小程序登录
     * @param $openid
     * @param $storeId
     * @param $inviteId
     * @return array
     * @throws \Exception
     */
    public function login($openid, $storeId, $inviteId)
    {
        $member = Member::where([
            ['openid', '=', $openid],
            ['store_id', '=', $storeId],
        ])->first();
        if (!$member) {
            $member = new Member();
            $member->openid = $openid;
            $member->store_id = $storeId;
//            $member->inviter_id = $inviteId;
            if (!$member->save()) {
                $this->throwException('注册失败', 1003);
            }
        }

        if (!$token = auth(Member::AUTH_NAME)->login($member)) {
            $this->throwException('登录失败', 1004);
        }

        // 更新登录时间
        $member = Member::find($member->id);
        $member->last_login_time = date('Y-m-d H:i:s');
        $member->ip = request()->getClientIp();
        $member->save();

        $data = [
            'token' => $token,
            'user_info' => auth(Member::AUTH_NAME)->user(),
        ];

        return $data;
    }

    public function bindPhone($phone, $id)
    {
        $member = Member::find($id);
        $member->phone = $phone;
        return $member->save();
    }

    /**
     * 获取客户信息
     * @param $id
     * @return mixed
     * @throws \Exception
     */
    public function get($id) {
        $member = new Member();

        if ($this->isSeller()) {
            $member = $member->where('store_id', $this->getStoreId());
        }

        $member = $member->where('id', $id)->first();

        if (!$member) {
            $this->throwException('客户不存在', 5503);
        }

        return $member;
    }

}
