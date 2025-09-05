<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\MemberService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MemberController extends Controller
{
    public function loginTest(Request $request, MemberService $service)
    {
        try {
            $openid = $request->openid;
            $storeId = $request->store_id;
            $inviterId = $request->inviter_id;

            if (empty($openid) || empty($storeId)) {
                $this->throwException('参数错误', 1001);
            }
            $data = $service->login($openid, $storeId, $inviterId);
            return $this->success($data);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    /**
     * 微信小程序登录
     * @param Request $request
     * @param MemberService $service
     * @return array
     */
    public function login(Request $request, MemberService $service)
    {
        try {
            $js_code = $request->js_code;
            $store_id = $request->store_id;
            $inviter_id = $request->inviter_id;

            if (empty($js_code)) {
                $this->throwException('参数错误：js_code不能为空', 1011);
            }

            if (empty($store_id)) {
                $this->throwException('参数错误：store_id不能为空', 1012);
            }

//            if (empty($inviter_id)) {
//                $this->throwException('参数错误：inviter_id不能为空', 1013);
//            }
            $openid = $service->getOpenidByCode($js_code)['openid'];
            $data = $service->login($openid, $store_id, $inviter_id);
            return $this->success($data);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    public function bindPhone(Request $request, MemberService $memberService)
    {
        try {
            $phone = $request->phone;
            if (empty($phone)) {
                throw new \Exception('绑定的手机号不能为空');
            }

            $userService = new UserService();
            if (!$userInfo = $userService->getUserInfo('member')) {
                throw new \Exception('未登录');
            }

            $memberService->bindPhone($phone, $userInfo->id);
            return $this->success([]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
