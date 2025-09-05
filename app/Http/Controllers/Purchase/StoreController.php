<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Services\Purchase\PurchaseDetailService;
use Illuminate\Http\Request;

/**
 * Class StoreController
 * @package App\Http\Controllers\Purchase
 */
class StoreController extends Controller
{
    private $purchaseDetailService;

    public function __construct(PurchaseDetailService $purchaseDetailService)
    {
        $this->purchaseDetailService = $purchaseDetailService;
    }

    /**
     * 按条件获取总公司库存
     * @param Request $request
     */
    public function getInv(Request $request)
    {
        $rules = [
            'limit' => 'int',
            'page' => 'int',
            'goods_no' => 'string',
            'company_ids' => 'string',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        if (data_get($data, 'goods_no')) {
            $data['goods_no'] = explode(',', data_get($data, 'goods_no'));
        }
        if (data_get($data, 'company_ids')) {
            $data['company_ids'] = explode(',', data_get($data, 'company_ids'));
        }
        $ret = $this->purchaseDetailService->getInvByCompany($data);
        success($ret);
    }

    /**
     * 根据仓库获取现存量
     * @param Request $request
     */
    public function getLocationInv(Request $request)
    {
        $rules = [
            'limit' => 'int',
            'page' => 'int',
            'goods_no' => 'string',
            'location_ids' => 'string',
            'location_name' => 'string',
            'company_ids' => 'string',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        if (data_get($data, 'goods_no')) {
            $data['goods_no'] = explode(',', data_get($data, 'goods_no'));
        }
        if (data_get($data, 'location_ids')) {
            $data['location_ids'] = explode(',', data_get($data, 'location_ids'));
        }
        if (data_get($data, 'company_ids')) {
            $data['company_ids'] = explode(',', data_get($data, 'company_ids'));
        }
        $ret = $this->purchaseDetailService->getInvByLocation($data);
        success($ret);
    }
}
