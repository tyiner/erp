<?php

namespace App\Services\Stock;

use App\Models\Stock\LockInventory;
use App\Services\BaseService;

/**
 * Class LockInventoryService
 *
 * @package App\Services\Stock
 */
class LockInventoryService extends BaseService
{
    private $model;

    public function __construct(LockInventory $model)
    {
        $this->model = $model;
    }

    /**
     * 更新锁定仓数量
     *
     * @param array $data 锁定数据
     * @return LockInventory
     */
    public function update(array &$data): LockInventory
    {
        $model = $this->model->where(
            [
                'location_no' => data_get($data, 'location_no'),
                'goods_no' => data_get($data, 'goods_no')
            ]
        )->first();
        if (is_null($model)) {
            $location = $this->getLocationByNo($data['location_no']);
            if (empty($location)) {
                error("不存在的仓库编号");
            }
            $data['location_id'] = data_get($location, 'id');
            $this->model->fill($data)->save();
            $model = $this->model;
        } else {
            if (0 > data_get($model, 'lock_num') + $data['lock_num']) {
                error("非法的锁定数量");
            }
            $model->lock_num += data_get($data, 'lock_num');
            $model->save();
        }
        return $model;
    }

    /**
     * 删除锁定仓
     *
     * @param array $data
     * @return mixed
     */
    public function destroy(array $data)
    {
        return $this->model->where('location_no', data_get($data, 'location_no'))->where(
            'goods_no',
            data_get($data, 'goods_no')
        )->delete();
    }
}
