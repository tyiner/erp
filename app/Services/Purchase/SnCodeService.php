<?php

namespace App\Services\Purchase;

use App\Services\BaseService;
use App\Models\Purchase\SnCode;
use Illuminate\Database\Eloquent\Collection;

/**
 * Class SnCodeService
 * @package App\Services\Purchase
 * @property SnCode model
 */
class SnCodeService extends BaseService
{
    private $model;

    public function __construct(SnCode $model)
    {
        $this->model = $model;
    }

    /**
     * batch add data
     * @param array $data
     * @return bool
     */
    public function addAll(array $data): bool
    {
        return $this->model->addAll($data);
    }

    /**
     * 批量获取数据
     * @param array $boxCodes
     * @return Collection
     */
    public function getBatchByBox(array $boxCodes): Collection
    {
        return $this->model->whereIn('box', $boxCodes)->get();
    }

    /**
     * 根据箱码或sn码获取商品信息
     * @param array $data
     * @return mixed
     */
    public function getGoodsInfo(array $data)
    {
        $query = $this->model;
        if (data_get($data, 'sn')) {
            $query = $query->where('sn', data_get($data, 'sn'));
        }
        if (data_get($data, 'box')) {
            $query = $query->where('box', data_get($data, 'box'));
        }
        return $query->first();
    }
}
