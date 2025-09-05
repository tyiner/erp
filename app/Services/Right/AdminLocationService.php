<?php

namespace App\Services\Right;

use App\Models\Right\AdminLocation;
use App\Models\Stock\Location;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

class AdminLocationService extends BaseService
{
    private $model;
    private $location;

    public function __construct(AdminLocation $model, Location $location)
    {
        $this->model = $model;
        $this->location = $location;
    }

    /**
     * 添加用户仓库权限
     * @param array $data
     * @return bool
     */
    public function add(array $data)
    {
        $location_ids = data_get($data, 'location_ids');
        $user = data_get($data, 'user_id');
        $ret = $this->model->where('admin_id', $user)->first();
        if (!is_null($ret)) {
            error("当前用户权限已存在");
        }
        foreach ($location_ids as $item) {
            $items[] = [
                'location_id' => $item,
                'admin_id' => $user
            ];
        }
        try {
            return $this->model->addAll($items);
        } catch (\Exception $e) {
            error("添加数据失败");
        }
    }

    /**
     * 更新用户仓库权限
     * @param array $data
     */
    public function update(array $data)
    {
        DB::beginTransaction();
        try {
            $this->model->where('admin_id', data_get($data, 'user_id'))->delete();
            $user = data_get($data, 'user_id');
            $location_ids = data_get($data, 'location_ids');
            foreach ($location_ids as $item) {
                $items[] = [
                    'location_id' => $item,
                    'admin_id' => $user
                ];
            }
            $ret = $this->model->addAll($items);
            if ($ret) {
                DB::commit();
                return;
            }
            DB::rollBack();
            error("更改数据失败");
        } catch (\Exception $e) {
            DB::rollBack();
            error("数据更改失败");
        }
    }

    /**
     * 根据用户获取仓库列表
     * @param int $id 用户id
     * @return array
     */
    public function getByAdminId(int $id): array
    {
        if (isAdmin()) {
            $list = $this->location->get();
        } else {
            $ids = getUsableLocation();
            $list = $this->location->whereIn('id', $ids)->get();
        }
        $items = $item = [];
        if ($list->count()) {
            foreach ($list->toArray() as $value) {
                $item['id'] = data_get($value, 'id');
                $item['name'] = data_get($value, 'name');
                $item['location_no'] = data_get($value, 'location_no');
                $items[] = $item;
            }
        }
        return $items;
    }
}
