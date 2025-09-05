<?php

namespace App\Services\Purchase;

use App\Models\Purchase\Company;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Class CompanyService
 *
 * @package App\Services\Purchase
 */
class CompanyService extends BaseService
{
    protected $model;

    public function __construct(Company $company)
    {
        $this->model = $company;
    }

    /**
     * @param array $data
     * @return Company
     */
    public function create(array $data): Company
    {
        $this->model->fill($data);
        $this->model->save();
        return $this->model;
    }

    /**
     * 获取仓库列表
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function getLocationList(array $data): LengthAwarePaginator
    {
        $limit = data_get($data, 'limit', 20);
        $query = $this->model;
        if (!isAdmin()) {
            //$data['company_ids'] = getUsableCompany(data_get($data, 'company_ids', []));
            $current = $this->getCurrentUser();
            $data['company_ids'] = [data_get($current, 'company_id')];
            $query = $query->whereIn('id', $data['company_ids']);
        } elseif (data_get($data, 'company_ids')) {
            $query = $query->whereIn('id', $data['company_ids']);
        }
        if (data_get($data, 'company_name')) {
            $query = $query->where('name', 'like', '%' . data_get($data, 'company_name') . '%');
        }
        $ret = $query->with('location')->paginate($limit);
        if ($ret->items()) {
            $items = $item = [];
            foreach ($ret->items() as $single) {
                $item['company_id'] = data_get($single, 'id');
                $item['company_name'] = data_get($single, 'name');
                $item['company_no'] = data_get($single, 'company_no');
                $item['status'] = data_get($single, 'status');
                $item['created_at'] = date("Y-m-d H:i:s", strtotime($single['created_at']));
                $locations = $location = [];
                if (data_get($single, 'location')) {
                    foreach (data_get($single, 'location') as $value) {
                        $location['id'] = data_get($value, 'id');
                        $location['location_name'] = data_get($value, 'name');
                        $location['created_at'] = date("Y-m-d H:i:s", strtotime($value['created_at']));
                        $location['location_no'] = data_get($value, 'location_no');
                        $location['link_user'] = data_get($value, 'link_user');
                        $location['link_phone'] = data_get($value, 'link_phone');
                        $location['address'] = data_get($value, 'address');
                        $location['address_detail'] = data_get($value, 'address_detail');
                        $location['status'] = data_get($value, 'status');
                        $location['remark'] = data_get($value, 'remark');
                        $locations[] = $location;
                    }
                }
                $item['locations'] = $locations;
                $items[] = $item;
            }
            $ret->setCollection(collect($items));
        }
        return $ret;
    }

    /**
     * 按条件获取公司
     *
     * @param array $data
     * @return mixed
     */
    public function getByCondition(array $data)
    {
        $query = $this->model;
        if (data_get($data, 'name')) {
            $query = $query->where('name', data_get($data, 'name'));
        }
        if (data_get($data, 'company_no')) {
            $query = $query->where('company_no', data_get($data, 'company_no'));
        }
        return $query->first();
    }

    /**
     * 分页获取公司
     *
     * @param array $data
     * @return mixed
     */
    public function getList(array $data)
    {
        /*if (!isAdmin()) {
            $current = $this->getCurrentUser();
            $company_id = data_get($current, 'company_id');
            $data['ids'] = [$company_id];
        }*/
        $limit = data_get($data, 'limit', 20);
        $query = $this->model;
        if (data_get($data, 'ids')) {
            $query = $query->whereIn('id', $data['ids']);
        }
        if (data_get($data, 'name')) {
            $query = $query->where('name', 'like', '%' . $data['name'] . '%');
        }
        if (data_get($data, 'company_no')) {
            $query = $query->where('company_no', 'like', '%' . $data['company_no'] . '%');
        }
        if (!is_null(data_get($data, 'status'))) {
            $query = $query->where('status', $data['status']);
        }
        return $query->orderBy('id')->paginate($limit);
    }

    /**
     * 更新公司名称
     *
     * @param array $data
     * @return bool
     */
    public function update(array $data): bool
    {
        $redis = Redis::connection('cache');
        $redis->del(data_get($data, 'id') . '_id_company');
        return $this->model->where('id', data_get($data, 'id'))->update($data);
    }

    /**
     * 批量删除公司
     *
     * @param array $ids
     * @return int
     */
    public function delete(array $ids): int
    {
        $companies = $this->model->where('id', $ids)->get();
        if ($companies->count() == 0) {
            error("不存在的删除数据");
        }
        $redis = Redis::connection('cache');
        foreach ($companies as $company) {
            $id = data_get($company, 'id');
            $redis->del($id . '_id_company');
        }
        return $this->model->whereIn('id', $ids)->delete();
    }
}
