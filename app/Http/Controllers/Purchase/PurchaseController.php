<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Services\Purchase\CheckCompleteService;
use App\Services\Purchase\PurCheckService;
use App\Services\Purchase\PurchaseDetailService;
use Exception;
use Illuminate\Http\Request;
use App\Services\Purchase\PurchaseService;
use Illuminate\Support\Facades\Storage;

/**
 * Class PurchaseController
 *
 * @package App\Http\Controllers\Purchase
 */
class PurchaseController extends Controller
{
    private $purchaseService;
    private $purchaseDetailService;
    private $purCheckService;
    private $checkCompleteService;

    private $amountType = [
        PURCHASE_OTHER_IN,
        PURCHASE_STORE_IN,
        PURCHASE_OTHER_OUT,
        PURCHASE_OTHER_RED,
        PURCHASE_TRANSFER,
        PURCHASE_SALE_OUT,
        PURCHASE_STOCK_OUT,
        PURCHASE_STOCK_RED,
    ];

    /**
     * PurchaseController constructor.
     *
     * @param PurchaseService $purchaseService
     * @param PurchaseDetailService $purchaseDetailService
     * @param PurCheckService $purCheckService
     * @param CheckCompleteService $checkCompleteService
     */
    public function __construct(
        PurchaseService $purchaseService,
        PurchaseDetailService $purchaseDetailService,
        PurCheckService $purCheckService,
        CheckCompleteService $checkCompleteService
    ) {
        $this->purchaseService = $purchaseService;
        $this->purchaseDetailService = $purchaseDetailService;
        $this->purCheckService = $purCheckService;
        $this->checkCompleteService = $checkCompleteService;
    }

