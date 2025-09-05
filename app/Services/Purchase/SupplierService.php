<?php

namespace App\Services\Purchase;

use App\Models\Purchase\Supplier;
use App\Services\BaseService;

/**
 * Class SupplierService
 * @package App\Services\Purchase
 */
class SupplierService extends BaseService
{
    private $model;

    public function __construct(Supplier $model)
    {
        $this->model = $model;
    }

    /**
     * 根据条件获取供应商
     * @param array $data
     * @return mixed
     */
    public function getByCondition(array $data)
    {
        $query = $this->model;
        if (data_get($data, 'name')) {
            $query = $query->where('name', $data['name']);
        }
        if (data_get($data, 'no')) {
            $query = $query->where('no', $data['no']);
        }
        return $query->first();
    }

    /**
     * 创建新供应商
     * @param array $data
     * @return Supplier
     */
    public function create(array $data): Supplier
    {
        $this->model->fill($data);
        $this->model->save();
        return $this->model;
    }

    /**
     * 根据id删除供应商
     * @param array $ids
     * @return int
     */
    public function delete(array $ids): int
    {
        return $this->model->whereIn('id', $ids)->delete();
    }

    /**
     * 更新供应商
     * @param array $data
     * @return mixed
     */
    public function update(array $data)
    {
        return $this->model->where('id', data_get($data, 'id'))->update($data);
    }

    /**
     * 获取供应商列表
     * @param array $data
     * @return mixed
     */
    public function getList(array $data)
    {
        $limit = data_get($data, 'limit', 20);
        $query = $this->model;
        if (data_get($data, 'ids')) {
            $query = $query->whereIn('id', $data['ids']);
        }
        if (data_get($data, 'name')) {
            $ret = $this->model->where('name', 'like', '%' . $data['name'] . '%')->count();
            if ($ret) {
                $query = $query->where('name', 'like', '%' . $data['name'] . '%');
            } else {
                $query = $query->where('no', 'like', '%' . $data['name'] . '%');
            }
        }
        if (data_get($data, 'no')) {
            $query = $query->where('no', 'like', '%' . $data['no'] . '%');
        }
        if (!is_null(data_get($data, 'status'))) {
            $query = $query->where('status', $data['status']);
        }
        return $query->orderByDesc('created_at')->paginate($limit);
    }
}
