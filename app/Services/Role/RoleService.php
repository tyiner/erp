<?php

namespace App\Services\Role;

use App\Models\Role;
use App\Services\BaseService;

/**
 * Class RoleService
 * @package App\Services\Role
 */
class RoleService extends BaseService
{
    private $model;

    public function __construct(Role $model)
    {
        $this->model = $model;
    }

    /**
     * 根据角色名获取角色
     * @param string $name
     * @return mixed
     */
    public function getByName(string $name)
    {
        return $this->model->where('name', 'like', '%' . $name . '%')->get();
    }
}
