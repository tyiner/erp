<?php

namespace App\Services\Purchase\StockPlan;

use App\Models\Purchase\OrderAuditClassify;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseDetail;
use App\Models\Purchase\PurSnRelation;
use App\Models\Purchase\SnCode;
use App\Models\Stock\Location;
use App\Models\Stock\LockInventory;
use App\Services\BaseService;
use App\Services\Purchase\PurCheckService;

/**
 * Class StockPlanService
 * @package App\Services\Purchase\StockPlan
 */
class StockPlanService extends BaseService
{

    protected $model;
    protected $purchaseDetail;
    protected $purSn;
    protected $snCode;
    protected $purCheckService;
    protected $lockInventory;
    protected $classifyAudit;
    protected $locationModel;

    public function __construct(
        Purchase $model,
        PurchaseDetail $purchaseDetail,
        PurSnRelation $purSn,
        SnCode $snCode,
        PurCheckService $purCheckService,
        LockInventory $lockInventory,
        OrderAuditClassify $classifyAudit,
        Location $locationModel
    ) {
        $this->model = $model;
        $this->purchaseDetail = $purchaseDetail;
        $this->purSn = $purSn;
        $this->snCode = $snCode;
        $this->purCheckService = $purCheckService;
        $this->lockInventory = $lockInventory;
        $this->classifyAudit = $classifyAudit;
        $this->locationModel = $locationModel;
    }

    /**
     * 获取备货计划单详情
     *
     * @param int $id
     * @return mixed
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
        $usableCompany = $this->getStorageNumByCompanyId($companyId);
        $lockNum = $this->getLockNumByCompanyId($companyId);
        $ret = $query->first();
        $detail = $item = [];
        $type = PURCHASE_STOCK_SHIP;
        $shipInfo = $this->getStoreInfo([$id], $type);//获取发货详情
        if (!empty($ret)) {
            $supplier = $this->getSupplier(data_get($ret, 'supplier_id'));
            $department = $this->getDepartment(data_get($ret, 'department_id'));
            $subCompany = $this->getCompany(data_get($ret, 'sub_company_id'));
            $location = $this->getLocation(data_get($ret, 'location_id'));
            $detail['id'] = data_get($ret, 'id'); //单据id
            $detail['audit_classify'] = $this->getAuditClassify(data_get($ret, 'type'));
            $detail['no'] = data_get($ret, 'no'); //采购单号
            $detail['source_id'] = data_get($ret, 'source_id'); //源数据id
            $detail['location_id'] = data_get($ret, 'location_id'); //仓库id
            $detail['location_name'] = data_get($location, 'name'); //仓库名称
            $parent = $this->model->where('id', data_get($ret, 'parent_id'))->select('no')->first();
            $detail['parent_no'] = $parent ? data_get($parent, 'no') : '';
            $detail['created_at'] = data_get($ret, 'created_at')->format("Y-m-d H:i:s");
            $detail['tax'] = data_get($ret, 'tax');
            $detail['type'] = data_get($ret, 'type');
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
            $detailIds = data_get($ret, 'detail')->pluck('id');
            $snInfo = [];
            if (!empty($detailIds)) {
                $relations = $this->purSn->whereIn('purchase_detail_id', $detailIds)->select('sn_id')->get();
                if (!empty($relations)) {
                    $snIds = $relations->pluck('sn_id')->flatten()->toArray();
                    $snInfo = $this->snCode->whereIn('id', $snIds)->get()->groupBy(['goods_no', 'box'])->toArray();
                }
            }
            foreach (data_get($ret, 'detail') as $v) {
                $item['id'] = data_get($v, 'id');
                $goods_no = $item['goods_no'] = data_get($v, 'goods_no');
                $goods = $this->getGoodsByNo(data_get($v, 'goods_no'));
                if (!empty($snInfo)) {
                    $sns = data_get($snInfo, $goods_no, []);
                    $serials = [];
                    foreach ($sns as $box => $singles) {
                        if (PACKAGE_NUM == count($singles)) {
                            $serials[] = $box;
                        } else {
                            $serial = array_column($singles, 'sn');
                            $serials = array_merge_recursive_distinct($serials, $serial);
                        }
                    }
                    $item['serials'] = $serials;
                } else {
                    $item['serials'] = [];
                }
                $item['goods_name'] = data_get($goods, 'name');
                $item['is_softWare'] = data_get($goods, 'is_software');
                $item['classify'] = data_get($goods, 'classify');
                $item['attribute'] = data_get($goods, 'attribute');
                $item['existing_num'] = data_get($usableCompany, $goods_no, 0);
                $item['usable_num'] = data_get($usableCompany, $goods_no, 0) - data_get($lockNum, $goods_no, 0);
                $item['unship_num'] = data_get($v, 'num')
                    - data_get($shipInfo, $id . '.' . data_get($v, 'goods_no'), 0); //未发货数量
                $item['total_num'] = data_get($v, 'total_num');
                $item['num'] = data_get($v, 'num');
                $item['source_id'] = data_get($v, 'source_id');
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
     * 根据备货计划订单id获取当前订单出库入库情况
     * @param array $purchaseOrderIds
     * @param int $type
     * @return array
     */
    private function getStoreInfo(array $purchaseOrderIds, $type = PURCHASE_STOCK_OUT): array
    {
        $query = $this->purchaseDetail
            ->whereIn('source_id', $purchaseOrderIds)
            ->where('type', $type);
        if (PURCHASE_STOCK_OUT == $type) {
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
