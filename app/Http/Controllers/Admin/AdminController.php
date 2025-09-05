<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminResource\AdminListCollection;
use App\Http\Resources\Admin\AdminResource\AdminResource;
use App\Models\Admin;
use App\Services\AdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Class AdminController
 * @package App\Http\Controllers\Admin
 */
class AdminController extends Controller
{
    protected $service;

    public function __construct(AdminService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, Admin $admin_model)
    {
        $list = new AdminListCollection($admin_model->with('roles')->orderBy('id',
            'desc')->paginate($request->per_page ?? 30));
        return $this->success($list);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Admin $admin_model)
    {
        $rules = [
            'username' => 'required|string|max:45',
            'password' => 'required|string',
            'nickname' => 'required|string|max:45',
            'phone' => 'required|string',
            'email' => 'string',
            'company_id' => 'required|int',
            'department_id' => 'required|int',
            'role_id' => 'required|int',
            'employee_no' => 'required|string|max:20',
            'status' => 'int|in:0,1',
        ];
        $this->handleValidateRequest($request, $rules);
        $user = $request->all();
        $user['password'] = Hash::make($user['password']);
        $user = $this->service->store($user);
        if ($user) {
            success(['id' => data_get($user, 'id')]);
        }
        error("添加用户失败");
    }

    /**
     * 按条件获取员工列表
     * @param Request $request
     */
    public function list(Request $request)
    {
        $rules = [
            'role_id' => 'int',
            'department_id' => 'int'
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        $ret = $this->service->list($data);
        success($ret);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show(Admin $admin_model, $id)
    {
        $ret = $this->service->show($id);
        return $this->success($ret);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Admin $admin_model, $id)
    {
        $existUsername = $admin_model->where('username', $request->username)->where('id', '<>', $id)->exists();
        $current = $admin_model->where('id', $id)->first();
        if (ADMIN_NAME == data_get($current, 'username')) {
            error("超级管理员账户信息不能修改");
        }
        if ($existUsername) {
            return $this->error('用户名已经存在');
        }
        $existPhone = $admin_model->where('phone', $request->phone)->where('id', '<>', $id)->exists();
        if ($existPhone) {
            return $this->error('手机号已经存在');
        }
        $existNo = $admin_model->where('employee_no', $request->employee_no)->where('id', '<>', $id)->exists();
        if ($existNo) {
            return $this->error('工号已经存在');
        }

        $admin_model = $admin_model->find($id);
        $admin_model->username = $request->username;
        if (!empty($request->password)) {
            $admin_model->password = Hash::make($request->password ?? '123456');
        }
        $admin_model->nickname = $request->nickname ?? '';
        $admin_model->avatar = $request->avatar ?? '';
        $admin_model->phone = $request->phone ?? '';
        $admin_model->email = $request->email ?? '';
        $admin_model->company_id = $request->company_id ?? 0;
        $admin_model->department_id = $request->department_id ?? 0;
        $admin_model->status = $request->status ?? 0;
        $admin_model->employee_no = $request->employee_no ?? '';
        $admin_model->save();
        $admin_model->roles()->sync($request->role_id ?? []);
        $admin_model->locations()->sync($request->location_id ?? []);
        return $this->success([], __('base.success'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Admin $admin_model, $id)
    {
        $idArray = array_filter(explode(',', $id), function ($item) {
            return is_numeric($item);
        });
        foreach ($idArray as $v) {
            $current = $admin_model->where('id', $v)->first();
            if (ADMIN_NAME == data_get($current, 'username')) {
                error("超级管理员账户不能被删除");
            }
            $admin_model->roles()->detach();
            $admin_model->refresh();
            $admin_model->destroy([$v]);
        }
        return $this->success([], __('base.success'));
    }
}
