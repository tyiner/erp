<?php

namespace App\Http\Controllers\Right;

use App\Http\Controllers\Controller;
use App\Services\Right\AdminLocationService;
use http\Client\Curl\User;
use Illuminate\Http\Request;

class ManagerController extends Controller
{
    private $service;

    public function __construct(AdminLocationService $service)
    {
        $this->service = $service;
    }

    /**
     * 添加用户仓库权限
     *
     * @param Request $request
     */
    public function addLocation(Request $request)
    {
        $rules = [
            "location_ids" => "required|string",
            "user_id" => "required|int",
        ];
        $msg = [
            "location_ids.required" => '添加的仓库id不能为空',
            "location_ids.string" => '仓库必须为拼接的字符串',
            "user_id.required" => '用户id不能为空',
            "user_id.int" => '用户类型必须为数字',
        ];
        $this->handleValidateRequest($request, $rules, $msg);
        $location_ids = explode(',', data_get($request, 'location_ids'));
        $user_id = $request->input('user_id');
        $ret = $this->service->add(compact('location_ids', 'user_id'));
        success($ret);
    }

    /**
     * @param Request $request
     */
    public function update(Request $request)
    {
        $rules = [
            "location_ids" => "required|string",
            "user_id" => "required|int",
        ];
        $msg = [
            "location_ids.required" => '添加的仓库id不能为空',
            "location_ids.string" => '仓库必须为拼接的字符串',
            "user_id.required" => '用户id不能为空',
            "user_id.int" => '用户类型必须为数字',
        ];
        $this->handleValidateRequest($request, $rules, $msg);
        $location_ids = explode(',', data_get($request, 'location_ids'));
        $user_id = $request->input('user_id');
        $ret = $this->service->update(compact('location_ids', 'user_id'));
        success($ret);
    }

    public function get()
    {
        $id = auth('admin')->id();
        if (empty($id)) {
            error("请先进行登录");
        }
        $list = $this->service->getByAdminId($id);
        success($list);
    }
}
