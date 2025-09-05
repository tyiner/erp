<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Services\Stock\StockShipService;
use Illuminate\Http\Request;

/**
 * 备货发货单
 * Class StockShipController
 * @package App\Http\Controllers\Stock
 */
class StockShipController extends Controller
{
    private $service;

    public function __construct(StockShipService $service)
    {
        $this->service = $service;
    }

    /**
     * 获取详情列表
     * @param Request $request
     */
    public function getDetailList(Request $request)
    {
        $rule = [
            'type' => 'required|int',
            'limit' => 'int',
            'page' => 'int',
            'begin_at' => 'string',
            'end_at' => 'string',
            'no' => 'string',
            'goods_no' => 'int',
            'status' => 'string',
            'check_status' => 'string',
            'location_ids' => 'string',
            'company_id' => 'int',
        ];
        $this->handleValidateRequest($request, $rule);
        $data = $request->only(
            [
                'type',
                'limit',
                'page',
                'begin_at',
                'end_at',
                'no',
                'goods_no',
                'status',
                'check_status',
                'location_ids',
                'company_id'
            ]
        );
        if (data_get($data, 'status')) {
            $data['status'] = explode(",", $data['status']);
        }
        if (data_get($data, 'check_status')) {
            $data['check_status'] = explode(",", $data['check_status']);
        }
        if (data_get($data, 'location_ids')) {
            $data['location_ids'] = explode(",", $data['location_ids']);
        }
        success($this->service->getDetailList($data));
    }
}
