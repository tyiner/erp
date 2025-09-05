<?php

namespace App\Services\Purchase;

use App\Models\Purchase\Department;
use App\Services\BaseService;
use Illuminate\Support\Facades\Redis;

/**
 * Class DepartmentService
 *
 * @package App\Services\Purchase
 */
class DepartmentService extends BaseService
{
    protected $model;

    public function __construct(Department $model)
    {
        $this->model = $model;
    }

    /**
     * 添加部门
     *
     * @param array $data
     * @return bool
     */
    public function create(array $data): bool
    {
        return $this->model->fill($data)->save();
    }

    /**
     * 删除部门
     *
     * @param array $ids
     * @return mixed
     */
    public function delete(array $ids)
    {
        $keys = [];
        foreach ($ids as $id) {
            $keys[] = $id . '_id_department';
        }
        $redis = Redis::connection('cache');
        $redis->del($keys);
        return $this->model->whereIn('id', $ids)->delete();
    }

    /**
     * 更新部门
     *
     * @param array $data
     * @return mixed
     */
    public function update(array $data)
    {
        $id = data_get($data, 'id');
        return $this->model->where('id', $id)->update($data);
    }

    /**
     * 获取部门列表
     * @param array $data
     * @return mixed
     */
    public function getList(array $data)
    {
        if (isAdmin()) {
        }
        $limit = data_get($data, 'limit', 20);
        $query = $this->model;
        if (data_get($data, 'ids')) {
            $query = $query->whereIn('id', $data['ids']);
        }
        if (data_get($data, 'name')) {
            $query = $query->where('name', 'like', '%' . $data['name'] . '%');
        }
        if (!is_null(data_get($data, 'status'))) {
            $query = $query->where('status', $data['status']);
        }
        if (data_get($data, 'company_id')) {
            $query = $query->where('company_id', $data['company_id']);
        }
        return $query->orderBy('created_at', 'desc')->paginate($limit);
    }
}
