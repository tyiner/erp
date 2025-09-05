<?php

namespace App\Services\Purchase;

use App\Models\Purchase\BackSaleRelation;
use App\Models\Purchase\OutSaleRelation;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseDetail;
use App\Models\Purchase\PurchaseSaleOrderRelation;
use App\Models\Purchase\RedSaleRelation;
use App\Models\Purchase\SaleOrder;
use App\Models\Purchase\ShipSaleRelation;
use App\Services\BaseService;

/**
 * 校验引单关闭开启
 * Class CheckCompleteService
 * @package App\Services\Purchase
 */
class CheckCompleteService extends BaseService
{
    protected $purchase;
    protected $purchaseDetail;
    protected $saleOrder;
    protected $shipSaleRelation;
    protected $purSaleOrderRelation;
    protected $outSaleRelation;
    protected $backSaleRelation;
    protected $redSaleRelation;

    public function __construct(
        Purchase $purchase,
        PurchaseDetail $purchaseDetail,
        SaleOrder $saleOrder,
        ShipSaleRelation $shipSaleRelation,
        PurchaseSaleOrderRelation $purSaleOrderRelation,
        OutSaleRelation $outSaleRelation,
        BackSaleRelation $backSaleRelation,
        RedSaleRelation $redSaleRelation
    ) {
        $this->purchase = $purchase;
        $this->purchaseDetail = $purchaseDetail;
        $this->saleOrder = $saleOrder;
        $this->shipSaleRelation = $shipSaleRelation;
        $this->purSaleOrderRelation = $purSaleOrderRelation;
        $this->outSaleRelation = $outSaleRelation;
        $this->backSaleRelation = $backSaleRelation;
        $this->redSaleRelation = $redSaleRelation;
    }

    /**
     * 校验是否需要关闭引单
     * @param array $data
     */
    public function check(array $data)
    {
        $type = data_get($data, 'type');
        switch ($type) {
            case PURCHASE_ARRIVAL:
                $this->purchaseArrival($data);
                break;
            case PURCHASE_STORE_IN:
                $this->purchaseStoreIn($data);
                break;
            case PURCHASE_STOCK_SHIP:
                $this->purchaseStockShip($data);
                break;
            case PURCHASE_STOCK_OUT:
                $this->purchaseStockOut($data);
                break;
            case PURCHASE_STOCK_BACK:
                $this->purchaseStockBack($data);
                break;
            case PURCHASE_STOCK_RED:
                $this->purchaseStockRed($data);
                break;
            case PURCHASE_SALE_SHIP:
                $this->purchaseSaleShip($data);
                break;
            case PURCHASE_SALE_OUT:
                $this->purchaseSaleOut($data);
                break;
            case PURCHASE_SALE_BACK:
                $this->purchaseSaleBack($data);
                break;
            case PURCHASE_SALE_RED:
                $this->purchaseSaleRed($data);
                break;
            case PURCHASE_OTHER_RED:
                $this->purchaseOtherRed($data);
                break;
        }
    }

    /**
     * 其它出库单红字引其它出库单
     * @param array $data
     */
    private function purchaseOtherRed(array $data)
    {
        $id = data_get($data, 'parent_id');
        $this->purchase->where('id', $id)->update(['status' => -1]);
    }

    /**
     * 销售退货单引单关闭
     * @param array $data
     */
    private function purchaseSaleRed(array $data)
    {
        $ids = collect($data['detail'])->pluck('parent_id')->unique()->flatten()->toArray();
        $this->purchase->whereIn('id', $ids)->update(['status' => -1]);
    }

    /**
     * 销售退货单引单关单
     * @param array $data
     */
    private function purchaseSaleBack(array $data)
    {
        $saleShipIds = array_unique(array_column($data['detail'], 'parent_id'));
        $saleShipOrder = $this->shipSaleRelation->whereIn('purchase_id', $saleShipIds)->get()->groupBy('purchase_id');
        $saleBackOrder = $this->backSaleRelation->whereIn('parent_id', $saleShipIds)->get()->groupBy([
            'parent_id',
            'sale_order_id'
        ]);
        $purIds = [];
        foreach ($saleShipOrder as $k => $v) {
            foreach ($v as $item) {
                $total = data_get($item, 'num');
                $num = data_get($saleBackOrder, $k . '.' . data_get($item, 'sale_order_id'));
                if (is_null($num)) {
                    $exist = 0;
                } else {
                    $exist = $num->sum('num');
                }
                if ($total != $exist) {
                    break(2);
                }
            }
            $purIds[] = $k;
        }
        if (!empty($purIds)) {
            $this->purchase->whereIn('id', $purIds)->update(['status' => -2]);
        }
    }

