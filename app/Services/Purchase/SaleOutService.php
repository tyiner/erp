<?php

namespace App\Services\Purchase;

use App\Models\Purchase\OutSaleRelation;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseDetail;
use App\Models\Purchase\PurSnRelation;
use App\Models\Stock\LockInventory;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

/**
 * Class SaleOutService
 * @package App\Services\Purchase
 */
class SaleOutService extends BaseService
{
    protected $purchase;
    protected $model;
    protected $purchaseDetail;
    protected $purSnRelation;
    protected $lockInv;

    public function __construct(
        Purchase $purchase,
        OutSaleRelation $model,
        PurchaseDetail $purchaseDetail,
        PurSnRelation $purSnRelation,
        LockInventory $lockInv
    ) {
        $this->purchase = $purchase;
        $this->model = $model;
        $this->purchaseDetail = $purchaseDetail;
        $this->purSnRelation = $purSnRelation;
        $this->lockInv = $lockInv;
    }

    /**
     * 获取销售出库单列表
     * @param array $data
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getSaleOutList(array $data): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $audit = $this->getAuditClassify(data_get($data, 'type'));
        $limit = data_get($data, 'limit', 20);
        $query = DB::table('purchases')->join(
            'out_sale_relation',
            function ($join) {
                $join->on('purchases.id', '=', 'out_sale_relation.purchase_id')
                    ->whereNull('out_sale_relation.deleted_at')
                    ->whereNull('purchases.deleted_at');
            }
        );
        $query = $query->join(
            'sale_orders',
            function ($join) {
                $join->on('out_sale_relation.sale_order_id', '=', 'sale_orders.id');
            }
        );
        $purchases = $query->where(['purchases.type' => PURCHASE_SALE_OUT])
            ->orderByDesc('purchases.id')->paginate($limit);
        if ($purchases->total() > 0) {
            $parentsId = array_unique(array_column($purchases->items(), 'parent_id'));
            $purchaseIds = array_unique(array_column($purchases->items(), 'purchase_id'));
            $nums = $this->model->whereIn('purchase_id', $purchaseIds)->get()->groupBy([
                'purchase_id',
                'sale_order_id'
            ]);
            $remarkMap = $this->purchase->whereIn('id', $purchaseIds)->get(['remark', 'id'])->pluck('remark', 'id');
            $parentsNo = $this->purchase->whereIn('id', $parentsId)->get(['id', 'no'])->groupBy('id');
            foreach ($purchases->items() as &$item) {
                $goods = $this->getGoodsByNo(data_get($item, 'goods_no'));
                $location = $this->getLocation(data_get($item, 'location_id'));
                $item->audit_classify = $audit;
                $item->remark = data_get($remarkMap, $item->purchase_id, '');
                $item->goods_name = data_get($goods, 'name');
                $item->num = data_get($nums, $item->purchase_id . '.' . $item->id . '.0.num');
                $item->location_name = data_get($location, 'name');
                $item->unit = data_get($goods, 'unit');
                $item->sale_ship_no = data_get($parentsNo, data_get($item, 'parent_id') . '.0.no');
                $item->attribute = data_get($goods, 'attribute');
            }
        }
        return $purchases;
    }

    /**
     * 新建不关联SN码信息的销售出库单
     * @param array $data
     * @return Purchase
     */
    public function saleOutAdd(array $data): Purchase
    {
        $ret = $this->purchase->where('no', data_get($data, 'no'))->first();
        if (!is_null($ret)) {
            error("销售订单已经存在");
        }
        DB::beginTransaction();
        try {
            $user = $this->getCurrentUser();
            $data['checked'] = UNCHECKED;
            $data['status'] = 1;
            $data['user'] = data_get($user, 'username');
            $data['user_id'] = data_get($user, 'id');
            $data['company_id'] = data_get($user, 'company_id');
            $this->purchase->fill($data)->save();
            $purchaseId = data_get($this->purchase, 'id');
            $detail = data_get($data, 'detail');
            $saleOutData = array_map(function (&$value) use ($purchaseId) {
                $tmp = [];
                $tmp['parent_id'] = data_get($value, 'parent_id');
                $tmp['purchase_id'] = $purchaseId;
                $tmp['sale_order_id'] = $value['id'];
                $tmp['num'] = abs($value['num']);
                $tmp['finished'] = 0;
                $tmp['remark'] = data_get($value, 'remark');
                return $tmp;
            }, $detail);
            $ret = $this->model->addAll($saleOutData);
            if (!$ret) {
                DB::rollBack();
                error("添加销售出库单关联关系表失败");
            }
            $type = data_get($data, 'type', PURCHASE_SALE_OUT);
            $locationId = data_get($data, 'location_id');
            $sourceId = data_get($data, 'source_id', 0);
            $detailData = array_map(function (&$value) use ($type, $purchaseId, $locationId, $sourceId) {
                $tmp['purchase_id'] = $purchaseId;
                $tmp['location_id'] = $locationId;
                $tmp['total_num'] = -abs($value['num']);
                $tmp['num'] = abs($value['num']);
                $tmp['goods_no'] = $value['goods_no'];
                $tmp['remark'] = data_get($value, 'remark');
                $tmp['type'] = $type;
                $tmp['source_id'] = $sourceId;
                $tmp['finished'] = 0;
                return $tmp;
            }, $detail);
            $ret = $this->purchaseDetail->addAll($detailData);
            if (!$ret) {
                DB::rollBack();
                error("添加销售出库单详情信息失败");
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            error("销售出库单数据添加失败");
        }
        return $this->purchase;
    }

    /**
     * 新添加关联SN码信息的销售出库单
     * @param array $data
     * @return Purchase
     */
    public function saleOutAddWithSerial(array $data): Purchase
    {
        $ret = $this->purchase->where('no', data_get($data, 'no'))->first();
        if (!is_null($ret)) {
            error("销售订单已经存在");
        }
        DB::beginTransaction();
        try {
            $user = $this->getCurrentUser();
            $data['checked'] = UNCHECKED;
            $data['status'] = 1;
            $data['user'] = data_get($user, 'username');
            $data['user_id'] = data_get($user, 'id');
            $data['company_id'] = data_get($user, 'company_id');
            $this->purchase->fill($data)->save();
            $purchaseId = data_get($this->purchase, 'id');
            $detail = data_get($data, 'detail');
            $purchaseDetail = data_get($data, 'purchase_detail');
            $saleOutData = array_map(function (&$value) use ($purchaseId) {
                $tmp = [];
                $tmp['parent_id'] = data_get($value, 'parent_id');
                $tmp['purchase_id'] = $purchaseId;
                $tmp['sale_order_id'] = $value['id'];
                $tmp['num'] = abs($value['num']);
                $tmp['serials'] = json_encode($value['serials']);
                $tmp['finished'] = 0;
                $tmp['remark'] = data_get($value, 'remark');
                return $tmp;
            }, $detail);
            $ret = $this->model->addAll($saleOutData);
            if (!$ret) {
                DB::rollBack();
                error("添加销售出库单关联关系表失败");
            }
            $type = data_get($data, 'type', PURCHASE_SALE_OUT);
            array_walk($purchaseDetail, function (&$value, $key, $purchaseId) use ($type) {
                $value['purchase_id'] = $purchaseId;
                $value['num'] = abs($value['total_num']);
                $value['type'] = $type;
                $snId = data_get($value, 'serials');
                unset($value['serials']);
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
                    error("添加销售出库单详情关联Sn码信息失败");
                }
            }, $purchaseId);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            error("销售出库单数据添加失败");
        }
        return $this->purchase;
    }

