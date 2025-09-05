<?php

namespace App\Services\Purchase;

use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseDetail;
use App\Models\Purchase\PurSnRelation;
use App\Models\Purchase\RedSaleRelation;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Class SaleRedService
 * @package App\Services\Purchase
 */
class SaleRedService extends BaseService
{
    protected $redSaleRelation;
    protected $purchase;
    protected $purSnRelation;
    protected $purchaseDetail;

    public function __construct(
        Purchase $purchase,
        RedSaleRelation $redSaleRelation,
        PurSnRelation $purSnRelation,
        PurchaseDetail $purchaseDetail
    ) {
        $this->redSaleRelation = $redSaleRelation;
        $this->purchase = $purchase;
        $this->purSnRelation = $purSnRelation;
        $this->purchaseDetail = $purchaseDetail;
    }

    /**
     * 新建不绑定SN码的销售出库红字单
     * @param array $data
     * @return Purchase
     */
    public function saleRedAdd(array $data): Purchase
    {
        $ret = $this->purchase->where('no', data_get($data, 'no'))->first();
        if (!is_null($ret)) {
            error("销售红字出库单已存在");
        }
        $user = $this->getCurrentUser();
        $data['user'] = data_get($user, 'username');
        $data['user_id'] = data_get($user, 'id');
        $data['company_id'] = data_get($user, 'company_id');
        $data['status'] = 1;
        $data['checked'] = UNCHECKED;
        DB::beginTransaction();
        try {
            $this->purchase->fill($data)->save();
            $purchaseId = data_get($this->purchase, 'id');
            $saleRedData = array_map(function (&$value) use ($purchaseId) {
                $tmp = [];
                $tmp['parent_id'] = data_get($value, 'parent_id');
                $tmp['purchase_id'] = $purchaseId;
                $tmp['sale_order_id'] = $value['id'];
                $tmp['num'] = abs($value['num']);
                $tmp['finished'] = 0;
                $tmp['remark'] = data_get($value, 'remark');
                return $tmp;
            }, data_get($data, 'detail'));
            $ret = $this->redSaleRelation->addAll($saleRedData);
            if (!$ret) {
                DB::rollBack();
                error("添加销售出库单红字数据失败");
            }
            $type = data_get($data, 'type', PURCHASE_STOCK_RED);
            $detailData = [];
            foreach (data_get($data, 'detail', []) as $item) {
                $detailData[] = [
                    'location_id' => data_get($data, 'location_id'),
                    'purchase_id' => $purchaseId,
                    'type' => $type,
                    'source_id' => 0,
                    'num' => data_get($item, 'num'),
                    'goods_no' => data_get($item, 'goods_no'),
                    'remark' => data_get($item, 'remark'),
                    'total_num' => data_get($item, 'num'),
                    'finished' => 0,
                ];
            }
            $ret = $this->purchaseDetail->addAll($detailData);
            if (!$ret) {
                DB::rollBack();
                error("销售出库红字表单详情添加失败");
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            error("添加销售出库红字数据失败");
        }
        return $this->purchase;
    }

    /**
     * 新建绑定SN码的销售出库红字单
     * @param array $data
     * @return Purchase
     */
    public function saleRedAddWithSerials(array $data): Purchase
    {
        $ret = $this->purchase->where('no', data_get($data, 'no'))->first();
        if (!is_null($ret)) {
            error("销售红字出库单已存在");
        }
        $user = $this->getCurrentUser();
        $data['user'] = data_get($user, 'username');
        $data['user_id'] = data_get($user, 'id');
        $data['company_id'] = data_get($user, 'company_id');
        $data['status'] = 1;
        $data['checked'] = UNCHECKED;
        DB::beginTransaction();
        try {
            $this->purchase->fill($data)->save();
            $purchaseId = data_get($this->purchase, 'id');
            $saleRedData = array_map(function (&$value) use ($purchaseId) {
                $tmp = [];
                $tmp['parent_id'] = data_get($value, 'parent_id');
                $tmp['purchase_id'] = $purchaseId;
                $tmp['sale_order_id'] = $value['id'];
                $tmp['serials'] = json_encode($value['serials']);
                $tmp['num'] = abs($value['num']);
                $tmp['finished'] = 0;
                $tmp['remark'] = data_get($value, 'remark');
                return $tmp;
            }, data_get($data, 'detail'));
            $ret = $this->redSaleRelation->addAll($saleRedData);
            if (!$ret) {
                DB::rollBack();
                error("添加销售出库单红字数据失败");
            }
            $type = data_get($data, 'type', PURCHASE_STOCK_RED);
            $purchaseDetail = data_get($data, 'purchase_detail');
            array_walk($purchaseDetail, function (&$value, $key, $purchaseId) use ($type) {
                $value['purchase_id'] = $purchaseId;
                $value['type'] = $type;
                $snId = data_get($value, 'ids');
                unset($value['ids']);
                $purchaseDetailId = DB::table('purchase_detail')->insertGetId($value);
                if (!$purchaseDetailId) {
                    DB::rollBack();
                    error("添加销售订单表详情失败");
                }
                $purSns = $purSn = [];
                foreach ($snId as $sn) {
                    $purSn['purchase_detail_id'] = $purchaseDetailId;
                    $purSn['sn_id'] = $sn;
                    $purSns[] = $purSn;
                }
                $ret = $this->purSnRelation->addAll($purSns);
                if (!$ret) {
                    DB::rollBack();
                    error("添加销售出库单红字关联关系表失败");
                }
            }, $purchaseId);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            error("添加销售出库红字数据失败");
        }
        return $this->purchase;
    }

    /**
     * 更新销售出库单红字
     * @param array $data
     * @return mixed
     */
    public function update(array $data)
    {
        $purchaseId = data_get($data, 'id');
        $purchase = $this->purchase->where('id', $purchaseId)->first();
        if (empty($purchase)) {
            error("销售出库单红字不存在");
        }
        if (!empty(data_get($purchase, 'checked_user'))) {
            error("销售出库单红字已审核，");
        }
        if (data_get($data, 'no') != data_get($purchase, 'no')) {
            error("单据序号不能被更改");
        }
        $ret = $purchase->update($data);

        $purchaseDetailIds = $this->purchaseDetail->where('purchase_id', $purchaseId)
            ->select('id')->get()->pluck('id')->flatten()->toArray();
        $this->purchaseDetail->where('purchase_id', $purchaseId)->delete();
        $this->redSaleRelation->where('purchase_id', $purchaseId)->delete();
        $this->purSnRelation->whereIn('purchase_detail_id', $purchaseDetailIds)->delete();

        $saleRedData = array_map(function (&$value) use ($purchaseId) {
            $tmp = [];
            $tmp['parent_id'] = data_get($value, 'parent_id');
            $tmp['purchase_id'] = $purchaseId;
            $tmp['sale_order_id'] = $value['id'];
            $tep['num'] = abs($value['num']);
            $tmp['finished'] = 0;
            $tmp['remark'] = data_get($value, 'remark');
            return $tmp;
        }, data_get($data, 'detail'));
        $this->redSaleRelation->addAll($saleRedData);
        $type = data_get($data, 'type', PURCHASE_STOCK_RED);
        $detailData = [];
        foreach (data_get($data, 'detail', []) as $item) {
            $detailData[] = [
                'location_id' => data_get($data, 'location_id'),
                'purchase_id' => $purchaseId,
                'type' => $type,
                'source_id' => 0,
                'num' => data_get($item, 'num'),
                'goods_no' => data_get($item, 'goods_no'),
                'remark' => data_get($item, 'remark'),
                'total_num' => data_get($item, 'num'),
                'finished' => 0,
            ];
        }
        $this->purchaseDetail->addAll($detailData);
        return $ret;
    }

    /**
     * 更新带SN码信息的销售出库红字单
     * @param array $data
     * @return mixed
     */
    public function updateWithSerial(array $data)
    {
        $purchaseId = data_get($data, 'id');
        $purchase = $this->purchase->where('id', $purchaseId)->first();
        if (empty($purchase)) {
            error("销售出库单红字不存在");
        }
        if (!empty(data_get($purchase, 'checked_user'))) {
            error("销售出库单红字已审核，");
        }
        if (data_get($data, 'no') != data_get($purchase, 'no')) {
            error("单据序号不能被更改");
        }
        $ret = $purchase->update($data);
        $purchaseDetailIds = $this->purchaseDetail->where('purchase_id', $purchaseId)
            ->select('id')->get()->pluck('id')->flatten()->toArray();
        $this->purchaseDetail->where('purchase_id', $purchaseId)->delete();
        $this->redSaleRelation->where('purchase_id', $purchaseId)->delete();
        $this->purSnRelation->whereIn('purchase_detail_id', $purchaseDetailIds)->delete();
        $saleRedData = array_map(function (&$value) use ($purchaseId) {
            $tmp = [];
            $tmp['parent_id'] = data_get($value, 'parent_id');
            $tmp['purchase_id'] = $purchaseId;
            $tmp['sale_order_id'] = $value['id'];
            $tmp['serials'] = json_encode($value['serials']);
            $tmp['num'] = abs($value['num']);
            $tmp['finished'] = 0;
            $tmp['remark'] = data_get($value, 'remark');
            return $tmp;
        }, data_get($data, 'detail'));
        $this->redSaleRelation->addAll($saleRedData);
        $type = data_get($data, 'type', PURCHASE_STOCK_RED);
        $purchaseDetail = data_get($data, 'purchase_detail');
        array_walk($purchaseDetail, function (&$value, $key, $purchaseId) use ($type) {
            $value['purchase_id'] = $purchaseId;
            $value['type'] = $type;
            $snId = data_get($value, 'ids');
            unset($value['ids']);
            $purchaseDetailId = DB::table('purchase_detail')->insertGetId($value);
            if (!$purchaseDetailId) {
                DB::rollBack();
                error("添加销售订单表详情失败");
            }
            $purSns = $purSn = [];
            foreach ($snId as $sn) {
                $purSn['purchase_detail_id'] = $purchaseDetailId;
                $purSn['sn_id'] = $sn;
                $purSns[] = $purSn;
            }
            $this->purSnRelation->addAll($purSns);
        }, $purchaseId);
        return $ret;
    }

    /**
     * 根据id获取销售出库单红字详情
     * @param int $id
     * @return mixed
     */
    public function getSaleRedByIds(int $id)
    {
        $purchase = $this->purchase->where('id', $id)->with('saleReds', 'saleRedRemarks')->first();
        $location = $this->getLocation(data_get($purchase, 'location_id'));
        $purchase->location_name = data_get($location, 'name');
        $department = $this->getDepartment(data_get($purchase, 'department_id'));
        $purchase->department_name = data_get($department, 'name');
        $purchase->parent_no = data_get($purchase->toArray(), 'sale_red_remarks.0.sale_back.no');
        $purchase->audit_classify = $this->getAuditClassify(data_get($purchase, 'type'));
        $numMap = $this->redSaleRelation->where('purchase_id', $id)->get()->groupBy('sale_order_id');
        foreach ($purchase->saleReds as $key => &$value) {
            $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
            $value['goods_name'] = data_get($goods, 'name');
            $value['attribute'] = data_get($goods, 'attribute');
            $value['is_software'] = data_get($goods, 'is_software');
            $value['unit'] = data_get($goods, 'unit');
            $value['parent_id'] = data_get($purchase->toArray(), 'sale_red_remarks.' . $key . '.parent_id');
            $serials = is_null(data_get($purchase->toArray(), 'sale_red_remarks.' . $key . '.serials'))
                ? '[]' : data_get($purchase->toArray(), 'sale_red_remarks.' . $key . '.serials');
            $value['serials'] = json_decode($serials, true);
            $value['back_num'] = -abs(data_get($numMap, data_get($value, 'id') . '.0.num'));
            $value['remark'] = data_get($numMap, data_get($value, 'id') . '.0.remark');
            if (!empty($value['serials'])) {
                $value['back_num'] = -count($value['serials']);
            }
            $value['remark'] = data_get($purchase, 'saleRedRemarks.' . $key . '.remark');
        }
        unset($purchase->saleRedRemarks);
        return $purchase;
    }

    /**
     * 审核销售出库单红字
     * @param array $data
     * @return mixed
     */
    public function audit(array $data)
    {
        $purchase = $this->purchase->where('id', data_get($data, 'id'))->first();
        is_null($purchase) && error("销售出库单红字不存在");
        if (1 == data_get($data, 'check_status') && empty(data_get($purchase, 'checked_user'))) {
            $user = $this->getCurrentUser();
            $info['user_id'] = data_get($user, 'id');
            $info['username'] = data_get($user, 'username');
            $info['checked_time'] = date('Y-m-d H:i:s', time());
            $checkInfo[] = $info;
            $purchase->checked_user = json_encode($checkInfo);
            $purchase->checked = FIRST_CHECKED;
            $this->redSaleRelation->where('purchase_id', data_get($data, 'id'))->update(['finished' => 1]);
            $this->purchaseDetail->where('purchase_id', data_get($data, 'id'))->update(['finished' => 1]);
        } elseif (1 == data_get($data, 'check_status') && !empty(data_get($purchase, 'checked_user'))) {
            error("销售出库单红字已审核");
        }
        if (-1 == data_get($data, 'check_status') && !empty(data_get($purchase, 'checked_user'))) {
            $purchase->checked_user = null;
            $purchase->checked = UNCHECKED;
            $this->redSaleRelation->where('purchase_id', data_get($data, 'id'))->update(['finished' => 0]);
            $this->purchaseDetail->where('purchase_id', data_get($data, 'id'))->update(['finished' => 0]);
        } elseif (-1 == data_get($data, 'check_status') && empty(data_get($purchase, 'checked_user'))) {
            error("销售出库单红字未审核");
        }
        return $purchase->save();
    }

    /**
     * 获取销售出库红字列表
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function getSaleRedList(array $data): LengthAwarePaginator
    {
        $audit = $this->getAuditClassify(data_get($data, 'type'));
        $limit = data_get($data, 'limit', 20);
        $query = DB::table('purchases')->join(
            'red_sale_relation',
            function ($join) {
                return $join->on('purchases.id', '=', 'red_sale_relation.purchase_id')
                    ->whereNull('purchases.deleted_at')
                    ->whereNull('red_sale_relation.deleted_at');
            }
        );
        $query = $query->join(
            'sale_orders',
            function ($join) {
                return $join->on('red_sale_relation.sale_order_id', '=', 'sale_orders.id');
            }
        );
        $purchases = $query->where(['purchases.type' => PURCHASE_SALE_RED])
            ->orderByDesc('purchases.id')->paginate($limit);
        $purchaseIds = array_unique(array_column($purchases->items(), 'purchase_id'));
        $numMap = $this->redSaleRelation->whereIn('purchase_id', $purchaseIds)->get()->groupBy('sale_order_id');
        $remarkMap = $this->purchase->whereIn('id', $purchaseIds)->get(['remark', 'id'])->pluck('remark', 'id');
        if ($purchases->total() > 0) {
            foreach ($purchases->items() as &$item) {
                $goods = $this->getGoodsByNo(data_get($item, 'goods_no'));
                $location = $this->getLocation(data_get($item, 'location_id'));
                $item->audit_classify = $audit;
                $item->remark = data_get($remarkMap, $item->purchase_id, '');
                $item->goods_name = data_get($goods, 'name');
                $item->num = data_get($numMap, data_get($item, 'id') . '.0.num');
                $item->location_name = data_get($location, 'name');
                $item->unit = data_get($goods, 'unit');
                $item->attribute = data_get($goods, 'attribute');
            }
        }
        return $purchases;
    }

    /**
     * 根据id删除单据
     * @param int $id
     * @return mixed
     */
    public function delete(int $id)
    {
        $purchase = $this->purchase->where('id', $id)->first();
        if (empty($purchase)) {
            error("销售出库单不存在");
        }
        if (UNCHECKED != data_get($purchase, 'checked')) {
            error("销售出库单已审核");
        }
        $purchaseDetailIds = $this->purchaseDetail->where('purchase_id', $id)
            ->get('id')->pluck('id')->flatten()->toArray();
        $this->purSnRelation->whereIn('purchase_detail_id', $purchaseDetailIds)->delete();
        $this->purchaseDetail->whereIn('id', $purchaseDetailIds)->delete();
        $this->redSaleRelation->where('purchase_id', $id)->delete();
        return $this->purchase->where('id', $id)->delete();
    }
}
