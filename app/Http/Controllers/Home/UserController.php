<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Http\Resources\Home\OrderResource\OrderCollection;
use App\Http\Resources\Home\UserResource\UserEditResource;
use App\Http\Resources\Home\UserResource\UserResource;
use App\Models\Favorite;
use App\Models\Order;
use App\Services\UserService;
use App\Models\UserCheck;
use App\Models\UserWechat;
use App\Services\FavoriteService;
use App\Services\OrderService;
use App\Services\UploadService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function user_info()
    {
        $user_service = new UserService();
        $user_info = $user_service->getUserInfo('member');

        // 获取资料完成度
        $suc = 0;
        $all = 0;
        foreach ($user_info as $v) {
            if (!empty($v)) {
                $suc++;
            }
            $all++;
        }
        $user_info['completion'] = intval($suc / $all * 100);
        $user_info['user_check'] = UserCheck::where('user_id', $user_info['id'])->exists();
        $user_info['wechat_check'] = UserWechat::where('user_id', $user_info['id'])->exists();
        $user_info = new UserResource($user_info);

        return $this->success($user_info);
    }

    public function edit_user(Request $request)
    {
        $user_service = new UserService();
        if ($request->isMethod('put')) {
            $rs = $user_service->editUser();
            return $rs['status'] ? $this->success($rs['data'], __('users.edit_success')) : $this->error(__('users.edit_error'));
        }
        $user_info = $user_service->getUserInfo();
        return $this->success(new UserEditResource($user_info));
    }

    // 个人中心首页默认信息
    public function default()
    {
        // 用户信息
        $user = $this->user_info()['data'];
        $data['user'] = $user;

        // 获取订单数量
        $data['order_count'] = [
            Order::where(['user_id' => $user->id, 'order_status' => Order::STATUS_CLOSE])->count(),
            Order::where(['user_id' => $user->id, 'order_status' => Order::STATUS_WAIT_PAY])->count(),
            Order::where(['user_id' => $user->id, 'order_status' => Order::STATUS_WAIT_SEND])->count(),
            Order::where(['user_id' => $user->id, 'order_status' => Order::STATUS_WAIT_CONFIRM])->count(),
            Order::where(['user_id' => $user->id, 'order_status' => Order::STATUS_COMPLETE])->count(),
            Order::where(['user_id' => $user->id])->whereHas('refund')->count(),
        ];

        // 近期订单信息
//        $orderService = new OrderService();
//        $orderList = $orderService->getOrders()['data'];
//        $data['order_list'] = new OrderCollection($orderList);

        // 收藏
//        $favService = new FavoriteService();
//        $favList = $favService->getFav();
//        $data['fav_list'] = $favList;
        $data['fav_count'] = Favorite::where('user_id', $user->id)->count();

        return $this->success($data);
    }

    // 图片上传
    public function avatar_upload(UploadService $upload_service)
    {
        $user_service = new UserService();
        $user_info = $user_service->getUserInfo();
        $rs = $upload_service->avatar($user_info['id']);
        if ($rs['status']) {
            return $this->success($rs['data'], $rs['msg']);
        } else {
            return $this->error($rs['msg']);
        }
    }
}