    /**
     * 销售出库单引用销售发货单关单校验
     * @param array $data
     */
    private function purchaseSaleOut(array $data)
    {
        $ids = collect($data['detail'])->pluck('parent_id')->unique()->flatten()->toArray();
        $this->purchase->whereIn('id', $ids)->update(['status' => -1]);
    }

    /**
     * 销售发货单引用销售订单关单校验
     * @param array $data
     */
    private function purchaseSaleShip(array $data)
    {
        //$map = array_column($data['detail'], 'num', 'id');
        $ids = array_column($data['detail'], 'id');
        $purchaseIds = $this->purSaleOrderRelation->whereIn('sale_order_id', $ids)
            ->get()->groupBy('purchase_id')->toArray();
        $purchaseIds = array_keys($purchaseIds);
        $saleOrderDetails = $this->purSaleOrderRelation->whereIn('purchase_id', $purchaseIds)
            ->get();
        $ids = $saleOrderDetails->pluck('sale_order_id')->flatten()->toArray();
        $details = $this->saleOrder->whereIn('id', $ids)->get()->groupBy('id');
        $exists = $this->shipSaleRelation->whereIn('sale_order_id', $ids)->get()->groupBy('sale_order_id');
        foreach ($details as $key => $value) {
            $total = data_get($value, '0.num');
            $exist = data_get($exists, $key);
            if (is_null($exist)) {
                $existNum = 0;
            } else {
                $existNum = $exist->sum("num");
            }
            if ($total == $existNum /*+ data_get($map, $key)*/) {
                $complete[] = $key;
            }
        }
        $purchaseGroup = $saleOrderDetails->groupBy('purchase_id')->toArray();
        $updateDatas = [];
        foreach ($purchaseGroup as $k => $v) {
            foreach ($v as $item) {
                $saleOrderIds[] = $item['sale_order_id'];
            }
            if ((isset($complete) && is_array($complete)) && is_array($saleOrderIds)) {
                $ret = array_intersect($complete, $saleOrderIds);
                sort($ret);
                sort($saleOrderIds);
                if ($ret == $saleOrderIds) {
                    $updateDatas[] = $k;
                }
            }
        }
        if (!is_null($updateDatas)) {
            $this->purchase->whereIn('id', $updateDatas)->update(['status' => -1]);
        }
    }

    /**
     * 采购订单被引用关单校验
     * @param array $data
     */
    private function purchaseArrival(array $data)
    {
        $id = data_get($data, 'parent_id');
        $purchase = $this->purchaseDetail->where([
            'purchase_id' => $id,
            'type' => PURCHASE_PLAN,
        ])->get()->groupBy('goods_no');
        $details = $this->purchaseDetail->where([
            'source_id' => $id,
            'type' => PURCHASE_ARRIVAL,
        ])->get()->groupBy('goods_no');
        //$map = array_column($data['detail'], 'num', 'goods_no');
        foreach ($purchase as $key => $value) {
            $total = data_get($value, '0.total_num');
            if (is_null(data_get($details, $key))) {
                $exists = 0;
            } else {
                $exists = data_get($details, $key)->sum('num');
            }
            //$submit = data_get($map, $key);
            if ($total != $exists /*+ $submit*/) {
                return;
            }
        }
        $this->purchase->where('id', $id)->update(['status' => -1]);
    }

    /**
     * 采购到货单被引用关单
     * @param array $data
     */
    private function purchaseStoreIn(array $data)
    {
        $id = data_get($data, 'parent_id');
        $this->purchase->where('id', $id)->update(['status' => -1]);
    }

    /**
     * 删除单据，更改引单状态
     * @param array $ids
     */
    public function deleteCheck(array $ids)
    {
        $purchaseDetail = $this->purchase->whereIn('id', $ids)->get(['parent_id', 'type'])->reject(function (
            $value,
            $key
        ) {
            return data_get($value, 'parent_id') == 0;
        });
        $ids = $purchaseDetail->pluck('parent_id')->flatten()->toArray();
        $purchase = $this->purchase->whereIn('id', $ids)->first();
        $currentStatus = data_get($purchase, 'status');
        if (-1 == $currentStatus) {
            $this->purchase->whereIn('id', $ids)->update(['status' => 1]);
        }
        if (-2 == $currentStatus) {
            $this->purchase->whereIn('id', $ids)->update(['status' => -1]);
        }
    }

