<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\Role\RoleService;
use Illuminate\Http\Request;

/**
 * Class RoleController
 * @package App\Http\Controllers\Admin
 */
class RoleController extends Controller
{
    protected $service;

    public function __construct(RoleService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, Role $role_model)
    {
        $list = $role_model->orderBy('id', 'desc')->paginate($request->limit ?? 30);
        return $this->success($list);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Role $role_model)
    {
        $rules = [
            'name' => "required|string",
        ];
        $msg = [
            'name.required' => '角色名称不能为空',
            'name.string' => '角色名称必须为字符',
        ];
        $this->handleValidateRequest($request, $rules, $msg);
        $name = $request->input('name');
        $ret = $this->service->getByName($name);
        if ($ret->count() > 0) {
            error("角色已存在");
        }
        $role_model = $role_model->create(['name' => $request->name]);
        $role_model->menus()->sync($request->menu_id ?? []);
        $role_model->permissions()->sync($request->permission_id ?? []);
        return $this->success(['id' => data_get($role_model, 'id')], __('base.success'));
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show(Role $role_model, $id)
    {
        $info = $role_model->with(['menus', 'permissions'])->find($id);
        return $this->success($info);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Role $role_model, $id)
    {
        $role_model = $role_model->find($id);
        $role_model->name = $request->name;
        $role_model->save();
        $role_model->menus()->sync($request->menu_id ?? []);
        $role_model->permissions()->sync($request->permission_id ?? []);
        return $this->success([], __('base.success'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Role $role_model, $id)
    {
        $idArray = array_filter(explode(',', $id), function ($item) {
            return is_numeric($item);
        });
        foreach ($idArray as $v) {
            $role_model = $role_model->find($v);
            $role_model->menus()->detach();
            $role_model->permissions()->detach();
            $role_model->refresh();
        }
        $role_model->destroy($idArray);
        return $this->success([], __('base.success'));
    }
}
