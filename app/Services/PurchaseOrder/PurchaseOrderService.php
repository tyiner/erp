<?php

namespace App\Services\PurchaseOrder;

use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseDetail;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Class PurchaseOrderService
 * @package App\Services\PurchaseOrder
 */
class PurchaseOrderService extends BaseService
{
    private $model;
    protected $purchaseDetail;

    public function __construct(
        Purchase $model,
        PurchaseDetail $purchaseDetail
    ) {
        $this->model = $model;
        $this->purchaseDetail = $purchaseDetail;
    }

    /**
     * 获取采购订单列表
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function getDetailList(array $data): LengthAwarePaginator
    {
        $type = data_get($data, 'type');
        $audit = $this->getAuditClassify($type);
        $limit = data_get($data, 'limit');
        $query = DB::table('purchase_detail')->join(
            'purchases',
            function ($join) use ($type) {
                $join->on('purchase_detail.purchase_id', '=', 'purchases.id')
                    ->where('purchases.type', $type)
                    ->whereNull('purchases.deleted_at')
                    ->whereNull('purchase_detail.deleted_at');
            }
        );
        if (!isAdmin()) {
            /*$locationIds = getUsableLocation(data_get($data, 'location_ids', []));
            $query = $query->whereIn('purchases.location_id', $locationIds);*/
            $current = $this->getCurrentUser();
            $companyId = data_get($current, 'company_id');
            $query = $query->where('purchases.company_id', $companyId);
        } elseif (data_get($data, 'location_ids')) {
            $query = $query->whereIn('purchases.location_id', data_get($data, 'location_ids'));
        }
        if (!is_null(data_get($data, 'status'))) {
            $query = $query->whereIn('purchases.status', data_get($data, 'status'));
        }
        if (!is_null(data_get($data, 'check_status'))) {
            $query = $query->whereIn('purchases.checked', data_get($data, 'check_status'));
        }
        if (data_get($data, 'no')) {
            $query = $query->where('purchases.no', data_get($data, 'no'));
        }
        if (data_get($data, 'goods_no')) {
            $query = $query->where('purchase_detail.goods_no', data_get($data, 'goods_no'));
        }
        if (data_get($data, 'begin_at')) {
            $query = $query->where('purchases.order_time', '>=', data_get($data, 'begin_at'));
        }
        if (data_get($data, 'end_at')) {
            $query = $query->where('purchases.order_time', '<=', data_get($data, 'end_at'));
        }
        $ret = $query->
        select(
            [
                '*',
                'purchases.remark as purchase_remark',
                'purchase_detail.remark as detail_remark'
            ]
        )->orderByDesc('purchases.order_time')->paginate($limit);
        if (!empty($ret->items())) {
            $purchaseOrderIds = array_unique(array_column($ret->items(), 'id'));
            $type = PURCHASE_STORE_IN;
            $storeInfo = $this->getStoreInfo($purchaseOrderIds, $type);
            $items = $item = [];
            foreach ($ret->items() as $value) {
                $item['audit_classify'] = $audit;
                $item['id'] = data_get($value, 'purchase_id');
                $item['no'] = data_get($value, 'no');
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                $supplier = $this->getSupplier(data_get($value, 'supplier_id'));
                $department = $this->getDepartment(data_get($value, 'department_id'));
                $item['attribute'] = data_get($value, 'attribute');
                $item['customer'] = data_get($value, 'customer');
                $item['location_response'] = data_get($value, 'location_response');
                $item['department_id'] = data_get($value, 'department_id');
                $item['department_name'] = data_get($department, 'name');
                $item['supplier_id'] = data_get($value, 'supplier_id');
                $item['supplier_name'] = data_get($supplier, 'name');
                $item['plan_delivery_date'] = data_get($value, 'plan_delivery_date');
                $item['price'] = data_get($value, 'price');
                $item['total_num'] = data_get($value, 'total_num');
                $item['num'] = data_get($value, 'num');
                if (isset($storeInfo[data_get($value, 'id')][data_get($value, 'goods_no')])) {
                    $item['arrival_num'] = $storeInfo[data_get($value, 'id')][data_get($value, 'goods_no')];
                } else {
                    $item['arrival_num'] = 0;
                }
                $item['unit'] = data_get($value, 'unit');
                $item['goods_no'] = data_get($value, 'goods_no');
                $item['status'] = data_get($value, 'status');
                $item['goods_name'] = data_get($goods, 'name');
                $item['user'] = data_get($value, 'user');
                $item['checked'] = data_get($value, 'checked');
                $item['checked_user'] = json_decode(data_get($value, 'checked_user'));
                $item['tax'] = data_get($value, 'tax');
                $item['created_at'] = data_get($value, 'created_at');
                $item['order_time'] = data_get($value, 'order_time');
                $item['purchase_remark'] = data_get($value, 'purchase_remark');
                $item['detail_remark'] = data_get($value, 'detail_remark');
                $items[] = $item;
            }
            $ret->setCollection(collect($items));
        }
        return $ret;
    }

    /**
     * 根据id获取采购订单详情
     * @param int $id
     * @return array
     */
    public function getById(int $id): array
    {
        $query = $this->model->with('detail')->where('id', $id);
        $current = $this->getCurrentUser();
        $companyId = data_get($current, 'company_id');
        if (!isAdmin()) {
            /*$location_ids = getUsableLocation([]);
            $query = $query->whereIn('location_id', $location_ids);*/
            $query = $query->where('purchases.company_id', $companyId);
        }
        $usable = $this->getLockNumByCompanyId($companyId);
        $ret = $query->first();
        $detail = $item = [];
        if (!empty($ret)) {
            $supplier = $this->getSupplier(data_get($ret, 'supplier_id'));
            $department = $this->getDepartment(data_get($ret, 'department_id'));
            $subCompany = $this->getCompany(data_get($ret, 'sub_company_id'));
            $location = $this->getLocation(data_get($ret, 'location_id'));
            $detail['id'] = data_get($ret, 'id'); //单据id
            $detail['no'] = data_get($ret, 'no'); //采购单号
            $detail['location_id'] = data_get($ret, 'location_id'); //仓库id
            $detail['location_name'] = data_get($location, 'name'); //仓库名称
            $parent = $this->model->where('id', data_get($ret, 'parent_id'))->select('no')->first();
            $detail['parent_no'] = $parent ? data_get($parent, 'no') : '';
            $detail['created_at'] = data_get($ret, 'created_at')->format("Y-m-d H:i:s");
            $detail['tax'] = data_get($ret, 'tax');
            $detail['type'] = data_get($ret, 'type');
            $detail['audit_classify'] = $this->getAuditClassify($detail['type']);
            $detail['customer'] = data_get($ret, 'customer');
            $detail['purchase_remark'] = data_get($ret, 'remark');
            $detail['purchase_type'] = data_get($ret, 'purchase_type');
            $detail['supplier_name'] = data_get($supplier, 'name');
            $detail['supplier_id'] = data_get($ret, 'supplier_id');
            $detail['department_name'] = data_get($department, 'name');
            $detail['sub_company_name'] = data_get($subCompany, 'name');
            $detail['sub_company_id'] = data_get($subCompany, 'id');
            $detail['department_id'] = data_get($ret, 'department_id');
            $detail['remark'] = data_get($ret, 'remark');
            $detail['order_time'] = data_get($ret, 'order_time');
            $detail['checked'] = data_get($ret, 'checked');
            $detail['user'] = data_get($ret, 'user');
            $checked_info = data_get($ret, 'checked_user');
            $detail['checked_info'] = json_decode($checked_info, true);
            $type = PURCHASE_ARRIVAL;
            $shipInfo = $this->getStoreInfo([$id], $type);//获取发货详情
            foreach (data_get($ret, 'detail') as $v) {
                $item['id'] = data_get($v, 'id');
                $goods = $this->getGoodsByNo(data_get($v, 'goods_no'));
                $item['existing_num'] = data_get($usable, data_get($v, 'goods_no'), 0);
                $item['goods_name'] = data_get($goods, 'name');
                $item['is_software'] = data_get($goods, 'is_software');
                $item['attribute'] = data_get($goods, 'attribute');
                $item['goods_no'] = data_get($v, 'goods_no');
                $item['total_num'] = data_get($v, 'total_num');
                $item['num'] = data_get($v, 'num');
                $item['unship_num'] = data_get($v, 'num') -
                    data_get($shipInfo, $id . '.' . data_get($v, 'goods_no'), 0); //未入库数量
                $item['price'] = data_get($v, 'price');
                $item['plan_delivery_date'] = data_get($v, 'plan_delivery_date');
                $item['unit_name'] = data_get($v, 'unit');
                $item['detail_remark'] = data_get($v, 'remark');
                $detail['detail'][] = $item;
            }
        }
        return $detail;
    }

    /**
     * 更改采购订单状态
     * @param array $data
     * @return mixed
     */
    public function changeStatus(array $data)
    {
        $id = data_get($data, 'id');
        $purchase = $this->model->where('id', $id)->first();
        if (is_null($purchase)) {
            error("采购订单不存在");
        }
        if (data_get($purchase, 'status') == $data['status']) {
            error("采购订单状态没有改变");
        }
        $purchase->status = $data['status'];
        return $purchase->save();
    }

    /**
     * 根据采购订单id获取当前订单出库入库情况
     * @param array $purchaseOrderIds
     * @param int $type
     * @return array
     */
    private function getStoreInfo(array $purchaseOrderIds, $type = PURCHASE_STORE_IN): array
    {
        $query = $this->purchaseDetail
            ->whereIn('source_id', $purchaseOrderIds)
            ->where('type', $type);
        if (PURCHASE_STORE_IN == $type) {
            $query = $query->where('finished', 1);
        }
        $purchaseDetails = $query->get([
            'source_id',
            'goods_no',
            'location_id',
            'total_num',
            'num'
        ])->groupBy(['source_id', 'goods_no']);
        if (empty($purchaseDetails->all())) {
            return [];
        }
        $items = [];
        foreach ($purchaseDetails as $k => $v) {
            foreach ($v as $key => $value) {
                if (isset($items[$k][$key])) {
                    $items[$k][$key] += $value->sum('num');
                } else {
                    $items[$k][$key] = $value->sum('num');
                }
            }
        }
        return $items;
    }
}
