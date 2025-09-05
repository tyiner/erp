<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Services\Purchase\CheckCompleteService;
use App\Services\Purchase\SaleCheckService;
use App\Services\Purchase\SaleOutService;
use Illuminate\Http\Request;

/**
 * Class SaleOutController
 * @package App\Http\Controllers\Purchase
 */
class SaleOutController extends Controller
{
    protected $service;
    protected $saleCheckService;
    protected $checkCompleteService;

    public function __construct(
        SaleOutService $service,
        SaleCheckService $saleCheckService,
        CheckCompleteService $checkCompleteService
    ) {
        $this->service = $service;
        $this->saleCheckService = $saleCheckService;
        $this->checkCompleteService = $checkCompleteService;
    }

    /**
     * 新建销售出库单
     *
     * @param Request $request
     */
    public function saleOutAdd(Request $request)
    {
        $rules = [
            'no' => 'required|string|max:45',
            'type' => 'required|int',
            'sale_type' => 'required|int',
            'department_id' => 'int',
            'remark' => 'string|max:255',
            'tax' => 'required|numeric',
            'location_id' => 'required|int',
            'detail.*.id' => 'required|int',
            'detail.*.parent_id' => 'required|int',
            'detail.*.num' => 'required|int',
            'detail.*.goods_no' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        if (empty(data_get($data, 'detail.0.serials'))) {
            $ret = $this->service->saleOutAdd($data);
        } else {
            $locationId = $request->input('location_id');
            $ret = $this->saleCheckService->checkSaleOut(data_get($data, 'detail'), $locationId);
            $data['purchase_detail'] = $ret;
            $ret = $this->service->saleOutAddWithSerial($data);
        }
        $this->checkCompleteService->check($data);
        success(['id' => data_get($ret, 'id')]);
    }

    /**
     * 更新销售出库单
     * @param Request $request
     */
    public function update(Request $request)
    {
        $rules = [
            'id' => 'required|int',
            'no' => 'required|string|max:45',
            'type' => 'required|int',
            'sale_type' => 'required|int',
            'department_id' => 'int',
            'tax' => 'required|numeric',
            'location_id' => 'required|int',
            'detail.*.id' => 'required|int',
            'detail.*.parent_id' => 'required|int',
            'detail.*.num' => 'required|int',
            'detail.*.goods_no' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        if (empty(data_get($data, 'detail.0.serials'))) {
            $ret = $this->service->update($data);
        } else {
            $locationId = $request->input('location_id');
            $ret = $this->saleCheckService->checkSaleOut(data_get($data, 'detail'), $locationId);
            $data['purchase_detail'] = $ret;
            $ret = $this->service->updateWithSerial($data);
        }
        if ($ret) {
            success("更新成功");
        }
        error("更新失败");
    }

    /**
     * 获取销售出库列表
     * @param Request $request
     */
    public function getSaleOutList(Request $request)
    {
        $rules = [
            'limit' => 'int',
            'page' => 'int',
            'location_id' => 'int',
            'sale_type' => 'int',
            'no' => 'string',
            'begin_at' => 'date_format:Y-m-d H:i:s',
            'end_at' => 'date_format:Y-m-d H:i:s',
            'check_status' => 'int',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        $ret = $this->service->getSaleOutList($data);
        success($ret);
    }

    /**
     * 销售出库单一级审核
     * @param Request $request
     */
    public function firstCheck(Request $request)
    {
        $rules = [
            'id' => 'required|int',
            'check_status' => 'required|in:1,-1',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        $ret = $this->service->firstCheck($data);
        if ($ret) {
            success();
        }
        error("销售出库单审核失败");
    }

    public function secondCheck(Request $request)
    {
        $rules = [
            'id' => 'required|int',
            'check_status' => 'required|in:1,-1',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        $this->service->secondCheck($data);
    }

    /**
     * 根据id获取销售出库单详情
     * @param Request $request
     */
    public function getSaleOutByIds(Request $request)
    {
        $ids = $request->input('ids');
        $ids = explode(',', $ids);
        $ret = $this->service->getSaleOutByIds($ids);
        success($ret);
    }

    /**
     * @param Request $request
     */
    public function delete(Request $request)
    {
        $rules = [
            'id' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $id = $request->input('id');
        $ret = $this->service->delete($id);
        if ($ret) {
            $this->checkCompleteService->deleteSaleCheck($id);
            success();
        }
        error("销售出库单删除失败");
    }
}
