<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Services\Purchase\CheckCompleteService;
use App\Services\Purchase\SaleShipService;
use Illuminate\Http\Request;

/**
 * 销售发货单
 * Class SaleShipController
 * @package App\Http\Controllers\Purchase
 */
class SaleShipController extends Controller
{
    protected $service;
    protected $checkCompleteService;

    public function __construct(
        SaleShipService $service,
        CheckCompleteService $checkCompleteService
    ) {
        $this->service = $service;
        $this->checkCompleteService = $checkCompleteService;
    }

    /**
     * 新建销售发货单
     * @param Request $request
     */
    public function saleShipAdd(Request $request)
    {
        $rules = [
            'no' => 'required|string',
            'type' => 'required|int',
            'sale_type' => 'required|int',
            'location_id' => 'required|int',
            'order_time' => 'date_format:Y-m-d H:i:s',
            'remark' => 'string',
            'detail.*.id' => 'required|int',
            'detail.*.parent_id' => 'required|int',
            'detail.*.num' => 'required|int',
            'detail.*.remark' => 'string|max:255',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        $ret = $this->service->saleShipAdd($data);
        $this->checkCompleteService->check($data);
        success(['id' => data_get($ret, 'id')]);
    }

    /**
     * 销售发货单修改审核状态接口
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
        error("销售发货单审核失败");
    }

    /**
     * 删除销售发货单
     * @param Request $request
     * @throws \Exception
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
        error("销售发货单删除失败");
    }

    /**
     * 获取销售订单类别数据
     * @param Request $request
     */
    public function getSaleShipList(Request $request)
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
        $ret = $this->service->getSaleShipList($data);
        success($ret);
    }

    /**
     * 更新销售发货单
     * @param Request $request
     */
    public function update(Request $request)
    {
        $rules = [
            'id' => 'required|int',
            'no' => 'required|string',
            'type' => 'required|int',
            'sale_type' => 'required|int',
            'location_id' => 'required|int',
            'order_time' => 'date_format:Y-m-d H:i:s',
            'detail.*.id' => 'required|int',
            'detail.*.parent_id' => 'required|int',
            'detail.*.num' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        $ret = $this->service->update($data);
        if ($ret) {
            success("销售发货单更新成功");
        }
        error("更新失败");
    }

    /**
     * 根据id获取销售发货单详情
     *
     * @param Request $request
     */
    public function getSaleShipByIds(Request $request)
    {
        $rules = [
            'ids' => 'required|string',
        ];
        $this->handleValidateRequest($request, $rules);
        $ids = $request->input('ids');
        $ids = explode(',', $ids);
        $ret = $this->service->getSaleShipByIds($ids);
        success($ret);
    }
}
