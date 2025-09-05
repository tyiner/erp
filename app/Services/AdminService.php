<?php

namespace App\Services;

use App\Models\Admin;

/**
 * Class AdminService
 * @package App\Services
 */
class AdminService extends BaseService
{
    protected $model;

    public function __construct(Admin $model)
    {
        $this->model = $model;
    }

    /**
     * 添加新账户
     * @param array $user
     * @return Admin
     */
    public function store(array $user): Admin
    {
        $ret = $this->model->where('username', data_get($user, 'username'))->first();
        if (!is_null($ret)) {
            error("用户名已存在");
        }
        $ret = $this->model->where('phone', data_get($user, 'phone'))->first();
        if (!is_null($ret)) {
            error("电话已存在");
        }
        $ret = $this->model->where('employee_no', data_get($user, 'employee_no'))->first();
        if (!is_null($ret)) {
            error("工号已存在");
        }
        $this->model->fill($user)->save();
        $this->model->roles()->sync(data_get($user, 'role_id', []));
        $this->model->locations()->sync(data_get($user, 'location_id', []));
        return $this->model;
    }

    public function show(int $id)
    {
        $info = $this->model->with('roles', 'locations')->find($id);
        unset($info->password);
        $company = $this->getCompany(data_get($info, 'company_id'));
        $info->company_name = data_get($company, 'name');
        $department = $this->getDepartment(data_get($info, 'department_id'));
        $info->department_name = data_get($department, 'name');
        $info->roles_id = data_get($info, 'roles.0.id');
        $info->roles_name = data_get($info, 'roles.0.name');
        $info->loation_ids = $info->locations->pluck('id')->flatten();
        unset($info->roles);
        unset($info->locations);
        return $info;
    }

    /**
     * 按条件获取员工账户
     * @param array $data
     * @return array
     */
    public function list(array $data): array
    {
        $query = $this->model;
        if (data_get($data, 'role_id')) {
            $roleId = data_get($data, 'role_id');
            $query = $query->with([
                'roles' => function ($q) use ($roleId) {
                    return $q->where('admin_role.role_id', $roleId);
                },
                'locations'
            ]);
        } else {
            $query = $query->with('roles', 'locations');
        }
        if (data_get($data, 'staff')) {
            $query = $query->where('nickname', 'like', '%' . data_get($data, 'staff') . '%');
            $query = $query->orWhere('employee_no', data_get($data, 'staff'));
        }
        if (data_get($data, 'department_id')) {
            $query = $query->where('department_id', data_get($data, 'department_id'));
        }
        $ret = $query->get()->toArray();
        $lists = [];
        if (!empty($ret)) {
            foreach ($ret as $item) {
                if (empty($item['roles'])) {
                    continue;
                }
                $lists[] = [
                    'id' => $item['id'],
                    'username' => $item['username'],
                    'nickname' => $item['nickname'],
                    'department_id' => $item['department_id'],
                    'phone' => $item['phone'],
                    'department' => data_get($this->getDepartment($item['department_id']), 'name'),
                    'employee_no' => $item['employee_no'],
                    'role' => $item['roles'][0]['name'],
                    'ip' => $item['ip'],
                    'login_time' => $item['login_time'],
                    'last_login_time' => $item['last_login_time'],
                    'created_at' => $item['created_at'],
                ];
            }
        }
        return $lists;
    }
}