    /**
     * 销售单据删除，引单进行开启
     * @param int $id
     */
    public function deleteSaleCheck(int $id)
    {
        $purchase = $this->purchase->where('id', $id)->withTrashed()->first();
        $type = data_get($purchase, 'type');
        switch ($type) {
            case PURCHASE_SALE_SHIP:
                $ids = $this->shipSaleRelation->where('purchase_id', $id)->withTrashed()
                    ->get()->pluck('parent_id')->flatten()->toArray();
                break;
            case PURCHASE_SALE_OUT:
                $ids = $this->outSaleRelation->where('purchase_id', $id)->withTrashed()
                    ->get()->pluck('parent_id')->flatten()->toArray();
                break;
            case PURCHASE_SALE_BACK:
                $ids = $this->backSaleRelation->where('purchase_id', $id)->withTrashed()
                    ->get()->pluck('parent_id')->flatten()->toArray();
                break;
            case PURCHASE_SALE_RED:
                $ids = $this->redSaleRelation->where('purchase_id', $id)->withTrashed()
                    ->get()->pluck('parent_id')->flatten()->toArray();
                break;
        }
        if (!empty($ids)) {
            $purchase = $this->purchase->whereIn('id', $ids)->first();
            $currentStatus = data_get($purchase, 'status');
            if (-1 == $currentStatus) {
                $this->purchase->whereIn('id', $ids)->update(['status' => 1]);
            }
            if (-2 == $currentStatus) {
                $this->purchase->whereIn('id', $ids)->update(['status' => -1]);
            }
        }
    }

    /**
     * 备货发货单引单关单
     * @param array $data
     */
    private function purchaseStockShip(array $data)
    {
        $purchase = $this->purchaseDetail->
        where('purchase_id', data_get($data, 'parent_id'))->get()->groupBy('goods_no');
        $exist = $this->purchaseDetail->where([
            'source_id' => data_get($data, 'parent_id'),
            'type' => PURCHASE_STOCK_SHIP
        ])->get()->groupBy('goods_no');
        //$map = array_column(data_get($data, 'detail'), 'num', 'goods_no');
        foreach ($purchase as $key => $value) {
            $total = data_get($value, '0.num');
            if (is_null(data_get($exist, $key))) {
                $existNum = 0;
            } else {
                $existNum = data_get($exist, $key)->sum('num');
            }
            //$num = data_get($map, $key, 0);
            if ($total != $existNum /*+ $num*/) {
                return;
            }
        }
        $this->purchase->where('id', data_get($data, 'parent_id'))->update(['status' => -1]);
    }

    /**
     * 备货出库单引单关单
     * @param array $data
     */
    private function purchaseStockOut(array $data)
    {
        $purchaseId = data_get($data, 'parent_id');
        $this->purchase->where('id', $purchaseId)->update(['status' => -1]);
    }

    /**
     * 备货退货单引单关闭
     * @param array $data
     */
    private function purchaseStockBack(array $data)
    {
        $purchaseId = data_get($data, 'parent_id');
        //$map = array_column($data['detail'], 'num', 'goods_no');
        $purchase = $this->purchaseDetail->where('purchase_id', $purchaseId)
            ->get()->groupBy('goods_no');
        $detail = $this->purchaseDetail->where(['source_id' => $purchaseId, 'type' => PURCHASE_STOCK_BACK])
            ->get()->groupBy('goods_no');
        foreach ($purchase as $key => $values) {
            $total = data_get($values, '0.num');
            if (is_null(data_get($detail, $key))) {
                $exist = 0;
            } else {
                $exist = data_get($detail, $key)->sum('num');
            }
            //$num = data_get($map, $key, 0);
            if ($total != $exist /*+ $num*/) {
                return;
            }
        }
        $this->purchase->where('id', $purchaseId)->update(['status' => -2]);
    }

    /**
     * 备货出库单红字引单关闭
     * @param array $data
     */
    private function purchaseStockRed(array $data)
    {
        $purchaseId = data_get($data, 'parent_id');
        $this->purchase->where('id', $purchaseId)->update(['status' => -1]);
    }
}
