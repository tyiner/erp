<?php

namespace App\Services\Purchase;

use App\Models\Purchase\Purchase;
use App\Models\Purchase\SaleOrder;
use App\Models\Purchase\ShipSaleRelation;
use App\Models\Stock\LockInventory;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Class SaleShipService
 * @package App\Services\Purchase
 */
class SaleShipService extends BaseService
{
    protected $purchase;
    protected $model;
    protected $lockInventory;
    protected $saleOrder;

    public function __construct(
        Purchase $purchase,
        ShipSaleRelation $model,
        LockInventory $lockInventory,
        SaleOrder $saleOrder
    ) {
        $this->model = $model;
        $this->purchase = $purchase;
        $this->lockInventory = $lockInventory;
        $this->saleOrder = $saleOrder;
    }

    /**
     * 根据id获取销售发货单详情列表
     * @param array $ids
     * @return mixed
     */
    public function getSaleShipByIds(array $ids)
    {
        $purchases = $this->purchase->whereIn('id', $ids)->with('saleShips', 'saleShipRemarks')->get();
        $user = $this->getCurrentUser();
        $companyId = data_get($user, 'company_id');
        $storage = $this->getStorageNumByCompanyId($companyId);
        if ($purchases->count() > 0) {
            $purchases = $purchases->toArray();
            foreach ($purchases as &$ret) {
                $ret['audit_classify'] = $this->getAuditClassify(data_get($ret, 'type'));
                $ret['checked_info'] = json_decode(data_get($ret, 'checked_user', '[]'));
                $location = $this->getLocation(data_get($ret, 'location_id'));
                $ret['location_name'] = data_get($location, 'name');
                foreach ($ret['sale_ships'] as $key => &$value) {
                    $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                    $value['parent_id'] = data_get($ret, 'sale_ship_remarks.' . $key . '.parent_id');
                    $value['location_name'] = data_get($location, 'name');
                    $value['goods_name'] = data_get($goods, 'name');
                    $value['unit'] = data_get($goods, 'unit');
                    $value['attribute'] = data_get($goods, 'attribute');
                    $value['is_software'] = data_get($goods, 'is_software');
                    $value['existing_num'] = data_get($storage, data_get($value, 'goods_no'), 0);
                    $value['num'] = data_get($ret, 'sale_ship_remarks.' . $key . '.num');
                    $value['sale_order_no'] = data_get($ret, 'sale_ship_remarks.' . $key . '.sale_order.no');
                    $value['remark'] = data_get($ret, 'sale_ship_remarks.' . $key . '.remark');
                    unset($value['pivot']);
                }
                unset($ret['sale_ship_remarks']);
            }
        }
        return $purchases;
    }

    /**
     * 修改销售发货单状态
     * @param array $data
     * @return mixed
     */
    public function firstCheck(array $data)
    {
        $purchase = $this->purchase->where('id', data_get($data, 'id'))->first();
        is_null($purchase) && error("销售发货单不存在");
        if (1 == data_get($data, 'check_status') && empty(data_get($purchase, 'checked_user'))) {
            $user = $this->getCurrentUser();
            $info['user_id'] = data_get($user, 'id');
            $info['username'] = data_get($user, 'username');
            $info['checked_time'] = date('Y-m-d H:i:s', time());
            $checkInfo[] = $info;
            $purchase->checked_user = json_encode($checkInfo);
            $purchase->checked = FIRST_CHECKED;
            $this->model->where('purchase_id', data_get($data, 'id'))->update(['finished' => 1]);
        } elseif (1 == data_get($data, 'check_status') && !empty(data_get($purchase, 'checked_user'))) {
            error("销售发货单已审核");
        }
        if (-1 == data_get($data, 'check_status') && !empty(data_get($purchase, 'checked_user'))) {
            $purchase->checked_user = null;
            $purchase->checked = UNCHECKED;
            $this->model->where('purchase_id', data_get($data, 'id'))->update(['finished' => 0]);
        } elseif (-1 == data_get($data, 'check_status') && empty(data_get($purchase, 'checked_user'))) {
            error("销售发货单未审核");
        }
        return $purchase->save();
    }