    /**
     * 更新销售出库单
     * @param array $data
     * @return mixed
     */
    public function update(array $data)
    {
        $purchaseId = data_get($data, 'id');
        $purchase = $this->purchase->where('id', $purchaseId)->first();
        if (empty($purchase)) {
            error("不存在的销售出库单");
        }
        if (data_get($data, 'no') != data_get($purchase, 'no')) {
            error("单据编号不能被更改");
        }
        if (!empty(data_get($purchase, 'checked_user'))) {
            error("单据已审核");
        }
        $ret = $purchase->update($data);
        $this->model->where('purchase_id', $purchaseId)->delete();
        $detail = data_get($data, 'detail');
        $saleOutData = array_map(function (&$value) use ($purchaseId) {
            $tmp = [];
            $tmp['parent_id'] = data_get($value, 'parent_id');
            $tmp['purchase_id'] = $purchaseId;
            $tmp['sale_order_id'] = $value['id'];
            $tmp['num'] = abs($value['num']);
            $tmp['finished'] = 0;
            $tmp['remark'] = data_get($value, 'remark');
            return $tmp;
        }, $detail);
        $this->model->addAll($saleOutData);
        $type = PURCHASE_SALE_OUT;
        $locationId = data_get($data, 'location_id');
        $sourceId = data_get($data, 'source_id');
        $this->purchaseDetail->where('purchase_id', data_get($purchase, 'id'))->delete();
        $detailData = array_map(function (&$value) use ($type, $purchaseId, $locationId, $sourceId) {
            $tmp['purchase_id'] = $purchaseId;
            $tmp['location_id'] = $locationId;
            $tmp['total_num'] = -abs($value['num']);
            $tmp['num'] = abs($value['num']);
            $tmp['goods_no'] = $value['goods_no'];
            $tmp['remark'] = data_get($value, 'remark');
            $tmp['type'] = $type;
            $tmp['source_id'] = $sourceId;
            $tmp['finished'] = 0;
            return $tmp;
        }, $detail);
        $this->purchaseDetail->addAll($detailData);
        return $ret;
    }

