<?php

namespace App\Services\Purchase;

use App\Models\Purchase\OrderAuditClassify;
use App\Services\BaseService;
use Illuminate\Support\Facades\Redis;

/**
 * Class OrderAuditClassifyService
 * @package App\Services\Purchase
 */
class OrderAuditClassifyService extends BaseService
{
    protected $model;

    public function __construct(OrderAuditClassify $model)
    {
        $this->model = $model;
    }

    /**
     * 根据type类型获取单据
     * @param int $type
     * @return mixed
     */
    public function getByType(int $type)
    {
        return $this->model->where('order_type', $type)->first();
    }

    /**
     * 根据id获取单据详情
     * @param int $id
     * @return mixed
     */
    public function getById(int $id)
    {
        return $this->model->where('id', $id)->first();
    }

    /**
     * 更新数据
     * @param array $data
     * @return mixed
     */
    public function update(array $data)
    {
        $redis = Redis::connection('cache');
        //$redis->hdel('audit_classify', $data['order_type']);
        $redis->del('audit_classify');
        $order = $this->model->where('id', '<>', data_get($data, 'id'))
            ->where('order_name', data_get($data, 'order_name'))->first();
        if (!is_null($order)) {
            error("相同单据名称已存在");
        }
        $order = $this->model->where('id', '<>', data_get($data, 'id'))
            ->where('order_type', data_get($data, 'order_type'))->first();
        if (!is_null($order)) {
            error("相同单据类型编号已存在");
        }
        $updateData = [];
        $updateData['order_name'] = data_get($data, 'order_name');
        $updateData['order_type'] = data_get($data, 'order_type');
        $updateData['classify'] = data_get($data, 'classify');
        return $this->model->where('id', $data['id'])->update($updateData);
    }

    /**
     * 获取单据审核等级列表
     * @param array $data
     * @return mixed
     */
    public function getList(array $data)
    {
        $limit = data_get($data, 'limit', 20);
        $query = $this->model;
        if (data_get($data, 'order_name')) {
            $query = $query->where('order_name', 'like', '%' . data_get($data, 'order_name') . '%');
        }
        if (data_get($data, 'order_type')) {
            $query = $query->where('order_type', data_get($data, 'order_type'));
        }
        if (data_get($data, 'classify')) {
            $query = $query->where('classify', data_get($data, 'classify'));
        }
        $ret = $query->orderBy('created_at', 'desc')->paginate($limit);
        return $ret;
    }

    /**
     * 添加新的订单审核等级权限
     * @param array $data
     * @return OrderAuditClassify
     */
    public function add(array $data): OrderAuditClassify
    {
        $order = $this->model->where('order_name', data_get($data, 'order_name'))->first();
        if (!is_null($order)) {
            error("相同单据名称已经存在");
        }
        $order = $this->model->where('order_type', data_get($data, 'order_type'))->first();
        if (!is_null($order)) {
            error("相同单据类型编号已经存在");
        }
        $ret = $this->model->fill($data)->save();
        if (!$ret) {
            error("添加失败");
        }
        return $this->model;
    }

    /**
     * 根据id数组删除数据
     * @param array $data
     * @return mixed
     */
    public function delete(array $data)
    {
        $redis = Redis::connection('cache');
        $redis->del('audit_classify');
        return $this->model->whereIn('id', $data)->delete();
    }
}
