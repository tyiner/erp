<?php

namespace App\Services\Stock;

use App\Models\Purchase\Company;
use App\Models\Stock\Location;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Class LocationService
 * @package App\Services\Stock
 */
class LocationService extends BaseService
{
    private $model;
    private $company;

    public function __construct(Location $model, Company $company)
    {
        $this->model = $model;
        $this->company = $company;
    }

    /**
     * @param array $data
     * @return Collection
     */
    public function getByConds(array $data): Collection
    {
        $query = $this->model;
        if (data_get($data, 'id')) {
            $query = $query->where('id', data_get($data, 'id'));
        }
        if (data_get($data, 'name')) {
            $query = $query->where('name', data_get($data, 'name'));
        }
        if (data_get($data, 'location_no')) {
            $query = $query->where($data, 'location_no');
        }
        return $query->get();
    }

    /**
     * 新建仓库
     * @param array $data
     * @return Location
     */
    public function save(array $data): Location
    {
        DB::beginTransaction();
        try {
            $ret = $this->model->fill($data)->save();
            if ($ret) {
                DB::commit();
            } else {
                DB::rollBack();
                error("仓库添加失败");
            }
        } catch (\Exception $e) {
            DB::rollBack();
            error("仓库添加失败");
        }
        return $this->model;
    }

    /**
     * 更新仓库
     * @param array $data
     * @return mixed
     */
    public function update(array $data)
    {
        $ret = $this->getByConds(['id' => data_get($data, 'id')]);
        $redis = Redis::connection('cache');
        $keys[] = data_get($data, 'id') . '_id_location';
        $keys[] = data_get($ret->first(), 'location_no') . '_location';
        $redis->del($keys);
        if ($ret->count() == 0) {
            error("仓库不存在");
        }
        $ret = $this->getByConds(['name' => data_get($data, 'name')]);
        if ($ret->count() > 0 && $ret->first()->id != data_get($data, 'id')) {
            error("仓库名已存在");
        }
        $ret = $this->getByConds(['location_no' => data_get($data, 'location_no')]);
        if ($ret->count() > 0 && $ret->first()->id != data_get($data, 'id')) {
            error("仓库序号已存在");
        }
        return $this->model->where('id', data_get($data, 'id'))->update($data);
    }

    /**
     * 删除仓库
     * @param array $data
     * @return mixed
     */
    public function delete(array $data)
    {
        $ret = $this->getByConds(['id' => data_get($data, 'id')]);
        if ($ret->count() > 0) {
            foreach ($ret as $value) {
                $keys = [];
                $redis = Redis::connection('cache');
                $keys[] = data_get($value, 'id') . '_id_location';
                $keys[] = data_get($value, 'location_no') . '_location';
                $redis->del($keys);
            }
        }
        return $this->model->whereIn('id', $data)->delete();
    }

    /**
     * 获取仓库列表
     * @param array $data
     * @return mixed
     */
    public function getList(array $data)
    {
        $limit = data_get($data, 'limit', 20);
        $query = $this->model;
        if (!isAdmin()) {
            /*$ids = getUsableLocation(data_get($data, 'ids', []));
            $query = $query->whereIn('id', $ids);*/
            $current = $this->getCurrentUser();
            $companyId = data_get($current, 'company_id');
            $companyNo = data_get($this->getCompany($companyId), 'no');
            if ('GS00001' != $companyNo) {
                $companyId = data_get($current, 'company_id');
            } elseif (data_get($data, 'company_no')) {
                $company = $this->company->where('company_no', data_get($data, 'company_no'))->first();
                $companyId = data_get($company, 'id');
                if (empty($companyId)) {
                    return ['data' => [], 'current_page' => 1, 'total' => 0];
                }
            }
            $query = $query->where('company_id', $companyId);
        }
        if (data_get($data, 'ids')) {
            $query = $query->whereIn('id', $data['ids']);
        }
        if (data_get($data, 'name')) {
            $query = $query->where('name', 'like', '%' . $data['name'] . '%');
        }
        if (data_get($data, 'location_no')) {
            $query = $query->where('location_no', $data['location_no']);
        }
        if (!is_null(data_get($data, 'status'))) {
            $query = $query->where('status', $data['status']);
        }
        $ret = $query->orderBy('created_at', 'desc')->paginate($limit);
        if (!empty($ret->items())) {
            $items = $item = [];
            foreach ($ret->items() as $value) {
                $item['id'] = data_get($value, 'id');
                $item['name'] = data_get($value, 'name');
                $item['location_no'] = data_get($value, 'location_no');
                $item['company_id'] = data_get($value, 'company_id');
                $company = $this->getCompany($item['company_id']);
                $item['company_name'] = data_get($company, 'name');
                $item['link_user'] = data_get($value, 'link_user');
                $item['link_phone'] = data_get($value, 'link_phone');
                $item['status'] = data_get($value, 'status');
                $item['type'] = data_get($value, 'type');
                $item['address'] = data_get($value, 'address');
                $item['address_detail'] = data_get($value, 'address_detail');
                $item['remark'] = data_get($value, 'remark');
                $items[] = $item;
            }
            $ret->setCollection(collect($items));
        }
        return $ret;
    }
}