    /**
     * 更新带Sn码的销售出库单
     * @param array $data
     * @return mixed
     */
    public function updateWithSerial(array $data)
    {
        $purchaseId = data_get($data, 'id');
        $purchase = $this->purchase->where('id', $purchaseId)->first();
        if (empty($purchase)) {
            error("不存在的销售出库单");
        }
        if (data_get($data, 'no') != data_get($purchase, 'no')) {
            error("单据编号不能被更改");
        }
        if (!empty(data_get($purchase, 'checked_user'))) {
            error("单据已审核");
        }
        $ret = $purchase->update($data);
        $this->model->where('purchase_id', $purchaseId)->delete();
        $purchaseDetailIds = $this->purchaseDetail->where('purchase_id', $purchaseId)->select('id')
            ->get()->pluck('id')->flatten()->toArray();
        $this->purSnRelation->whereIn('purchase_detail_id', $purchaseDetailIds)->delete();
        $this->purchaseDetail->where('purchase_id', $purchaseId)->delete();
        $detail = data_get($data, 'detail');
        $saleOutData = array_map(function (&$value) use ($purchaseId) {
            $tmp = [];
            $tmp['parent_id'] = data_get($value, 'parent_id');
            $tmp['purchase_id'] = $purchaseId;
            $tmp['sale_order_id'] = $value['id'];
            $tmp['num'] = abs($value['num']);
            $tmp['serials'] = json_encode($value['serials']);
            $tmp['finished'] = 0;
            $tmp['remark'] = data_get($value, 'remark');
            return $tmp;
        }, $detail);
        $this->model->addAll($saleOutData);
        $detail = data_get($data, 'purchase_detail');
        $type = data_get($data, 'type', PURCHASE_SALE_OUT);
        array_walk($detail, function (&$value, $key, $purchaseId) use ($type) {
            $value['purchase_id'] = $purchaseId;
            $value['num'] = abs($value['total_num']);
            $value['type'] = $type;
            $snId = data_get($value, 'serials');
            unset($value['serials']);
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
     * 销售出库单一级审核
     * @param array $data
     * @return mixed
     */
    public function firstCheck(array $data)
    {
        $id = data_get($data, 'id');
        $purchase = $this->purchase->where('id', $id)->first();
        if (is_null($purchase)) {
            error("销售出库单据不存在");
        }
        if (1 == data_get($data, 'check_status') && empty(data_get($purchase, 'checked_user'))) {
            $user = $this->getCurrentUser();
            $info['user_id'] = data_get($user, 'id');
            $info['username'] = data_get($user, 'username');
            $info['checked_time'] = date('Y-m-d H:i:s', time());
            $checkInfo[] = $info;
            $purchase->checked_user = json_encode($checkInfo);
            $purchase->checked = 2;
        } elseif (1 == data_get($data, 'check_status') && !empty(data_get($purchase, 'checked_user'))) {
            error("销售出库单已审核");
        }
        if (-1 == data_get($data, 'check_status') && !empty(data_get($purchase, 'checked_user'))) {
            if (SECOND_CHECKED == data_get($purchase, 'checked')) {
                error("请先进行二级反审核！");
            }
            $purchase->checked_user = null;
            $purchase->checked = 1;
        } elseif (-1 == data_get($data, 'check_status') && empty(data_get($purchase, 'checked_user'))) {
            error("销售出库单未审核");
        }
        return $purchase->save();
    }

    /**
     * 销售出库单二级审核
     * @param array $data
     */
    public function secondCheck(array $data)
    {
        if (-1 == $data['check_status']) {
            $ret = $this->purchase->where('parent_id', $data['id'])->first();
            if (!is_null($ret)) {
                error("单据已被引单，无法进行反审核");
            }
        }
        $user = $this->getCurrentUser();
        if (is_null($user)) {
            error("用户不存在");
        }
        $purchase = $this->purchase->where('id', data_get($data, 'id'))->first();
        empty($purchase) && error("单据不存在");
        $checked_info = data_get($purchase, 'checked_user') ? json_decode($purchase->checked_user, true) : [];
        if (2 == count($checked_info) && -1 == data_get($data, 'check_status')) {
            $newCheckedInfo[] = $checked_info[0];
            $purchase->checked_user = json_encode($newCheckedInfo);
            $purchase->checked = FIRST_CHECKED;
            $purchase->save();
            $locationId = data_get($purchase, 'location_id');
            $lockNums = $this->lockInv->where('location_id', $locationId)->get()->groupBy('goods_no');
            $detailData = $this->purchaseDetail->where('purchase_id', data_get($data, 'id'))
                ->select('goods_no', 'num')->get();
            foreach ($detailData as $item) {
                if (data_get($lockNums, $item['goods_no'])) {
                    $model = data_get($lockNums, $item['goods_no'])->first();
                    $model->lock_num += $item['num'];
                    $model->save();
                } else {
                    $lockData[] = [
                        'location_id' => $locationId,
                        'location_no' => data_get($this->getLocation($locationId), 'no'),
                        'goods_no' => data_get($item, 'goods_no'),
                        'lock_num' => data_get($item, 'num'),
                    ];
                }
            }
            if (isset($lockData)) {
                $this->lockInv->addAll($lockData);
            }
            $this->model->where('purchase_id', data_get($data, 'id'))->update(['finished' => 0]);
            $this->purchaseDetail->where('purchase_id', data_get($data, 'id'))->update(['finished' => 0]);
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
            $locationId = data_get($purchase, 'location_id');
            $lockNums = $this->lockInv->where('location_id', $locationId)->get()->groupBy('goods_no');
            $detailData = $this->purchaseDetail->where('purchase_id', data_get($data, 'id'))
                ->select('goods_no', 'num')->get();
            foreach ($detailData as $item) {
                if (data_get($lockNums, $item['goods_no'])) {
                    $model = data_get($lockNums, $item['goods_no'])->first();
                    $model->lock_num -= $item['num'];
                    $model->save();
                } else {
                    $lockData[] = [
                        'location_id' => $locationId,
                        'location_no' => data_get($this->getLocation($locationId), 'no'),
                        'goods_no' => data_get($item, 'goods_no'),
                        'lock_num' => -(data_get($item, 'num', 0)),
                    ];
                }
            }
            if (isset($lockData)) {
                $this->lockInv->addAll($lockData);
            }
            $this->model->where('purchase_id', data_get($data, 'id'))->update(['finished' => 1]);
            $this->purchaseDetail->where('purchase_id', data_get($data, 'id'))->update(['finished' => 1]);
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
     * 根据id获取销售出库单详情
     * @param array $ids
     * @return mixed
     */
    public function getSaleOutByIds(array $ids)
    {
        $purchases = $this->purchase->where('id', $ids)->with('saleOuts', 'saleOutRemarks')->get();
        $nums = $this->model->whereIn('purchase_id', $ids)->get()->groupBy(['purchase_id', 'sale_order_id']);
        if ($purchases->count() > 0) {
            $purchases = $purchases->toArray();
            foreach ($purchases as &$ret) {
                $ret['audit_classify'] = $this->getAuditClassify(data_get($ret, 'type'));
                $location = $this->getLocation(data_get($ret, 'location_id'));
                $ret['location_name'] = data_get($location, 'name');
                $ret['checked_info'] = json_decode(data_get($ret, 'checked_user', '[]'), true);
                foreach ($ret['sale_outs'] as $key => &$value) {
                    $value['parent_id'] = data_get($ret, 'sale_out_remarks.' . $key . '.parent_id');
                    $value['parent_no'] = data_get($ret, 'sale_out_remarks.0.sale_ship.no');
                    $value['remark'] = data_get($ret, 'sale_out_remarks.' . $key . '.remark');
                    $value['sale_order_no'] = data_get($ret, 'sale_out_remarks.' . $key . '.sale_order.no');
                    $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                    $value['goods_name'] = data_get($goods, 'name');
                    $value['is_software'] = data_get($goods, 'is_software');
                    $location = $this->getLocation(data_get($value, 'location_id'));
                    $value['location_name'] = data_get($location, 'name');
                    $serials = is_null(data_get($ret, 'sale_out_remarks.' . $key . '.serials'))
                        ? '[]' : data_get($ret, 'sale_out_remarks.' . $key . '.serials');
                    $value['serials'] = json_decode($serials, true);
                    if (!empty($value['serials'])) {
                        $value['num'] = count($value['serials']);
                    } else {
                        $value['num'] = data_get($nums, data_get($ret, 'id') . '.' . data_get($value, 'id') . '.0.num');
                    }
                    $value['unit'] = data_get($goods, 'unit');
                    $value['attribute'] = data_get($goods, 'attribute');
                    unset($value['pivot']);
                }
                unset($ret['sale_out_remarks']);
            }
        }
        return $purchases;
    }

    /**
     * 删除销售出库单
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
        $this->model->where('purchase_id', $id)->delete();
        return $this->purchase->where('id', $id)->delete();
    }
}
