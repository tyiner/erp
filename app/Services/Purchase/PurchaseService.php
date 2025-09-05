<?php

namespace App\Services\Purchase;

use App\Models\Purchase\OrderAuditClassify;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseDetail;
use App\Models\Purchase\PurSnRelation;
use App\Models\Purchase\SnCode;
use App\Models\Stock\Location;
use App\Models\Stock\LockInventory;
use App\Services\BaseService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Class PurchaseService
 *
 * @package  App\Services\Purchase
 * @property Purchase model
 */
class PurchaseService extends BaseService
{
    protected $reduce = [
        PURCHASE_SALE_OUT,
        PURCHASE_OTHER_OUT,
        PURCHASE_STORE_OUT,
        PURCHASE_STOCK_OUT,
        PURCHASE_TRANSFER,
    ];

    protected $storeType = [
        PURCHASE_OTHER_IN,
        PURCHASE_OTHER_OUT,
        PURCHASE_OTHER_RED,
        PURCHASE_STORE_OUT,
        PURCHASE_STORE_IN,
        PURCHASE_TRANSFER,
        PURCHASE_SALE_RED,
    ];
    protected $secondStoreType = [
        PURCHASE_SALE_OUT,
        PURCHASE_TRANSFER,
        PURCHASE_STOCK_OUT,
        PURCHASE_STOCK_RED,
    ];