    /**
     * 表单提交数据
     * @param Request $request
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function postExcel(Request $request)
    {
        $rules = [
            'id' => 'required|int',
            'excelFile' => 'required|file|max:1024*2',
        ];
        $msg = [
            'excelFile.max' => '上传文件大小不能超2M',
        ];
        $this->handleValidateRequest($request, $rules, $msg);
        $order = $this->purchaseService->getById($request->input('id'));
        if (empty($order)) {
            error("订单不存在");
        }
        $no = data_get($order, 'no');
        $path = storage_path() . '/app/';
        $extension = $request->file('excelFile')->extension();
        $fileName = $no . '.' . $extension;
        $filePath = $path . $fileName;
        if (!is_file($filePath)) {
            $file = $request->file('excelFile')->storeAs('/', $fileName);
        } else {
            $file = $request->file('excelFile')
                ->storeAs('/', date('YmdHis') . $fileName);
        }
        $filePath = $path . $file;
        $serials = $this->getSerialsData($filePath);
        Storage::delete($file);
        $this->purchaseDetailService->bindSerials($serials, $order);
        success(['no' => data_get($order, 'no')]);
    }

    /**
     * 新建调拨单
     * @param Request $request
     * @throws Exception
     */
    public function createTransfer(Request $request)
    {
        $rules = [
            'no' => 'required|string|max:45',
            'location_id' => 'int',
            'type' => 'required|int',
            'tax' => 'required|numeric',
            'order_time' => 'date_format:Y-m-d H:i:s',
            'business_type' => 'int',
            'purchase_type' => 'required|int',
            'detail.*.price' => 'required|numeric',
            'detail.*.goods_no' => 'required|distinct|int',
            'detail.*.attribute' => 'required|string',
            'detail.*.num' => 'required|numeric',
            'detail.*.unit' => 'required|string|max:10',
            'detail.*.plan_delivery_date' => 'string',
            'receiving_location_id' => 'required|int',
            'consignee_info.name' => 'required|string',
            'consignee_info.address' => 'required|string',
            'consignee_info.phone' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $ret = $this->purchaseService->getPurchaseByNo($request->post('no'));
        if (!is_null($ret)) {
            error("相同单号表单已经存在！");
        }
        $data = $request->all();
        $current = $this->purchaseService->getCurrentUser();
        $data['user_id'] = data_get($current, 'id');
        $data['user'] = data_get($current, 'username');
        if (!isAdmin()) {
            $data['company_id'] = data_get($current, 'company_id');
        } else {
            $data['company_id'] = 1;
        }
        if (data_get($data, 'consignee_info')) {
            $data['consignee_info'] = json_encode($data['consignee_info']);
        }
        $this->orderCheck($data);
        data_set($data, 'status', 1); //单据状态 开启：1；关闭：-1；
        data_set($data, 'checked', 1); //审核状态 未审核：1；一级审核：2；多级累加；
        data_set($data, 'checked_user', null);
        $type = data_get($data, 'type');
        if (in_array($type, $this->amountType) && !empty(data_get($data['detail'], '0.serials'))) {
            $locationId = data_get($data, 'location_id');
            $receivingLocationId = data_get($data, 'receiving_location_id');
            $detail = $this->checkSerials($data['detail'], $type, $locationId, $receivingLocationId);
            $data['detail'] = $detail;
            $ret = $this->purchaseService->addTransferDetail($data);
        } else {
            $ret = $this->purchaseService->addTransfer($data);
        }
        success(
            [
                'id' => data_get($ret, 'id')
            ]
        );
    }

    /**
     * 添加单据
     *
     * @param Request $request
     * @throws Exception
     */
    public function create(Request $request)
    {
        $rules = [
            'no' => 'required|string|max:45',
            'location_id' => 'int',
            'type' => 'required|int',
            'tax' => 'required|numeric',
            'order_time' => 'date_format:Y-m-d H:i:s',
            'business_type' => 'int',
            'purchase_type' => 'required|int',
            'department_id' => 'int',
            'receiving_location_id' => 'int',
            'detail.*.price' => 'required|numeric',
            'detail.*.goods_no' => 'required|distinct|int',
            'detail.*.attribute' => 'required|string',
            'detail.*.num' => 'required|numeric',
            'detail.*.unit' => 'required|string|max:10',
            'detail.*.plan_delivery_date' => 'string',
        ];
        if (!empty($request->input('type')) && PURCHASE_STOCK_OUT == $request->input('type')) {
            $arr = [
                'sub_company_id' => 'required|int',
                'consignee_info.name' => 'required|string',
                'consignee_info.address' => 'required|string',
                'consignee_info.phone' => 'required|int',
            ];
            $rules = array_merge_recursive_distinct(
                $rules,
                $arr
            );
        }
        $this->handleValidateRequest($request, $rules);
        $ret = $this->purchaseService->getList(['no' => $request->post('no')]);
        if ($ret->count() > 0) {
            error("相同单号表单已经存在！");
        }
        $data = $request->all();
        $current = $this->purchaseService->getCurrentUser();
        $data['user_id'] = data_get($current, 'id');
        $data['user'] = data_get($current, 'username');
        $data['company_id'] = data_get($current, 'company_id');
        if (data_get($data, 'consignee_info')) {
            $data['consignee_info'] = json_encode($data['consignee_info']);
        }
        $this->orderCheck($data);
        data_set($data, 'status', 1); //单据状态 开启：1；关闭：-1；
        data_set($data, 'checked', 1); //审核状态 未审核：1；一级审核：2；多级累加：++1；
        data_set($data, 'checked_user', null);
        $type = data_get($data, 'type');
        if (in_array($type, $this->amountType) && !empty(data_get($data['detail'], '0.serials'))) {
            /*$locationId = data_get($data, 'location_id');
            $detail = $this->checkSerials($data['detail'], $type, $locationId);
            $data['detail'] = $detail['detail'];
            */
            $data['detail'] = $this->purCheckService->getDetailSnInfo($data['detail']);
            $ret = $this->purchaseService->addDetail($data);
        } else {
            $ret = $this->purchaseService->add($data);
        }
        $this->checkCompleteService->check($data);
        success(
            [
                'id' => data_get($ret, 'id')
            ]
        );
    }

    /**
     * 删除调拨单据
     * @param Request $request
     * @throws Exception
     */
    public function deleteTransfer(Request $request)
    {
        $rules = [
            'ids' => 'required|string',
        ];
        $this->handleValidateRequest($request, $rules);
        $ids = $request->input('ids');
        $idList = explode(',', $ids);
        $ret = $this->purchaseService->delete($idList);
        if ($ret) {
            success('删除成功');
        }
        error("删除失败");
    }

    /**
     * 获取调拨单详情
     * @param Request $request
     */
    public function getTransferById(Request $request)
    {
        $rules = [
            'id' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $id = $request->input('id');
        $ret = $this->purchaseService->getTransferById($id);
        success($ret);
    }

    /**
     * 获取调拨单详情
     * @param Request $request
     */
    public function getTransferDetailList(Request $request)
    {
        $rules = [
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
            'receiving_location_id' => 'int',
        ];
        $this->handleValidateRequest($request, $rules);
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
                'receiving_location_id',
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
        success($this->purchaseService->getTransferDetailList($data));
    }

    /**
     * 更新调拨单
     * @param Request $request
     */
    public function updateTransfer(Request $request)
    {
        $rules = [
            'id' => 'required|int',
            'no' => 'required|string|max:45',
            'location_id' => 'int',
            'type' => 'required|int',
            'tax' => 'required|numeric',
            'order_time' => 'date_format:Y-m-d H:i:s',
            'business_type' => 'int',
            'purchase_type' => 'required|int',
            'detail.*.price' => 'required|numeric',
            'detail.*.goods_no' => 'required|distinct|int',
            'detail.*.attribute' => 'required|string',
            'detail.*.num' => 'required|numeric',
            'detail.*.unit' => 'required|string|max:10',
            'detail.*.plan_delivery_date' => 'string',
            'receiving_location_id' => 'required|int',
            'consignee_info.name' => 'required|string',
            'consignee_info.address' => 'required|string',
            'consignee_info.phone' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        $ret = $this->purchaseService->updateTransfer($data);
        success(
            [
                'id' => data_get($ret, 'id')
            ]
        );
    }

    /**
     * 校验商品Sn码信息
     * @param array $detail
     * @param int $type
     * @param int $locationId
     * @param null $receivingLocationId
     * @return array
     */
    private function checkSerials(array $detail, int $type, int $locationId, $receivingLocationId = null): array
    {
        $method = config('sncheck.check_map.purchase')[$type];
        if (PURCHASE_TRANSFER == $type) {
            $detail = $this->purCheckService->$method(compact('detail', 'type', 'locationId', 'receivingLocationId'));
        } else {
            $detail = $this->purCheckService->$method(compact('detail', 'type', 'locationId'));
        }
        return $detail;
    }

    /**
     * 单据校验
     *
     * @param array $data
     */
    private function orderCheck(array &$data)
    {
        $types = [
            PURCHASE_ARRIVAL,
            PURCHASE_STORE_IN,
            PURCHASE_STORE_OUT,
            PURCHASE_STOCK_RED,
        ];
        if (in_array($data['type'], $types)) {
            $ret = $this->purchaseService->getParentTypeById($data['parent_id']);
            is_null($ret) && error("引用订单不存在");
            switch ($data['type']) {
                case PURCHASE_ARRIVAL:
                    $flag = PURCHASE_PLAN;
                    break;
                case PURCHASE_STORE_IN:
                    $flag = PURCHASE_ARRIVAL;
                    break;
                case PURCHASE_STORE_OUT:
                    $flag = PURCHASE_STORE_IN;
                    break;
                case PURCHASE_STOCK_RED:
                    $flag = PURCHASE_STOCK_BACK;
                    break;
                default:
                    $flag = '';
            }
            if ($flag != data_get($ret, 'type')) {
                error("引用单据类型不正确！");
            }
        } /*else {
            data_set($data, 'parent_id', 0);
        }*/
    }

    /**
     * 获取列表
     *
     * @param Request $request
     * @return void
     */
    public function getList(Request $request)
    {
        $rules = [
            'limit' => 'int',
            'page' => 'int',
            'check_status' => 'string',
            'status' => 'string',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        if (data_get($data, 'check_status')) {
            data_set($data, 'checked', explode(',', data_get($data, 'check_status')));
        }
        if (data_get($data, 'status')) {
            $data['status'] = explode(',', $data['status']);
        }
        if (data_get($data, 'parent_id')) {
            $data['parent_id'] = explode(',', data_get($data, 'parent_id'));
        }
        success($this->purchaseService->getList($data));
    }

    /**
     * 获取采购单详情
     *
     * @param Request $request
     * @return mixed
     */
    public function getById(Request $request)
    {
        $id = $request->get('id');
        success($this->purchaseService->getById($id));
    }

    /**
     * 更新采购单
     *
     * @param Request $request
     * @throws Exception
     */
    public function update(Request $request)
    {
        $rules = [
            'id' => 'required|int',
            'type' => 'required|int',
            'tax' => 'required|numeric',
            'location_id' => 'int',
            'detail.*.price' => 'required|numeric',
            'detail.*.goods_no' => 'required|distinct|int',
            'detail.*.attribute' => 'required|string',
            'detail.*.num' => 'required|int|min:1',
            'detail.*.unit' => 'required|string|max:10',
            'detail.*.plan_delivery_date' => 'string',
        ];
        $this->handleValidateRequest($request, $rules);
        $ret = $this->purchaseService->getList(['parent_id' => array_values($request->only('id'))]);
        if ($ret->count() > 0) {
            error('单据已经被关联');
        }
        $data = $request->except(['no', 'parent_id']);
        $user = $this->purchaseService->getCurrentUser();
        $data['user_id'] = data_get($user, 'id');
        $data['user'] = data_get($user, 'username');
        $detail = $this->purchaseService->getById(data_get($data, 'id'));
        if (is_array(data_get($detail, 'checked_info'))) {
            error("单据已审核");
        }
        if (is_null(data_get($data, 'detail.0.serials'))) {
            $ret = $this->purchaseService->update($data);
        } else {
            $data['detail'] = $this->purCheckService->getDetailSnInfo(data_get($data, 'detail'));
            $ret = $this->purchaseService->updateWithSn($data);
        }
        if ($ret) {
            success(['id' => data_get($data, 'id')], 200, '单据修改成功');
        }
        error("单据修改失败");
    }

    /**
     * 删除采购订单
     *
     * @param Request $request
     * @throws Exception
     */
    public function delete(Request $request)
    {
        $rules = [
            'ids' => 'required|string',
        ];
        $this->handleValidateRequest($request, $rules);
        $ids = $request->input('ids');
        $idList = explode(',', $ids);
        $ret = $this->purchaseService->getList(['parent_id' => $idList]);
        if ($ret->count() > 0) {
            error('当前单据被引单，不能删除');
        }
        $this->checkCompleteService->deleteCheck($idList);
        $ret = $this->purchaseService->delete($idList);
        if ($ret) {
            success('删除成功');
        }
        error("删除失败");
    }

    /**
     * 修改单据关闭状态
     *
     * @param Request $request
     */
    public function changeStatus(Request $request)
    {
        $rules = [
            'id' => 'required|int',
            'status' => 'required|int|in:-1,1',
        ];
        $msg = [
            'id.int' => '表单类型不存在',
            'status.in' => '表单要修改的状态不存在',
        ];
        $this->handleValidateRequest($request, $rules, $msg);
        $data = $request->only(['id', 'status']);
        success($this->purchaseService->changeStatus($data));
    }

    /**
     * 修改订单一级审核状态
     *
     * @param Request $request
     */
    public function firstChecked(Request $request)
    {
        $rules = [
            'id' => 'required|int',
            'check_status' => 'required|int|in:-1,1',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->only(['id', 'check_status']);
        $ret = $this->purchaseService->firstChecked($data);
        if ($ret) {
            success($ret);
        }
        error("审核失败");
    }

    /**
     * 修改采购订单二级审核状态
     *
     * @param Request $request
     */
    public function secondChecked(Request $request)
    {
        $rules = [
            'id' => 'required|int',
            'check_status' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->only(['id', 'check_status']);
        $this->purchaseService->secondChecked($data);
    }

    /**
     * 更改单据三级审核状态
     * @param Request $request
     */
    public function thirdChecked(Request $request)
    {
        $rules = [
            'id' => 'required|int',
            'check_status' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->only(['id', 'check_status']);
        $this->purchaseService->thirdChecked($data);
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
        success($this->purchaseService->getDetailList($data));
    }
}
