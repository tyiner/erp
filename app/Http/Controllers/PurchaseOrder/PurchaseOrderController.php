<?php

namespace App\Http\Controllers\PurchaseOrder;

use App\Http\Controllers\Controller;
use App\Models\Purchase\Purchase;
use App\Services\Purchase\PurchaseDetailService;
use App\Services\Purchase\PurCheckService;
use App\Services\PurchaseOrder\PurchaseOrderService;
use Illuminate\Http\Request;

/**
 * Class PurchaseOrderController
 * @package App\Http\Controllers\PurchaseOrder
 */
class PurchaseOrderController extends Controller
{
    private $service;
    private $purchaseDetailService;
    private $purCheckService;

    private $amountType = [
        PURCHASE_OTHER_IN,
        PURCHASE_STORE_IN,
        PURCHASE_OTHER_OUT,
        PURCHASE_TRANSFER,
        PURCHASE_SALE_OUT,
        PURCHASE_STOCK_OUT,
        PURCHASE_STOCK_BACK,
    ];

    public function __construct(
        PurchaseOrderService $service,
        PurchaseDetailService $purchaseDetailService,
        PurCheckService $purCheckService
    ) {
        $this->service = $service;
        $this->purCheckService = $purCheckService;
        $this->purchaseDetailService = $purchaseDetailService;
    }

    /**
     * 获取采购单明细表
     *
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
                'location_ids'
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

    /**
     * 根据id获取采购订单详情
     * @param Request $request
     */
    public function getPurchaseOrder(Request $request)
    {
        $rules = [
            'id' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $id = $request->input('id');
        success($this->service->getById($id));
    }

    /**
     * 修改单据开启关闭状态
     * @param Request $request
     */
    public function changeStatus(Request $request)
    {
        $rules = [
            'id' => 'required|int',
            'status' => 'required|int|in:-1,1',
        ];
        $msg = [
            'id.required' => '采购订单id不能为空',
            'status.in' => '单据状态必须为-1或1',
        ];
        $this->handleValidateRequest($request, $rules, $msg);
        $data = $request->only(['id', 'status']);
        $ret = $this->service->changeStatus($data);
        if ($ret) {
            success("单据状态修改成功");
        }
        error("单据状态修改失败");
    }
}