    protected $ship = [
        PURCHASE_STOCK_SHIP,
        PURCHASE_SALE_SHIP,
    ];

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
     * 添加调拨单详情
     *
     * @param array $data
     * @return Purchase
     */
    public function addTransferDetail(array $data): Purchase
    {
        $detail = data_get($data, 'detail');
        $locationId = data_get($data, 'location_id');
        $shipLocationStore = $this->getStorageNumByLocationId([$locationId]);
        $lockInfos = $this->lockInventory->where('location_id', $locationId)->get()->groupBy('goods_no');
        foreach ($data['detail'] as $v) {
            if (data_get($lockInfos, $v['goods_no'])) {
                $lockInfo = data_get($lockInfos, $v['goods_no'])->first();
                $store = data_get($shipLocationStore, $locationId . '.' . $v['goods_no']);
                if ($v['num'] > $store - $lockInfo->lock_num) {
                    $goods = $this->getGoodsByNo($v['goods_no']);
                    error("存货名称为：" . data_get($goods, 'name') . "的可用数量不足");
                }
            } else {
                $store = data_get($shipLocationStore, $locationId . '.' . $v['goods_no']);
                if ($v['num'] > $store) {
                    $goods = $this->getGoodsByNo($v['goods_no']);
                    error("存货名称为：" . data_get($goods, 'name') . "的可用数量不足");
                }
            }
        }
        DB::beginTransaction();
        $date = date('Y-m-d H:i:s');
        $type = data_get($data, 'type');
        try {
            $this->model->fill($data)->save();
            $purchaseId = data_get($this->model, 'id');
            foreach ($detail as $item) {
                $numInfo = ['num' => data_get($item, 'num'), 'type' => $type];
                $numInfo['reduce'] = $this->reduce;
                $info['price'] = data_get($item, 'price');
                $info['location_id'] = data_get($item, 'location_id');
                $info['goods_no'] = data_get($item, 'goods_no');
                $info['num'] = data_get($item, 'num');
                $info['type'] = $type;
                $info['total_num'] = data_get($item, 'total_num');
                $info['plan_delivery_date'] = data_get($item, 'plan_delivery_date');
                $info['attribute'] = data_get($item, 'attribute');
                $info['remark'] = data_get($item, 'remark');
                $info['unit'] = data_get($item, 'unit');
                $info['purchase_id'] = $purchaseId;
                $info['created_at'] = $info['updated_at'] = $date;
                $ret = DB::table('purchase_detail')->insertGetId($info);
                if (!$ret) {
                    DB::rollBack();
                    error("添加表格详情数据失败");
                }
                $data = array_map(
                    function ($serial) use ($ret) {
                        $info['purchase_detail_id'] = $ret;
                        $info['sn_id'] = $serial;
                        return $info;
                    },
                    data_get($item, 'ids')
                );
                $ret = $this->purSn->addAll($data);
                if (!$ret) {
                    DB::rollBack();
                    error("sn码关联信息数据添加失败");
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            error("添加数据失败");
        }
        return $this->model;
    }


    /**
     * 添加详情
     *
     * @param array $data
     * @return Purchase
     */
    public function addDetail(array $data): Purchase
    {
        $detail = data_get($data, 'detail');
        DB::beginTransaction();
        $date = date('Y-m-d H:i:s');
        $type = data_get($data, 'type');
        $location_id = data_get($data, 'location_id');
        try {
            $this->model->fill($data)->save();
            $purchaseId = data_get($this->model, 'id');
            $sourceId = data_get($data, 'source_id', 0);
            array_walk(
                $detail,
                function (&$item) use ($date, $type, $location_id, $purchaseId, $sourceId) {
                    $numInfo = ['num' => data_get($item, 'num'), 'type' => $type];
                    $numInfo['reduce'] = $this->reduce;
                    $info['price'] = data_get($item, 'price');
                    $info['location_id'] = $location_id;
                    $info['goods_no'] = data_get($item, 'goods_no');
                    $info['num'] = data_get($item, 'num');
                    $info['type'] = $type;
                    $info['source_id'] = $sourceId;
                    $info['total_num'] = $this->getTotal($numInfo);
                    $info['plan_delivery_date'] = data_get($item, 'plan_delivery_date');
                    $info['attribute'] = data_get($item, 'attribute');
                    $info['remark'] = data_get($item, 'remark');
                    $info['unit'] = data_get($item, 'unit');
                    $info['purchase_id'] = $purchaseId;
                    $info['created_at'] = $info['updated_at'] = $date;
                    $ret = DB::table('purchase_detail')->insertGetId($info);
                    if (!$ret) {
                        DB::rollBack();
                        error("添加表格详情数据失败");
                    }
                    $data = array_map(
                        function ($serial) use ($ret) {
                            $info['purchase_detail_id'] = $ret;
                            $info['sn_id'] = $serial;
                            return $info;
                        },
                        data_get($item, 'ids')
                    );
                    $ret = $this->purSn->addAll($data);
                    if (!$ret) {
                        DB::rollBack();
                        error("sn码关联信息数据添加失败");
                    }
                }
            );
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            error("添加数据失败");
        }
        return $this->model;
    }

    /**
     * 获取调拨单详情
     *
     * @param int $id
     * @return array
     */
    public function getTransferById(int $id): array
    {
        $query = $this->model->with('detail')->where('id', $id);
        $current = $this->getCurrentUser();
        $companyId = data_get($current, 'company_id');
        if (!isAdmin()) {
            /*$location_ids = getUsableLocation([]);
            $query = $query->whereIn('location_id', $location_ids);*/
            $query = $query->where('purchases.company_id', $companyId);
        }
        $locationIds = $this->locationModel->where('company_id', $companyId)->get('id')
            ->pluck('id')->flatten()->toArray();
        $storageNum = $this->getStorageNumByLocationId($locationIds);
        $lockNum = $this->getLockNumByLocationId($locationIds);
        $ret = $query->first();
        $detail = $item = [];
        if (!empty($ret)) {
            $supplier = $this->getSupplier(data_get($ret, 'supplier_id'));
            $department = $this->getDepartment(data_get($ret, 'department_id'));
            $subCompany = $this->getCompany(data_get($ret, 'sub_company_id'));
            $location = $this->getLocation(data_get($ret, 'location_id'));
            $receivingLocation = $this->getLocation(data_get($ret, 'receiving_location_id'));
            $detail['receiving_location_name'] = data_get($receivingLocation, 'name');
            $detail['receiving_location_id'] = data_get($receivingLocation, 'id');
            $detail['consignee_info'] = json_decode(data_get($ret, 'consignee_info', '[]'), true);
            $detail['id'] = data_get($ret, 'id'); //单据id
            $detail['audit_classify'] = $this->getAuditClassify(data_get($ret, 'type'));
            $detail['no'] = data_get($ret, 'no'); //采购单号
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
                if ($v['total_num'] < 0) {
                    continue;
                }
                $item['id'] = data_get($v, 'id');
                $goods_no = $item['goods_no'] = data_get($v, 'goods_no');
                $goods = $this->getGoodsByNo(data_get($v, 'goods_no'));
                if (!empty($snInfo)) {
                    $snInfo = data_get($snInfo, $goods_no);
                    $serials = [];
                    foreach ($snInfo as $box => $singles) {
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
                $item['classify'] = data_get($goods, 'classify');
                $item['is_software'] = data_get($goods, 'is_software');
                $item['goods_type'] = data_get($goods, 'goods_type');
                $item['attribute'] = data_get($v, 'attribute');
                $item['total_num'] = data_get($v, 'total_num');
                $item['num'] = data_get($v, 'num');
                $item['usable_num'] = data_get($storageNum, $ret['location_id'] . '.' . $v['goods_no'], 0)
                    - data_get($lockNum, $ret['location_id'] . '.' . $v['goods_no'], 0);
                $item['actual_num'] = data_get($storageNum, $ret['location_id'] . '.' . $v['goods_no'], 0);
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
     * 获取调拨单详情列表
     *
     * @param array $data
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getTransferDetailList(array $data): \Illuminate\Contracts\Pagination\LengthAwarePaginator
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
            $locationIds = getUsableLocation(data_get($data, 'location_ids', []));
            $query = $query->whereIn('purchases.location_id', $locationIds);
        } elseif (data_get($data, 'location_ids')) {
            $query = $query->whereIn('purchases.location_id', data_get($data, 'location_ids'));
        }
        if (data_get($data, 'receiving_location_id')) {
            $query = $query->where('purchases.receiving_location_id', data_get($data, 'receiving_location_id'));
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
        $ret = $query->where('purchase_detail.total_num', '>', 0)
            ->select(
                [
                    '*',
                    'purchases.remark as purchase_remark',
                    'purchase_detail.remark as detail_remark'
                ]
            )->orderByDesc('purchases.order_time')->paginate($limit);
        if (!empty($ret->items())) {
            $items = $item = [];
            foreach ($ret->items() as $value) {
                if (data_get($value, 'total_num') < 0) {
                    continue;
                }
                $item['id'] = data_get($value, 'purchase_id');
                $item['no'] = data_get($value, 'no');
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                $supplier = $this->getSupplier(data_get($value, 'supplier_id'));
                $department = $this->getDepartment(data_get($value, 'department_id'));
                $location = $this->getLocation(data_get($value, 'location_id'));
                $item['audit_classify'] = $audit;
                $item['attribute'] = data_get($value, 'attribute');
                $item['customer'] = data_get($value, 'customer');
                $item['status'] = data_get($value, 'status');
                $item['department_id'] = data_get($value, 'department_id');
                $item['department_name'] = data_get($department, 'name');
                $item['location_name'] = data_get($location, 'name');
                $item['location_id'] = data_get($location, 'id');
                $item['location_response'] = data_get($value, 'location_response');
                $item['receiving_location_response'] = data_get($value, 'receiving_location_response');
                $item['supplier_id'] = data_get($value, 'supplier_id');
                $item['supplier_name'] = data_get($supplier, 'name');
                $item['plan_delivery_date'] = data_get($value, 'plan_delivery_date');
                $item['price'] = data_get($value, 'price');
                $item['total_num'] = data_get($value, 'total_num');
                $item['num'] = data_get($value, 'num');
                $item['unit'] = data_get($value, 'unit');
                $item['goods_no'] = data_get($value, 'goods_no');
                $item['goods_name'] = data_get($goods, 'name');
                $item['user'] = data_get($value, 'user');
                $item['checked'] = data_get($value, 'checked');
                $item['checked_user'] = json_decode(data_get($value, 'checked_user'));
                $item['tax'] = data_get($value, 'tax');
                $item['created_at'] = data_get($value, 'created_at');
                $item['order_time'] = data_get($value, 'order_time');
                $item['purchase_remark'] = data_get($value, 'purchase_remark');
                $item['detail_remark'] = data_get($value, 'detail_remark');
                if (PURCHASE_TRANSFER == data_get($value, 'type')) {
                    $receivingLocation = $this->getLocation(data_get($value, 'receiving_location_id'));
                    data_set($item, 'receiving_location_id', data_get($receivingLocation, 'id'));
                    data_set($item, 'receiving_location_name', data_get($receivingLocation, 'name'));
                }
                $items[] = $item;
            }
            $ret->setCollection(collect($items));
        }
        return $ret;
    }

    /**
     * 添加调拨单
     *
     * @param array $data
     * @return Purchase
     */
    public function addTransfer(array $data): Purchase
    {
        $detail = data_get($data, 'detail');
        $type = data_get($data, 'type');
        $locationId = $location_id = data_get($data, 'location_id');
        $shipLocationStore = $this->getStorageNumByLocationId([$locationId]);
        $lockInfos = $this->lockInventory->where('location_id', $locationId)->get()->groupBy('goods_no');
        foreach ($data['detail'] as $v) {
            if (data_get($lockInfos, $v['goods_no'])) {
                $lockInfo = data_get($lockInfos, $v['goods_no'])->first();
                $store = data_get($shipLocationStore, $locationId . '.' . $v['goods_no']);
                if ($v['num'] > $store - $lockInfo->lock_num) {
                    $goods = $this->getGoodsByNo($v['goods_no']);
                    error("存货名称为：" . data_get($goods, 'name') . "的可用数量不足");
                }
            } else {
                $store = data_get($shipLocationStore, $locationId . '.' . $v['goods_no']);
                if ($v['num'] > $store) {
                    $goods = $this->getGoodsByNo($v['goods_no']);
                    error("存货名称为：" . data_get($goods, 'name') . "的可用数量不足");
                }
            }
        }
        DB::beginTransaction();
        try {
            $pur = $this->model->fill($data)->save();
            if (!$pur) {
                DB::rollBack();
                error("purchase");
            }
            $date = Carbon::now()->format("Y-m-d H:i:s");
            $sourceId = data_get($data, 'source_id', 0);
            array_walk(
                $detail,
                function (&$item, $k, $id) use ($type, $date, $location_id, $sourceId) {
                    $numInfo = ['num' => $item['num'], 'type' => $type];
                    $numInfo['reduce'] = $this->reduce;
                    $info['price'] = data_get($item, 'price');
                    $info['location_id'] = $location_id;
                    $info['goods_no'] = data_get($item, 'goods_no');
                    $info['num'] = data_get($item, 'num');
                    $info['type'] = $type;
                    $info['source_id'] = $sourceId;
                    $info['total_num'] = $this->getTotal($numInfo);
                    $info['plan_delivery_date'] = data_get($item, 'plan_delivery_date');
                    $info['attribute'] = data_get($item, 'attribute');
                    $info['remark'] = data_get($item, 'remark');
                    $info['unit'] = data_get($item, 'unit');
                    $info['purchase_id'] = $id;
                    $info['created_at'] = $info['updated_at'] = $date;
                    $item = $info;
                },
                $this->model->id
            );
            $ret = $this->purchaseDetail->addAll($detail);
            if (!$ret) {
                DB::rollBack();
                error("purchaseDetail数据添加失败");
            }
            if (PURCHASE_TRANSFER == $type) {
                $location_id = data_get($data, 'receiving_location_id');
                array_walk(
                    $detail,
                    function (&$item, $k, $id) use ($type, $date, $location_id, $sourceId) {
                        $info['price'] = data_get($item, 'price');
                        $info['location_id'] = $location_id;
                        $info['goods_no'] = data_get($item, 'goods_no');
                        $info['num'] = data_get($item, 'num');
                        $info['type'] = $type;
                        $info['source_id'] = $sourceId;
                        $info['total_num'] = -($item['total_num']);
                        $info['plan_delivery_date'] = data_get($item, 'plan_delivery_date');
                        $info['attribute'] = data_get($item, 'attribute');
                        $info['unit'] = data_get($item, 'unit');
                        $info['remark'] = data_get($item, 'remark');
                        $info['purchase_id'] = $id;
                        $info['created_at'] = $info['updated_at'] = $date;
                        $item = $info;
                    },
                    $this->model->id
                );
                $ret1 = $this->purchaseDetail->addAll($detail);
                if (!$ret1) {
                    DB::rollBack();
                    error("调拨入库purchaseDetail数据添加失败");
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            error("数据添加失败");
        }
        return $this->model;
    }

    /**
     * 添加采购单
     *
     * @param array $data
     * @return mixed
     * @throws \Exception
     */
    public function add(array $data): Purchase
    {
        $detail = data_get($data, 'detail');
        $type = data_get($data, 'type');
        $location_id = data_get($data, 'location_id');
        if (in_array($type, $this->reduce)) {
            $shipLocationStore = $this->getStorageNumByLocationId([$location_id]);
            $lockInfos = $this->lockInventory->where('location_id', $location_id)->get()->groupBy('goods_no');
            if (PURCHASE_STOCK_SHIP == $type) {
                $lockList = [];
                foreach ($data['detail'] as $v) {
                    if (data_get($lockInfos, $v['goods_no'])) {
                        $lockInfo = data_get($lockInfos, $v['goods_no'])->first();
                        $store = data_get($shipLocationStore, $location_id . '.' . $v['goods_no']);
                        if ($v['num'] > $store - $lockInfo->lock_num) {
                            $goods = $this->getGoodsByNo($v['goods_no']);
                            error("存货名称为：" . data_get($goods, 'name') . "的可用数量不足");
                        }
                        $lockInfo->lock_num += $v['num'];
                        $lockList[] = $lockInfo;
                    } else {
                        $store = data_get($shipLocationStore, $location_id . '.' . $v['goods_no']);
                        if ($v['num'] > $store) {
                            $goods = $this->getGoodsByNo($v['goods_no']);
                            error("存货名称为：" . data_get($goods, 'name') . "的可用数量不足");
                        }
                        $lockData[] = [
                            'location_id' => $location_id,
                            'location_no' => data_get($this->getLocation($location_id), 'no'),
                            'lock_num' => $v['num'],
                            'goods_no' => $v['goods_no']
                        ];
                    }
                }
            } else {
                foreach ($data['detail'] as $v) {
                    if (data_get($lockInfos, $v['goods_no'])) {
                        $lockInfo = data_get($lockInfos, $v['goods_no'])->first();
                        $store = data_get($shipLocationStore, $location_id . '.' . $v['goods_no']);
                        if ($v['num'] > $store - $lockInfo->lock_num) {
                            $goods = $this->getGoodsByNo($v['goods_no']);
                            error("存货名称为：" . data_get($goods, 'name') . "的可用数量不足");
                        }
                    } else {
                        $store = data_get($shipLocationStore, $location_id . '.' . $v['goods_no']);
                        if ($v['num'] > $store) {
                            $goods = $this->getGoodsByNo($v['goods_no']);
                            error("存货名称为：" . data_get($goods, 'name') . "的可用数量不足");
                        }
                    }
                }
            }
        }
        DB::beginTransaction();
        try {
            $pur = $this->model->fill($data)->save();
            if (!$pur) {
                DB::rollBack();
                error("purchase");
            }
            $date = Carbon::now()->format("Y-m-d H:i:s");
            $sourceId = data_get($data, 'source_id', 0);
            array_walk(
                $detail,
                function (&$item, $k, $id) use ($type, $date, $location_id, $sourceId) {
                    $numInfo = ['num' => $item['num'], 'type' => $type];
                    $numInfo['reduce'] = $this->reduce;
                    $info['price'] = data_get($item, 'price');
                    $info['location_id'] = $location_id;
                    $info['source_id'] = $sourceId;
                    $info['goods_no'] = data_get($item, 'goods_no');
                    $info['num'] = data_get($item, 'num');
                    $info['type'] = $type;
                    $info['total_num'] = $this->getTotal($numInfo);
                    $info['plan_delivery_date'] = data_get($item, 'plan_delivery_date');
                    $info['attribute'] = data_get($item, 'attribute');
                    $info['remark'] = data_get($item, 'remark');
                    $info['unit'] = data_get($item, 'unit');
                    $info['purchase_id'] = $id;
                    $info['created_at'] = $info['updated_at'] = $date;
                    $item = $info;
                },
                $this->model->id
            );
            $ret = $this->purchaseDetail->addAll($detail);
            if (!$ret) {
                DB::rollBack();
                error("purchaseDetail数据添加失败");
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            error("数据添加失败");
        }
        if (isset($lockList)) {
            foreach ($lockList as $item) {
                $item->save();
            }
        }
        if (isset($lockData)) {
            $this->lockInventory->addAll($lockData);
        }
        return $this->model;
    }

    /**
     * 表单更新
     *
     * @param array $data
     * @return Purchase|false
     */
    public function update(array $data)
    {
        if (PURCHASE_STOCK_SHIP == data_get($data, 'type')) {
            $newLockNums = collect($data['detail'])->pluck('num', 'goods_no')->toArray();
            $historyLockNums = $this->purchaseDetail->where('purchase_id', data_get($data, 'id'))->get()
                ->pluck('num', 'goods_no')->toArray();
            $newGoods = array_keys($newLockNums);
            $historyGoods = array_keys($historyLockNums);
            $goodsNos = array_merge_recursive_distinct($newGoods, $historyGoods);
            $locationId = data_get($data, 'location_id');
            $lockInfos = $this->lockInventory->where('location_id', $locationId)
                ->whereIn('goods_no', $goodsNos)->get();
            $lockUpdate = [];
            foreach ($lockInfos as $lockInfo) {
                $lockInfo->lock_num += data_get($newLockNums, data_get($lockInfo, 'goods_no'), 0);
                $lockInfo->lock_num -= data_get($historyLockNums, data_get($lockInfo, 'goods_no'), 0);
                $lockUpdate[] = $lockInfo;
            }
            if (!empty($lockUpdate)) {
                foreach ($lockUpdate as $update) {
                    $update->save();
                }
            }
        }
        DB::beginTransaction();
        try {
            $this->purchaseDetail->where('purchase_id', data_get($data, 'id'))->get();
            $this->purchaseDetail->where('purchase_id', data_get($data, 'id'))->delete();
            $fillable = $this->model->getFillable();
            $purchaseData = collect($data)->only($fillable)->toArray();
            $purchase = $this->model->find(data_get($data, 'id'));
            $purchase->fill($purchaseData)->save();
            $detail = data_get($data, 'detail');
            $date = Carbon::now()->format("Y-m-d H:i:s");
            $type = data_get($data, 'type');
            $sourceId = data_get($purchaseData, 'source_id', 0);
            array_walk(
                $detail,
                function (&$item, $k, $id) use ($date, $type, $sourceId) {
                    $numInfo = ['num' => $item['num'], 'type' => $type];
                    $numInfo['reduce'] = $this->reduce;
                    $info['price'] = data_get($item, 'price');
                    $info['source_id'] = $sourceId;
                    $info['location_id'] = data_get($item, 'location_id');
                    $info['goods_no'] = data_get($item, 'goods_no');
                    $info['num'] = data_get($item, 'num');
                    $info['unit'] = data_get($item, 'unit');
                    $info['remark'] = data_get($item, 'remark');
                    $info['type'] = $type;
                    $info['plan_delivery_date'] = data_get($item, 'plan_delivery_date');
                    $info['attribute'] = data_get($item, 'attribute');
                    $info['purchase_id'] = $id;
                    $info['total_num'] = $this->getTotal($numInfo);
                    $info['created_at'] = $info['updated_at'] = $date;
                    $item = $info;
                },
                data_get($data, 'id')
            );
            $ret = $this->purchaseDetail->addAll($detail);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
        return $ret;
    }

    /**
     * 更新带Sn码的采购订单
     *
     * @param array $data
     * @return mixed
     */
    public function updateWithSn(array $data)
    {
        DB::beginTransaction();
        try {
            $purdetailIds = $this->purchaseDetail->where('purchase_id', data_get($data, 'id'))
                ->get('id')->pluck('id')->flatten()->toArray();
            $this->purSn->whereIn('purchase_detail_id', $purdetailIds)->delete();
            $this->purchaseDetail->where('purchase_id', data_get($data, 'id'))->delete();
            $fillable = $this->model->getFillable();
            $purchaseData = collect($data)->only($fillable)->toArray();
            $model = $this->model->find(data_get($data, 'id'));
            $model->fill($purchaseData)->update();
            $detail = data_get($data, 'detail');
            $date = Carbon::now()->format("Y-m-d H:i:s");
            $type = data_get($data, 'type');
            $purchaseId = data_get($data, 'id');
            $location_id = data_get($model, 'location_id');
            array_walk(
                $detail,
                function (&$item) use ($date, $type, $location_id, $purchaseId) {
                    $numInfo = ['num' => data_get($item, 'num'), 'type' => $type];
                    $numInfo['reduce'] = $this->reduce;
                    $info['price'] = data_get($item, 'price');
                    $info['location_id'] = $location_id;
                    $info['goods_no'] = data_get($item, 'goods_no');
                    $info['num'] = data_get($item, 'num');
                    $info['type'] = $type;
                    $info['total_num'] = $this->getTotal($numInfo);
                    $info['plan_delivery_date'] = data_get($item, 'plan_delivery_date');
                    $info['attribute'] = data_get($item, 'attribute');
                    $info['remark'] = data_get($item, 'remark');
                    $info['unit'] = data_get($item, 'unit');
                    $info['purchase_id'] = $purchaseId;
                    $info['created_at'] = $info['updated_at'] = $date;
                    $ret = DB::table('purchase_detail')->insertGetId($info);
                    if (!$ret) {
                        DB::rollBack();
                        error("添加表格详情数据失败");
                    }
                    $data = array_map(
                        function ($serial) use ($ret) {
                            $info['purchase_detail_id'] = $ret;
                            $info['sn_id'] = $serial;
                            return $info;
                        },
                        data_get($item, 'ids')
                    );
                    $ret = $this->purSn->addAll($data);
                    if (!$ret) {
                        DB::rollBack();
                        error("sn码关联信息数据添加失败");
                    }
                }
            );
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            error("表单更新失败");
        }
        return $model;
    }

    /**
     * 更新调拨单
     *
     * @param array $data
     * @return mixed
     */
    public function updateTransfer(array $data)
    {
        $purchase = $this->model->find(data_get($data, 'id'));
        if (UNCHECKED != data_get($purchase, 'checked')) {
            error("调拨单据已经被审核，无法被编辑");
        }
        if (is_null($purchase)) {
            error("调拨单不存在");
        }
        if (data_get($data, 'no') != data_get($purchase, 'no')) {
            error("单据序号不一致");
        }
        $ids = $this->purchaseDetail->where('purchase_id', data_get($data, 'id'))->get('id')->pluck('id')->flatten();
        $relation = $this->purSn->whereIn('purchase_detail_id', $ids)->get(['purchase_detail_id', 'sn_id'])->all();
        if (!empty($relation)) {
            $this->purSn->whereIn('purchase_detail_id', $ids)->delete();
        }
        data_set($data, 'status', 1); //单据状态 开启：1；关闭：-1；
        data_set($data, 'checked', 1); //审核状态 未审核：1；一级审核：2；多级累加；
        data_set($data, 'checked_user', null);
        $user = $this->getCurrentUser();
        $data['user_id'] = data_get($user, 'id');
        $data['user'] = data_get($user, 'username');
        try {
            $type = data_get($data, 'type');
            $purchaseId = data_get($purchase, 'id');
            $locationId = data_get($purchase, 'location_id');
            $receivingLocationId = data_get($purchase, 'receiving_location_id');
            $detail = $data['detail'];
            if (!empty(data_get($detail, '0.serials'))) {
                $data = compact('detail', 'type', 'locationId', 'receivingLocationId');
                $detail = $this->purCheckService->purTransfer($data);
                DB::beginTransaction();
                foreach ($detail as $item) {
                    $numInfo = ['num' => data_get($item, 'num'), 'type' => $type];
                    $numInfo['reduce'] = $this->reduce;
                    $info['price'] = data_get($item, 'price');
                    $info['location_id'] = data_get($item, 'location_id');
                    $info['goods_no'] = data_get($item, 'goods_no');
                    $info['num'] = data_get($item, 'num');
                    $info['type'] = $type;
                    $info['total_num'] = data_get($item, 'total_num');
                    $info['plan_delivery_date'] = data_get($item, 'plan_delivery_date');
                    $info['attribute'] = data_get($item, 'attribute');
                    $info['remark'] = data_get($item, 'remark');
                    $info['unit'] = data_get($item, 'unit');
                    $info['purchase_id'] = $purchaseId;
                    $ret = DB::table('purchase_detail')->insertGetId($info);
                    if (!$ret) {
                        DB::rollBack();
                        $this->purSn->addAll($relation);
                        error("添加表格详情数据失败");
                    }
                    $data = array_map(
                        function ($serial) use ($ret) {
                            $info['purchase_detail_id'] = $ret;
                            $info['sn_id'] = $serial;
                            return $info;
                        },
                        data_get($item, 'ids')
                    );
                    $ret = $this->purSn->addAll($data);
                    if (!$ret) {
                        DB::rollBack();
                        $this->purSn->addAll($relation);
                        error("sn码关联信息数据添加失败");
                    }
                }
                DB::commit();
            } else {
                DB::beginTransaction();
                $purchase->fill($data)->save();
                $this->purchaseDetail->whereIn('id', $ids)->delete();
                $purchaseId = data_get($purchase, 'id');
                array_walk(
                    $detail,
                    function (&$item, $k, $id) use ($type, $locationId) {
                        $numInfo = ['num' => $item['num'], 'type' => $type];
                        $numInfo['reduce'] = $this->reduce;
                        $info['price'] = data_get($item, 'price');
                        $info['location_id'] = $locationId;
                        $info['goods_no'] = data_get($item, 'goods_no');
                        $info['num'] = data_get($item, 'num');
                        $info['type'] = $type;
                        $info['total_num'] = $this->getTotal($numInfo);
                        $info['plan_delivery_date'] = data_get($item, 'plan_delivery_date');
                        $info['attribute'] = data_get($item, 'attribute');
                        $info['remark'] = data_get($item, 'remark');
                        $info['unit'] = data_get($item, 'unit');
                        $info['purchase_id'] = $id;
                        $item = $info;
                    },
                    $purchaseId
                );
                $ret = $this->purchaseDetail->addAll($detail);
                if (!$ret) {
                    DB::rollBack();
                    error("purchaseDetail数据添加失败");
                }
                array_walk(
                    $detail,
                    function (&$item, $k, $id) use ($type, $receivingLocationId) {
                        $info['price'] = data_get($item, 'price');
                        $info['location_id'] = $receivingLocationId;
                        $info['goods_no'] = data_get($item, 'goods_no');
                        $info['num'] = data_get($item, 'num');
                        $info['type'] = $type;
                        $info['total_num'] = -($item['total_num']);
                        $info['plan_delivery_date'] = data_get($item, 'plan_delivery_date');
                        $info['attribute'] = data_get($item, 'attribute');
                        $info['unit'] = data_get($item, 'unit');
                        $info['remark'] = data_get($item, 'remark');
                        $info['purchase_id'] = $id;
                        $item = $info;
                    },
                    $purchaseId
                );
                $ret1 = $this->purchaseDetail->addAll($detail);
                if (!$ret1) {
                    DB::rollBack();
                    error("purchaseDetail数据添加失败");
                }
                DB::commit();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            if (!empty($relation)) {
                $this->purSn->addAll($relation);
            }
            error("表单更新失败");
        }
        return $purchase;
    }

    /**
     * 更新采购单状态
     *
     * @param array $data
     * @return mixed
     */
    public function changeStatus(array $data)
    {
        $updateData = ['status' => $data['status']];
        return $this->model->where('id', data_get($data, 'id'))->update($updateData);
    }

    /**
     * 一级审核
     *
     * @param array $data
     * @return mixed
     */
    public function firstChecked(array $data)
    {
        if (-1 == $data['check_status']) {
            $ret = $this->model->where('parent_id', $data['id'])->first();
            if (!is_null($ret)) {
                error("单据已被引单，无法进行反审核");
            }
        }
        $user = $this->getCurrentUser();
        if (is_null($user)) {
            error("用户不存在");
        }
        $purchase = $this->model->where('id', data_get($data, 'id'))->first();
        empty($purchase) && error("要审核的表单不存在");
        if (is_null(data_get($purchase, 'checked_user')) && 1 == data_get($data, 'check_status')) {
            $checked_user[0] = [
                'id' => $user->id,
                'username' => $user->username,
                'checked_time' => date_format(now(), 'Y-m-d H:i:s'),
            ];
            $purchase->checked = FIRST_CHECKED;
            $purchase->checked_user = json_encode($checked_user);
            $purchase->save();
            if (in_array(data_get($purchase, 'type'), $this->storeType)) {
                $this->purchaseDetail->where('purchase_id', data_get($purchase, 'id'))->update(['finished' => 1]);
            }
            success("审核成功");
        }
        if (!is_null(data_get($purchase, 'checked_user')) && -1 == data_get($data, 'check_status')) {
            $checked_info = json_decode($purchase->checked_user, true);
            if (1 == count($checked_info)) {
                data_set($purchase, 'checked_user', null);
                $purchase->checked = UNCHECKED;
                $purchase->save();
                if (in_array(data_get($purchase, 'type'), $this->storeType)) {
                    $this->purchaseDetail->where('purchase_id', data_get($purchase, 'id'))->update(['finished' => 0]);
                }
                success("反审核成功");
            }
            error("请先进行二级审核反审核");
        }
        if (is_null(data_get($purchase, 'checked_user')) && -1 == data_get($data, 'check_status')) {
            error("请先进行审核");
        }
        if (!is_null(data_get($purchase, 'checked_user')) && 1 == data_get($data, 'check_status')) {
            error("请先进行反审核");
        }
        error("单据非法审核操作");
    }

    /**
     * 二级审核
     *
     * @param array $data
     */
    public function secondChecked(array $data)
    {
        if (-1 == $data['check_status']) {
            $ret = $this->model->where('parent_id', $data['id'])->first();
            if (!is_null($ret)) {
                error("单据已被引单，无法进行反审核");
            }
        }
        $user = $this->getCurrentUser();
        if (is_null($user)) {
            error("用户不存在");
        }
        $purchase = $this->model->where('id', data_get($data, 'id'))->first();
        empty($purchase) && error("单据不存在");
        $checked_info = data_get($purchase, 'checked_user') ? json_decode($purchase->checked_user, true) : [];
        if (2 == count($checked_info) && -1 == data_get($data, 'check_status')) {
            $newCheckedInfo[] = $checked_info[0];
            $purchase->checked_user = json_encode($newCheckedInfo);
            $purchase->checked = FIRST_CHECKED;
            $purchase->save();
            if (in_array(data_get($purchase, 'type'), $this->secondStoreType)) {
                $this->purchaseDetail->where('purchase_id', data_get($purchase, 'id'))->update(['finished' => 0]);
            }
            if (PURCHASE_STOCK_OUT == data_get($purchase, 'type')) {
                $purchaseDetail = $this->purchaseDetail->where('purchase_id', data_get($purchase, 'id'))->get();
                $freeNum = $purchaseDetail->pluck('num', 'goods_no')->toArray();
                $lockGoods = array_keys($freeNum);
                $locationId = data_get($purchase, 'location_id');
                $lockNums = $this->lockInventory->where('location_id', $locationId)
                    ->whereIn('goods_no', $lockGoods)->get();
                $update = [];
                foreach ($lockNums as $lockNum) {
                    $lockNum->lock_num += data_get($freeNum, data_get($lockNum, 'goods_no'));
                    $update[] = $lockNum;
                }
                if (!empty($lockNum)) {
                    foreach ($update as $item) {
                        $item->save();
                    }
                }
            }
            success("二级反审核成功");
        }
        if (2 == count($checked_info) && 1 == data_get($data, 'check_status')) {
            error("请先反审核二级审核");
        }
        if (1 == count($checked_info) && 1 == data_get($data, 'check_status')) {
            $checked_info[] = [
                'id' => $user->id,
                'username' => $user->username,
                'checked_time' => date_format(now(), 'Y-m-d H:i:s')
            ];
            $purchase->checked_user = json_encode($checked_info);
            $purchase->checked = SECOND_CHECKED;
            $purchase->save();
            if (in_array(data_get($purchase, 'type'), $this->secondStoreType)) {
                $this->purchaseDetail->where('purchase_id', data_get($purchase, 'id'))->update(['finished' => 1]);
            }
            if (PURCHASE_STOCK_OUT == data_get($purchase, 'type')) {
                $purchaseDetail = $this->purchaseDetail->where('purchase_id', data_get($purchase, 'id'))->get();
                $freeNum = $purchaseDetail->pluck('num', 'goods_no')->toArray();
                $lockGoods = array_keys($freeNum);
                $locationId = data_get($purchase, 'location_id');
                $lockNums = $this->lockInventory->where('location_id', $locationId)
                    ->whereIn('goods_no', $lockGoods)->get();
                $update = [];
                foreach ($lockNums as $lockNum) {
                    $lockNum->lock_num -= data_get($freeNum, data_get($lockNum, 'goods_no'));
                    $update[] = $lockNum;
                }
                if (!empty($lockNum)) {
                    foreach ($update as $item) {
                        $item->save();
                    }
                }
            }
            success("二级审核成功");
        }
        if (1 == count($checked_info) && -1 == data_get($data, 'check_status')) {
            error("请先进行二级审核");
        }
        if (0 == count($checked_info)) {
            error("请先进行一级审核");
        }
        error("非法审核操作");
    }

    /**
     * 单据三级审核状态
     *
     * @param array $data
     */
    public function thirdChecked(array $data)
    {
        if (-1 == $data['check_status']) {
            $ret = $this->model->where('parent_id', $data['id'])->first();
            if (!is_null($ret)) {
                error("单据已被引单，无法进行反审核");
            }
        }
        $user = $this->getCurrentUser();
        if (is_null($user)) {
            error("用户不存在");
        }
        $purchase = $this->model->where('id', data_get($data, 'id'))->first();
        is_null($purchase) && error("单据不存在");
        $checked_info = data_get($purchase, 'checked_user') ? json_decode($purchase->checked_user, true) : [];
        if (THIRD_CHECKED == data_get($purchase, 'checked') && -1 == data_get($data, 'check_status')) {
            array_pop($checked_info);
            $purchase->checked_user = json_encode($checked_info);
            $purchase->checked = SECOND_CHECKED;
            $purchase->save();
            success("三级反审核成功");
        }
        if (THIRD_CHECKED == data_get($purchase, 'checked') && 1 == data_get($data, 'check_status')) {
            error("请先进行三级反审核");
        }
        if (SECOND_CHECKED == data_get($purchase, 'checked') && 1 == data_get($data, 'check_status')) {
            $checked_info[] = [
                'id' => $user->id,
                'username' => $user->username,
                'checked_time' => date_format(now(), 'Y-m-d H:i:s')
            ];
            $purchase->checked_user = json_encode($checked_info);
            $purchase->checked = THIRD_CHECKED;
            $purchase->save();
            success("三级审核成功");
        }
        if (data_get($purchase, 'checked') < THIRD_CHECKED && -1 == data_get($data, 'check_status')) {
            error("请先进行三级审核");
        }
        if (data_get($purchase, 'checked') < SECOND_CHECKED && 1 == data_get($data, 'check_status')) {
            error("请先进行一级，二级审核");
        }
        error("非法审核操作");
    }

    /**
     * 获取采购单数据
     *
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function getList(array $data): LengthAwarePaginator
    {
        $limit = data_get($data, 'limit', 20);
        $query = $this->model;
        if (!isAdmin()) {
            /*$locationIds = getUsableLocation(data_get($data, 'location_ids', []));
            $query = $query->whereIn('location_id', $locationIds);*/
            $customer = $this->getCurrentUser();
            $companyId = data_get($customer, 'company_id');
            $query = $query->where('company_id', $companyId);
        } elseif (data_get($data, 'location_ids')) {
            $query = $query->whereIn('location_id', data_get($data, 'location_ids'));
        }
        if (data_get($data, 'parent_id') && is_array(data_get($data, 'parent_id'))) {
            $query = $query->whereIn('parent_id', data_get($data, 'parent_id'));
        }
        if (data_get($data, 'checked') && is_array(data_get($data, 'checked'))) {
            $query = $query->whereIn('checked', data_get($data, 'checked'));
        }
        if (data_get($data, 'status') && is_array(data_get($data, 'status'))) {
            $query = $query->whereIn('status', data_get($data, 'status'));
        }
        if (data_get($data, 'no')) {
            $query = $query->where('no', data_get($data, 'no'));
        }
        if (data_get($data, 'user')) {
            $query = $query->where('user', data_get($data, 'user'));
        }
        if (data_get($data, 'type')) {
            $classify = $this->classifyAudit->where('order_type', data_get($data, 'type'))->select('classify')->first();
            $query = $query->where('type', data_get($data, 'type'))
                ->where('checked', data_get($classify, 'classify'));
            if (!data_get($data, 'status')) {
                $query = $query->where('status', 1);
            }
        }
        $ret = $query->with(['supplier', 'company'])->orderByDesc('id')->paginate($limit);
        if ($ret->items()) {
            $lists = $ret->items();
            $items = $item = [];
            foreach ($lists as $obj) {
                $item = $obj->toArray();
                $subCompany = $this->getCompany(data_get($item, 'sub_company_id'));
                $location = $this->getLocation(data_get($item, 'location_id'));
                $item['created_at'] = date("Y-m-d H:i:s", strtotime($item['created_at']));
                $item['updated_at'] = date("Y-m-d H:i:s", strtotime($item['updated_at']));
                $item['supplier_name'] = data_get($item, 'supplier.name');
                $item['supplier_id'] = data_get($item, 'supplier.id');
                $item['company_name'] = data_get($item, 'company.name');
                $item['sale_type'] = data_get($item, 'sale_type');
                $item['sub_company_id'] = data_get($subCompany, 'id');
                $item['sub_company_name'] = data_get($subCompany, 'name');
                $item['location_name'] = data_get($location, 'name');
                $item['location_id'] = data_get($location, 'id');
                $item['company_no'] = data_get($item, 'company.company_no');
                unset($item['supplier']);
                unset($item['company']);
                $items[] = $item;
            }
            $ret->setCollection(collect($items));
        }
        return $ret;
    }

    /**
     *
     * @param array $ids
     */
    public function checkDelete(array $ids)
    {
        $ret = $this->model->whereIn('id', $ids)->get();
        foreach ($ret as $item) {
            if (UNCHECKED != data_get($item, 'checked')) {
                error("请将表单反审核");
            }
        }
    }


    /**
     * 获取采购明细表
     *
     * @param array $data
     * @return mixed
     */
    public function getDetailList(array $data): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $type = data_get($data, 'type');
        switch ($type) {
            case PURCHASE_PLAN:
                $ret = $this->getPlanDetailList($data);
                break;
            case PURCHASE_OTHER_OUT:
            case PURCHASE_OTHER_IN:
                $ret = $this->getOtherDetailList($data);
                break;
            default:
                $ret = $this->getArrivalList($data);
                break;
        }
        return $ret;
    }

    /**
     * 其它出其它入详情列表
     *
     * @param array $data
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    private function getOtherDetailList(array $data): \Illuminate\Contracts\Pagination\LengthAwarePaginator
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
        if (!is_null(data_get($data, 'company_id'))) {
            $query = $query->whereIn('purchases.company_id', data_get($data, 'company_id'));
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
            $items = $item = [];
            foreach ($ret->items() as $value) {
                $item['id'] = data_get($value, 'purchase_id');
                $item['no'] = data_get($value, 'no');
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                $supplier = $this->getSupplier(data_get($value, 'supplier_id'));
                $department = $this->getDepartment(data_get($value, 'department_id'));
                $location = $this->getLocation(data_get($value, 'location_id'));
                $item['audit_classify'] = $audit;
                $item['attribute'] = data_get($goods, 'attribute');
                $item['classify'] = data_get($goods, 'classify');
                $item['goods_type'] = data_get($goods, 'goods_type');
                $item['customer'] = data_get($value, 'customer');
                $item['department_id'] = data_get($value, 'department_id');
                $item['source_id'] = data_get($value, 'source_id');
                $item['department_name'] = data_get($department, 'name');
                $item['location_name'] = data_get($location, 'name');
                $item['location_response'] = data_get($location, 'location_response');
                $item['location_id'] = data_get($location, 'id');
                $item['supplier_id'] = data_get($value, 'supplier_id');
                $item['supplier_name'] = data_get($supplier, 'name');
                $item['plan_delivery_date'] = data_get($value, 'plan_delivery_date');
                $item['status'] = data_get($value, 'status');
                $item['price'] = data_get($value, 'price');
                $item['total_num'] = data_get($value, 'total_num');
                $item['num'] = data_get($value, 'num');
                $item['unit'] = data_get($value, 'unit');
                $item['goods_no'] = data_get($value, 'goods_no');
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
     * 获取详情列表
     *
     * @param array $data
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    private function getArrivalList(array $data): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $type = data_get($data, 'type');
        $audit = $this->getAuditClassify($type);
        $limit = data_get($data, 'limit');
        $query = DB::table('purchase_detail')->join(
            'purchases',
            function ($join) use ($type) {
                $join->on('purchase_detail.purchase_id', '=', 'purchases.id')
                    ->where('purchase_detail.type', $type)
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
        if (data_get($data, 'no')) {
            $query = $query->where('purchases.no', data_get($data, 'no'));
        }
        if (data_get($data, 'company_id')) {
            $query = $query->where('purchases.company_id', data_get($data, 'company_id'));
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
        if (data_get($data, 'location_ids')) {
            $query = $query->whereIn('purchase_detail.location_id', data_get($data, 'location_ids'));
        }
        if (data_get($data, 'status')) {
            $query = $query->whereIn('purchases.status', data_get($data, 'status'));
        }
        if (data_get($data, 'check_status')) {
            $query = $query->whereIn('purchases.checked', data_get($data, 'check_status'));
        }
        $ret = $query->select(
            [
                '*',
                'purchases.remark as purchase_remark',
                'purchase_detail.remark as detail_remark'
            ]
        )->orderByDesc('purchases.order_time')->paginate($limit);
        if (!is_null($ret->items())) {
            $parent_ids = collect($ret->items())->pluck('parent_id')->unique();
            $query = DB::table('purchase_detail')->join(
                'purchases',
                function ($join) use ($parent_ids) {
                    $join->on('purchase_detail.purchase_id', '=', 'purchases.id')->whereIn('purchases.id', $parent_ids);
                }
            );
            $plans = $query->get(
                [
                    'purchases.id as id',
                    'purchase_detail.total_num as total',
                    'purchase_detail.goods_no'
                ]
            )->groupBy(
                [
                    'id',
                    function ($item) {
                        return data_get($item, 'goods_no');
                    },
                ],
                true
            );
            foreach ($ret->items() as &$item) {
                $goods = $this->getGoodsByNo(data_get($item, 'goods_no'));
                $supplier = $this->getSupplier(data_get($item, 'supplier_id'));
                $location = $this->getLocation(data_get($item, 'location_id'));
                $department = $this->getDepartment(data_get($item, 'department_id'));
                $subCompany = $this->getCompany(data_get($item, 'sub_company_id'));
                data_set($item, 'audit_classify', $audit);
                data_set($item, 'goods_name', data_get($goods, 'name'));
                data_set($item, 'classify', data_get($goods, 'classify'));
                data_set($item, 'goods_type', data_get($goods, 'goods_type'));
                data_set($item, 'supplier_name', data_get($supplier, 'name'));
                data_set($item, 'supplier_id', data_get($item, 'supplier_id'));
                data_set($item, 'department_name', data_get($department, 'name'));
                data_set($item, 'department_id', data_get($item, 'department_id'));
                data_set($item, 'location_name', data_get($location, 'name'));
                data_set($item, 'location_no', data_get($location, 'no'));
                data_set($item, 'sub_company_id', data_get($subCompany, 'id'));
                data_set($item, 'sub_company_name', data_get($subCompany, 'name'));
                data_set($item, 'unit_name', data_get($item, 'unit'));
                data_set($item, 'detail_remark', data_get($item, 'detail_remark'));
                data_set($item, 'purchase_remark', data_get($item, 'purchase_remark'));
                data_set($item, 'checked_user', json_decode(data_get($item, 'checked_user')));
                unset($item->remark);
                $collection = data_get($plans, $item->parent_id . '.' . $item->goods_no);
                if (!empty($collection)) {
                    data_set($item, 'plan_num', $collection->first()->total);
                } else {
                    data_set($item, 'plan_num', 0);
                }
            }
        }
        return $ret;
    }


    /**
     * 获取采购计划明细列表
     *
     * @param array $data
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    private function getPlanDetailList(array $data): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $type = data_get($data, 'type');
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
            $locationIds = getUsableLocation(data_get($data, 'location_ids', []));
            $query = $query->whereIn('purchases.location_id', $locationIds);
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
        if (data_get($data, 'company_id')) {
            $query = $query->where('purchases.company_id', data_get($data, 'company_id'));
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
        )->orderByDesc('purchases.created_at')->paginate($limit);
        if (!empty($ret->items())) {
            $items = $item = [];
            foreach ($ret->items() as $value) {
                $item['id'] = data_get($value, 'purchase_id');
                $item['no'] = data_get($value, 'no');
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                $supplier = $this->getSupplier(data_get($value, 'supplier_id'));
                $department = $this->getDepartment(data_get($value, 'department_id'));
                $item['attribute'] = data_get($goods, 'attribute');
                $item['customer'] = data_get($value, 'customer');
                $item['department_id'] = data_get($value, 'department_id');
                $item['department_name'] = data_get($department, 'name');
                $item['supplier_id'] = data_get($value, 'supplier_id');
                $item['supplier_name'] = data_get($supplier, 'name');
                $item['plan_delivery_date'] = data_get($value, 'plan_delivery_date');
                $item['price'] = data_get($value, 'price');
                $item['total_num'] = data_get($value, 'total_num');
                $item['num'] = data_get($value, 'num');
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
     * 删除采购单
     *
     * @param array $ids
     * @return mixed
     * @throws \Exception
     */
    public function delete(array $ids)
    {
        $this->checkDelete($ids);
        $purchaseIds = $this->purchaseDetail->whereIn('purchase_id', $ids)->get()->pluck('id')->flatten()->all();
        $this->purSn->whereIn('purchase_detail_id', $purchaseIds)->delete();
        $purchaseDetail = $this->purchaseDetail->whereIn('id', $purchaseIds)->get();
        $relations = $purchaseDetail->pluck('location_id', 'type')->toArray();
        $locationIds = array_unique(array_values($relations));
        $lockInfos = $this->lockInventory
            ->whereIn('location_id', $locationIds)->get()->groupBy(['location_id', 'goods_no']);
        $updateInfos = [];
        foreach ($purchaseDetail as $item) {
            if (in_array(data_get($item, 'type'), $this->ship)) {
                $lockInfo = data_get($lockInfos, data_get($item, 'location_id') . '.' . $item['goods_no']);
                if (!is_null($lockInfo)) {
                    $lockLocationGoods = $lockInfo->first();
                    $lockLocationGoods->lock_num -= abs(data_get($item, 'num'));
                    $updateInfos[] = $lockLocationGoods;
                }
            }
        }
        if (!empty($updateInfos)) {
            foreach ($updateInfos as $updateInfo) {
                $updateInfo->save();
            }
        }
        $this->purchaseDetail->whereIn('id', $purchaseIds)->delete();
        return $this->model->whereIn('id', $ids)->delete();
    }

    /**
     * 获取采购单详情
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
        if (!empty($ret)) {
            $supplier = $this->getSupplier(data_get($ret, 'supplier_id'));
            $department = $this->getDepartment(data_get($ret, 'department_id'));
            $subCompany = $this->getCompany(data_get($ret, 'sub_company_id'));
            $location = $this->getLocation(data_get($ret, 'location_id'));
            if (PURCHASE_OTHER_OUT == data_get($ret, 'type')) {
                $locationNums = collect($this->getStorageNumByLocationId([$location]))->first();
                $locationLockNums = collect($this->getLockNumByLocationId([$location]))->first();
            }
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
            $detail['consignee_info'] = json_decode(data_get($ret, 'consignee_info'), '[]');
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
                            $serials = array_merge($serials, $serial);
                        }
                    }
                    $item['serials'] = array_unique($serials);
                } else {
                    $item['serials'] = [];
                }
                $item['goods_name'] = data_get($goods, 'name');
                $item['is_softWare'] = data_get($goods, 'is_software');
                $item['classify'] = data_get($goods, 'classify');
                $item['attribute'] = data_get($goods, 'attribute');
                if (PURCHASE_OTHER_OUT == data_get($ret, 'type')) {
                    $item['usable_num'] = data_get($locationNums, $goods_no, 0)
                        - data_get($locationLockNums, $goods_no, 0);
                    $item['existing_num'] = data_get($locationNums, $goods_no, 0);
                } else {
                    $item['existing_num'] = data_get($usableCompany, $goods_no, 0);
                    $item['usable_num'] = data_get($usableCompany, $goods_no, 0) - data_get($lockNum, $goods_no, 0);
                }
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
        if (PURCHASE_OTHER_OUT == data_get($detail, 'type')) {
            $backNums = $this->purchaseDetail->where([
                'source_id' => data_get($detail, 'id'),
                'finished' => 1
            ])->get()->groupBy('goods_no');
            foreach ($detail['detail'] as &$item) {
                $item['back_num'] = data_get($backNums, $item['goods_no'] . '.0.num', 0);
            }
        }
        return $detail;
    }

    /**
     * 根据id获取引用单据类型
     * @param $id
     * @return mixed
     */
    public function getParentTypeById($id)
    {
        return $this->model->where('id', $id)->first();
    }

    /**
     * 根据单号获取单据
     * @param string $no
     * @return mixed
     */
    public function getPurchaseByNo(string $no)
    {
        return $this->model->where('no', $no)->first();
    }
}
