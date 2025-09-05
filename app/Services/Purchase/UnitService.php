<?php

namespace App\Services\Purchase;

use App\Models\Purchase\Unit;
use App\Services\BaseService;

/**
 * Class UnitService
 * @package App\Services\Purchase
 */
class UnitService extends BaseService
{
    private $model;

    public function __construct(Unit $unit)
    {
        $this->model = $unit;
    }

    /**
     * 根据名称获取单位
     * @param string $name
     * @return mixed
     */
    public function getByName(string $name)
    {
        return $this->model->where('name', 'like', '%' . $name . '%')->first();
    }
}
