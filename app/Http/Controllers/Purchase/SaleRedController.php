<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Services\Purchase\CheckCompleteService;
use App\Services\Purchase\SaleCheckService;
use App\Services\Purchase\SaleRedService;
use Illuminate\Http\Request;

/**
 * Class SaleRedController
 * @package App\Http\Controllers\Purchase
 */
class SaleRedController extends Controller
{
    protected $service;
    protected $saleCheckService;
    protected $checkCompleteService;

    public function __construct(
        SaleRedService $service,
        SaleCheckService $saleCheckService,
        CheckCompleteService $checkCompleteService
    ) {
        $this->service = $service;
        $this->saleCheckService = $saleCheckService;
        $this->checkCompleteService = $checkCompleteService;
    }

    /**
     * 新建销售出库单红字
     * @param Request $request
     */
    public function saleRedAdd(Request $request)
    {
        $rules = [
            'no' => 'required|string|max:45',
            'type' => 'required|int',
            'sale_type' => 'required|int',
            'department_id' => 'required|int',
            'tax' => 'required|numeric',
            'location_id' => 'required|int',
            'detail.*.id' => 'required|int',
            'detail.*.parent_id' => 'required|int',
            'detail.*.num' => 'required|int',
            'detail.*.goods_no' => 'required|int',
            'detail.*.serials' => 'array',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        if (!data_get($data, 'detail.0.serials')) {
            $ret = $this->service->saleRedAdd($data);
        } else {
            $locationId = $request->input('location_id');
            $ret = $this->saleCheckService->checkSn(data_get($data, 'detail'), $locationId);
            $data['purchase_detail'] = $ret;
            $ret = $this->service->saleRedAddWithSerials($data);
        }
        $this->checkCompleteService->check($data);
        success(['id' => data_get($ret, 'id')]);
    }

    /**
     * 销售出库单红字更新接口
     * @param Request $request
     */
    public function update(Request $request)
    {
        $rules = [
            'no' => 'required|string|max:45',
            'type' => 'required|int',
            'sale_type' => 'required|int',
            'department_id' => 'required|int',
            'tax' => 'required|numeric',
            'location_id' => 'required|int',
            'detail.*.id' => 'required|int',
            'detail.*.parent_id' => 'required|int',
            'detail.*.num' => 'required|int',
            'detail.*.goods_no' => 'required|int',
            'detail.*.serials' => 'array',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        if (!data_get($data, 'detail.0.serials')) {
            $ret = $this->service->update($data);
        } else {
            $locationId = $request->input('location_id');
            $ret = $this->saleCheckService->checkSn(data_get($data, 'detail'), $locationId);
            $data['purchase_detail'] = $ret;
            $ret = $this->service->updateWithSerial($data);
        }
        if ($ret) {
            success("销售出库红字单更新成功");
        }
        error("销售出库红字单更新失败");
    }

    /**
     * 根据id获取销售出库红字详情
     * @param Request $request
     */
    public function getSaleRedByIds(Request $request)
    {
        $rules = [
            'id' => 'required|int'
        ];
        $this->handleValidateRequest($request, $rules);
        $id = $request->input('id');
        $ret = $this->service->getSaleRedByIds($id);
        success($ret);
    }

    /**
     * 审核出库单红字
     * @param Request $request
     */
    public function audit(Request $request)
    {
        $rules = [
            'id' => 'required|int',
            'check_status' => 'required|in:-1,1'
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        $ret = $this->service->audit($data);
        if ($ret) {
            success();
        }
        error("销售出库单红字审核失败");
    }

    /**
     * 获取销售出库红字列表
     * @param Request $request
     */
    public function getSaleRedList(Request $request)
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
        $ret = $this->service->getSaleRedList($data);
        success($ret);
    }

    /**
     * 根据id删除销售退货入库
     * @param Request $request
     */
    public function delete(Request $request)
    {
        $rules = [
            'id' => 'required|int'
        ];
        $this->handleValidateRequest($request, $rules);
        $id = $request->input('id');
        $ret = $this->service->delete($id);
        if ($ret) {
            $this->checkCompleteService->deleteSaleCheck($id);
            success("删除成功");
        }
        error("单据删除失败");
    }
}
