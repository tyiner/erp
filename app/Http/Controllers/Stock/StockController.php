<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Services\Stock\CheckService;
use App\Services\Stock\StockService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Class StockController
 *
 * @package App\Http\Controllers\Stock
 */
class StockController extends Controller
{
    private $service;
    private $checkService;

    public function __construct(StockService $stockService, CheckService $checkService)
    {
        $this->service = $stockService;
        $this->checkService = $checkService;
    }

    /**
     * 创建备货单
     *
     * @param Request $request
     * @throws \Exception
     */
    public function create(Request $request)
    {
        $rules = [
            'company_id' => 'required|int',
            'location_id' => 'required|int',
            'user' => 'required|string|max:45',
            'type' => 'required|int',
            'no' => 'required|string',
            'supplier_id' => 'int',
            'parent_id' => 'required|int',
            'detail.*.goods_no' => 'required|int',
            'detail.*.price' => 'required|numeric',
            'detail.*.tax' => 'required|numeric',
            'detail.*.serials' => 'string',
            'detail.*.unit_id' => 'required|int',
            'detail.*.num' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $ret = $this->service->getByNo($request->only('no'));
        if (!empty($ret)) {
            error("相同单号表单已经存在！");
        }
        $types = [
            STOCK_STORE_IN,
            STOCK_STORE_OUT,
            STOCK_OTHER_IN,
            STOCK_OTHER_OUT,
            STOCK_SALE_OUT,
            STOCK_SALE_BACK,
            STOCK_TRANSFER,
        ];
        $this->checkService->checkStockParent($request->only(['type', 'parent_id']));
        $data = $request->only(
            [
                'company_id',
                'location_id',
                'user',
                'no',
                'type',
                'supplier_id',
                'attribute',
                'parent_id',
                'detail',
            ]
        );
        if (in_array($data['type'], $types)) {
            $data['detail'] = $this->checkService->checkStockUsable($data);
            $ret = $this->service->addDetail($data);
        } else {
            $detail = collect($data['detail']);
            if ($detail->count() != $detail->pluck('goods_no')->unique()->count()) {
                error('表单详情存在重复的商品');
            }
            $ret = $this->service->add($data);
        }
        if ($ret) {
            success($ret->id);
        }
    }

    /**
     * 删除单据
     *
     * @param Request $request
     */
    public function destroy(Request $request)
    {
        $rules = [
            'ids' => 'required|string'
        ];
        $this->handleValidateRequest($request, $rules);
        $ids = $request->input('ids');
        $ids = explode(',', $ids);
        $this->service->delete($ids);
        success("删除成功");
    }

    /**
     * 更新单据
     *
     * @param Request $request
     */
    public function update(Request $request)
    {
    }

    /**
     * @param Request $request
     */
    public function get(Request $request)
    {
        $rules = [
            'id' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $id = $request->input('id');
        $ret = $this->service->get($id);
        if ($ret) {
            success($ret);
        }
    }

    /**
     * 获取表单列表
     *
     * @param Request $request
     * @return LengthAwarePaginator
     */
    public function getList(Request $request): LengthAwarePaginator
    {
        $rules = [
            'limit' => 'int',
            'page' => 'int',
            'type' => 'int',
            'company_id' => 'int',
            'no' => 'string',
            'ids' => 'string',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->only(['limit', 'page', 'type', 'company_id', 'no', 'ids']);
        if (data_get($data, 'ids')) {
            $data['ids'] = explode(',' . $data['ids']);
        }
        $ret = $this->service->getList($data);
        $info = $infos = [];
        foreach ($ret->items() as $item) {
            $info['id'] = $item->id;
            $info['type'] = $item->type;
            $info['company'] = data_get($item, 'company.name');
            $info['no'] = data_get($item, 'no');
            $info['status'] = data_get($item, 'status', 0);
            $info['checked_user'] = data_get($item, 'checked_user', '');
            $info['user'] = data_get($item, 'user', '');
            $info['supplier_name'] = data_get($item, 'supplier.name');
            $info['supplier_link_name'] = data_get($item, 'supplier.link_name');
            $info['supplier_address'] = data_get($item, 'supplier.address');
            $info['supplier_phone'] = data_get($item, 'supplier.phone');
            $infos[] = $info;
        }
        $ret->setCollection(collect($infos));
        return $ret;
    }

    /**
     * 获取明细列表
     *
     * @param Request $request
     */
    public function getDetailList(Request $request)
    {
        $rules = [
            'limit' => 'int',
            'page' => 'int',
            'type' => 'int',
            'goods_no' => 'int',
            'checked' => 'int',
            'department_id' => 'int',
            'begin_at' => 'string',
            'ending_at' => 'string'
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->only(['limit', 'page', 'type', 'checked', 'goods_no', 'department_id', 'begin_at', 'end_at']);
        $list = $this->service->getDetailList($data);
    }
}