    /**
     * 添加销售发货单
     * @param array $data
     * @return Purchase
     */
    public function saleShipAdd(array $data): Purchase
    {
        $ret = $this->purchase->where('no', data_get($data, 'no'))->first();
        if (!is_null($ret)) {
            error('销售发货单已存在');
        }
        $shipDetails = collect($data['detail'])->groupBy('goods_no');
        $locationId = data_get($data, 'location_id');
        $lockInv = $this->lockInventory->where('location_id', $locationId)->get()->groupBy('goods_no');
        $storageNum = collect($this->getStorageNumByLocationId([$locationId]))->first();
        $numMap = $lockList = [];
        foreach ($shipDetails as $goodsNo => $list) {
            $total = $list->sum('num');
            $usable = data_get($storageNum, $goodsNo, 0) - data_get($lockInv, $goodsNo . '.0.lock_num', 0);
            if ($total > $usable) {
                $goods = $this->getGoodsByNo($goodsNo);
                error("存货名称：" . data_get($goods, 'name') . '的数量不足');
            }
            if (data_get($lockInv, $goodsNo)) {
                $lockInfo = data_get($lockInv, $goodsNo)->first();
                $lockInfo->lock_num += $total;
                $lockList[] = $lockInfo;
            } else {
                $lockData[] = [
                    'location_id' => $locationId,
                    'location_no' => data_get($this->getLocation($locationId), 'no'),
                    'lock_num' => $total,
                    'goods_no' => $goodsNo
                ];
            }
        }
        DB::beginTransaction();
        try {
            $user = $this->getCurrentUser();
            $data['user'] = data_get($user, 'username');
            $data['user_id'] = data_get($user, 'id');
            $data['company_id'] = data_get($user, 'company_id');
            $data['checked'] = UNCHECKED;
            $data['status'] = 1;
            $this->purchase->fill($data)->save();
            $purchaseId = $this->purchase->id;
            $detail = collect($data['detail'])->map(function ($item, $key) use ($purchaseId) {
                $temp = [];
                $temp['sale_order_id'] = data_get($item, 'id');
                $temp['purchase_id'] = $purchaseId;
                $temp['parent_id'] = data_get($item, 'parent_id');
                $temp['num'] = data_get($item, 'num');
                $temp['finished'] = 0;
                $temp['remark'] = data_get($item, 'remark');
                return $temp;
            });
            $ret = $this->model->addAll($detail->toArray());
            if (!$ret) {
                DB::rollBack();
                error("销售发货关联关系添加失败");
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            error("销售发货单添加失败");
        }
        foreach ($lockList as $lockModel) {
            $lockModel->save();
        }
        if (isset($lockData)) {
            $this->lockInventory->addAll($lockData);
        }
        return $this->purchase;
    }

    /**
     * 更新销售发货单
     * @param array $data
     * @return bool
     */
    public function update(array $data): bool
    {
        $purchaseId = data_get($data, 'id');
        $purchase = $this->purchase->where('id', $purchaseId)->first();
        if (empty($purchase)) {
            error("销售发货单不存在");
        }
        if (data_get($data, 'no') != data_get($purchase, 'no')) {
            error("销售发货单编号不能更改");
        }
        if (!empty(data_get($purchase, 'checked_user'))) {
            error("单据已审核无法更改");
        }
        $purchase->update($data);
        $oldData = $this->model->where('purchase_id', $purchaseId)->get();
        $locationId = data_get($data, 'location_id');
        $lockInv = $this->lockInventory->where('location_id', $locationId)->get()->groupBy('goods_no');
        $oldSaleOrderIds = array_column($data['detail'], 'id');
        $oldSaleOrder = $this->saleOrder->whereIn('id', $oldSaleOrderIds)->get();
        $oldNumMap = array_column($oldData->toArray(), 'num', 'sale_order_id');
        foreach ($oldSaleOrder as $value) {
            $goodsNo = data_get($value, 'goods_no');
            $id = data_get($value, 'id');
            if (data_get($lockInv, $goodsNo)) {
                $lockInfo = data_get($lockInv, $goodsNo)->first();
                $lockInfo->lock_num -= $oldNumMap[$id];
                $lockInfo->save();
            }
        }
        $this->model->where('purchase_id', $purchaseId)->delete();
        $detail = collect($data['detail'])->map(function ($item, $key) use ($purchaseId) {
            $temp = [];
            $temp['sale_order_id'] = data_get($item, 'id');
            $temp['purchase_id'] = $purchaseId;
            $temp['parent_id'] = data_get($item, 'parent_id');
            $temp['num'] = data_get($item, 'num');
            $temp['finished'] = 0;
            $temp['remark'] = data_get($item, 'remark');
            return $temp;
        });
        $ret = $this->model->addAll($detail->toArray());
        $locationId = data_get($data, 'location_id');
        $lockInv = $this->lockInventory->where('location_id', $locationId)->get()->groupBy('goods_no');
        $saleOrderIds = array_column($data['detail'], 'id');
        $saleOrder = $this->saleOrder->whereIn('id', $saleOrderIds)->get();
        $numMap = array_column($data['detail'], 'num', 'id');
        foreach ($saleOrder as $value) {
            $goodsNo = data_get($value, 'goods_no');
            $id = data_get($value, 'id');
            if (data_get($lockInv, $goodsNo)) {
                $lockInfo = data_get($lockInv, $goodsNo)->first();
                $lockInfo->lock_num += $numMap[$id];
                $lockInfo->save();
            } else {
                $lockData[] = [
                    'location_id' => $locationId,
                    'location_no' => data_get($this->getLocation($locationId), 'no'),
                    'lock_num' => $numMap[$id],
                    'goods_no' => $goodsNo
                ];
            }
        }
        if (isset($lockData)) {
            $this->lockInventory->addAll($lockData);
        }
        return $ret;
    }

    /**
     * 根据id删除销售发货单
     * @param int $id
     * @return bool|null
     * @throws \Exception
     */
    public function delete(int $id): ?bool
    {
        $purchase = $this->purchase->where('id', $id)->first();
        $locationId = data_get($purchase, 'location_id');
        $numMap = $this->model->where('purchase_id', $id)->get()->pluck('num', 'sale_order_id')->toArray();
        $saleOrderIds = array_keys($numMap);
        $goodsNos = $this->saleOrder->whereIn('id', $saleOrderIds)->select(['goods_no', 'id'])->get();
        $lockInv = $this->lockInventory->where('location_id', $locationId)->get()->groupBy('goods_no');
        foreach ($goodsNos as $value) {
            $goodsNo = data_get($value, 'goods_no');
            $saleOrderId = data_get($value, 'id');
            if (data_get($lockInv, $goodsNo)) {
                $lockInfo = data_get($lockInv, $goodsNo)->first();
                $lockInfo->lock_num -= $numMap[$saleOrderId];
                $lockInfo->save();
            } else {
                $lockData[] = [
                    'location_id' => $locationId,
                    'location_no' => data_get($this->getLocation($locationId), 'no'),
                    'lock_num' => -($numMap[$saleOrderId]),
                    'goods_no' => $goodsNo
                ];
            }
        }
        if (isset($lockData)) {
            $this->lockInventory->addAll($lockData);
        }
        if (is_null($purchase)) {
            error("销售发货单不存在");
        }
        if (!empty(data_get($purchase, 'checked_user'))) {
            error("销售发货单已经被审核");
        }
        $this->model->where('purchase_id', $id)->delete();
        return $purchase->delete();
    }

    /**
     * 获取销售发货单列表
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function getSaleShipList(array $data): LengthAwarePaginator
    {
        $audit = $this->getAuditClassify(data_get($data, 'type'));
        $limit = data_get($data, 'limit', 20);
        $query = DB::table('purchases')->join(
            'ship_sale_relation',
            function ($join) {
                return $join->on('purchases.id', '=', 'ship_sale_relation.purchase_id')
                    ->whereNull('purchases.deleted_at')->whereNull('ship_sale_relation.deleted_at');
            }
        );
        $query = $query->join(
            'sale_orders',
            function ($join) {
                return $join->on('ship_sale_relation.sale_order_id', '=', 'sale_orders.id');
            }
        );
        $purchases = $query->where(['purchases.type' => PURCHASE_SALE_SHIP])
            ->orderByDesc('purchases.id')->paginate($limit);
        if ($purchases->total() > 0) {
            $parentsId = array_unique(array_column($purchases->items(), 'parent_id'));
            $purchaseIds = array_unique(array_column($purchases->items(), 'purchase_id'));
            $nums = $this->model->whereIn('purchase_id', $purchaseIds)->get()->groupBy([
                'purchase_id',
                'sale_order_id'
            ]);
            $parentsNo = $this->purchase->whereIn('id', $parentsId)->get(['id', 'no'])->groupBy('id');
            $remarkMap = $this->purchase->whereIn('id', $purchaseIds)->get(['remark', 'id'])->pluck('remark', 'id');
            foreach ($purchases->items() as &$item) {
                $location = $this->getLocation(data_get($item, 'location_id'));
                $item->location_name = data_get($location, 'name');
                $goods = $this->getGoodsByNo(data_get($item, 'goods_no'));
                $item->goods_name = data_get($goods, 'name');
                $item->audit_classify = $audit;
                $item->remark = data_get($remarkMap, $item->purchase_id, '');
                $item->num = data_get($nums, $item->purchase_id . '.' . $item->sale_order_id . '.0.num');
                $item->unit = data_get($goods, 'unit');
                $item->sale_order_no = data_get($parentsNo, data_get($item, 'parent_id') . '.0.no');
                $item->attribute = data_get($goods, 'attribute');
                $department = $this->getDepartment(data_get($item, 'department_id'));
                $item->department_name = data_get($department, 'name');
            }
        }
        return $purchases;
    }
}
