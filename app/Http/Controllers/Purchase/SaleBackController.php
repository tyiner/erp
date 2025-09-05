<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Services\Purchase\CheckCompleteService;
use App\Services\Purchase\SaleBackService;
use Illuminate\Http\Request;

/**
 * Class SaleBackController
 * @package App\Http\Controllers\Purchase
 */
class SaleBackController extends Controller
{
    protected $service;
    protected $checkCompleteService;

    public function __construct(
        SaleBackService $service,
        CheckCompleteService $checkCompleteService
    ) {
        $this->service = $service;
        $this->checkCompleteService = $checkCompleteService;
    }

    /**
     * 新建销售退货单
     * @param Request $request
     */
    public function saleBackAdd(Request $request)
    {
        $rules = [
            'no' => 'required|string|max:45',
            'type' => 'required|int',
            'sale_type' => 'required|int',
            'department_id' => 'required|int',
            'remark' => 'string|max:255',
            'tax' => 'required|numeric',
            'location_id' => 'required|int',
            'detail.*.id' => 'required|int',
            'detail.*.parent_id' => 'required|int',
            'detail.*.num' => 'required|int',
            'detail.*.remark' => 'string|max:225',
        ];

        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        $ret = $this->service->saleBackAdd($data);
        $this->checkCompleteService->check($data);
        success(['id' => data_get($ret, 'id')]);
    }

    /**
     * 根据id获取销售退货单详情
     * @param Request $request
     */
    public function getSaleBackByIds(Request $request)
    {
        $rules = [
            'ids' => 'required|string',
        ];
        $this->handleValidateRequest($request, $rules);
        $ids = $request->input('ids');
        $ids = explode(',', $ids);
        $ret = $this->service->getSaleBackByIds($ids);
        success($ret);
    }

    /**
     * 获取销售退货单列表
     * @param Request $request
     */
    public function getSaleBackList(Request $request)
    {
        $rules = [
            'limit' => 'int',
            'page' => 'int',
            'no' => 'string|max:45',
            'begin_at' => 'date_format:Y-m-d H:i:s',
            'end_at' => 'date_format:Y-m-d H:i:s',
            'goods_no' => 'int',
            'check_status' => 'int',
            'sale_type' => 'int',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        $ret = $this->service->getSaleBackList($data);
        success($ret);
    }

    public function audit(Request $request)
    {
        $rules = [
            'id' => 'required|int',
            'check_status' => 'required|in:1,-1',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->only(['id', 'check_status']);
        $ret = $this->service->audit($data);
        if ($ret) {
            success();
        }
        error("审核失败");
    }

    /**
     * 更新采购退货单
     * @param Request $request
     */
    public function update(Request $request)
    {
        $rules = [
            'id' => 'required|int',
            'no' => 'required|string|max:45',
            'type' => 'required|int',
            'sale_type' => 'required|int',
            'department_id' => 'required|int',
            'tax' => 'required|numeric',
            'location_id' => 'required|int',
            'detail.*.id' => 'required|int',
            'detail.*.parent_id' => 'required|int',
            'detail.*.num' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        $ret = $this->service->update($data);
        if ($ret) {
            success("单据更新成功");
        }
        error("单据更新失败");
    }

    /**
     * 删除销售退货单
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
        error("删除销售退货单失败");
    }
}
