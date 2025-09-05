<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Services\Purchase\SaleOrderService;
use Illuminate\Http\Request;

/**
 * Class SaleOrderController
 * @package App\Http\Controllers\Purchase
 */
class SaleOrderController extends Controller
{
    private $service;

    public function __construct(SaleOrderService $service)
    {
        $this->service = $service;
    }

    /**
     * 新建销售订单
     * @param Request $request
     */
    public function saleOrderAdd(Request $request)
    {
        $rules = [
            'no' => 'required|string',
            'order_time' => 'required|date_format:Y-m-d H:i:s',
            'tax' => 'required|numeric',
            'sale_type' => 'int',
            'department_id' => 'required|int',
            'remark' => 'string',
            'detail.*.goods_no' => 'required|int',
            'detail.*.price' => 'required|numeric',
            'detail.*.num' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        if (!isset($data['status'])) {
            $data['status'] = 1;
        }
        $ret = $this->service->saleOrderAdd($data);
        success(['id' => data_get($ret, 'id')]);
    }

    /**
     * 销售订单一级审核
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
        error("审核失败");
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
     * 更新销售订单
     * @param Request $request
     */
    public function update(Request $request)
    {
        $rules = [
            'id' => 'required|int',
            'no' => 'required|string',
            'order_time' => 'required|date_format:Y-m-d H:i:s',
            'tax' => 'required|numeric',
            'sale_type' => 'int',
            'department_id' => 'required|int',
            'remark' => 'string',
            'detail.*.order_no' => 'required|max:45',
            'detail.*.platform' => 'required|string',
            'detail.*.goods_no' => 'required|int',
            'detail.*.num' => 'required|int',
            'detail.*.price' => 'required|numeric',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        $ret = $this->service->update($data);
        success(['id' => data_get($ret, 'id')]);
    }

    /**
     * 获取销售订单详情
     * @param Request $request
     */
    public function getSaleOrderByIds(Request $request)
    {
        $rules = [
            'ids' => 'required|string',
        ];
        $this->handleValidateRequest($request, $rules);
        $ids = $request->input('ids');
        $ids = explode(',', $ids);
        $ret = $this->service->getSaleOrderByIds($ids);
        success($ret);
    }

    /**
     * 获取销售列表
     * @param Request $request
     */
    public function getSaleOrderList(Request $request)
    {
        $rules = [
            'begin_at' => 'format_date:Y-m-d H:i:s',
            'end_at' => 'format_date:Y-m-d H:i:s',
            'limit' => 'int',
            'page' => 'int',
            'status' => 'int',
            'platform' => 'int',
            'order_no' => 'string|max:20',
        ];
        $this->handleValidateRequest($request, $rules);
        $conds = $request->all();
        $ret = $this->service->getSaleOrderList($conds);
        success($ret);
    }

    /**
     * 根据id删除销售订单
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
            success("销售订单删除成功");
        }
        error("销售订单删除失败");
    }
}
