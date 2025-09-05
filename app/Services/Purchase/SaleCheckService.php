<?php

namespace App\Services\Purchase;

use App\Models\Purchase\BackSaleRelation;
use App\Models\Purchase\OutSaleRelation;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\SnCode;
use App\Services\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Class SaleCheckService
 * @package App\Services\Purchase
 */
class SaleCheckService extends BaseService
{
    private $snCode;
    private $purchase;
    private $outSaleRelation;
    private $backSaleRelation;

    private $stockReduce = [
        PURCHASE_SALE_OUT,  //销售出库
        PURCHASE_STORE_OUT, //采购入库红字
        PURCHASE_OTHER_OUT, //其他出库
        PURCHASE_STOCK_OUT, //备货出库
        PURCHASE_TRANSFER,  //调拨出库
    ];

    public function __construct(
        SnCode $snCode,
        Purchase $purchase,
        OutSaleRelation $outSaleRelation,
        BackSaleRelation $backSaleRelation
    ) {
        $this->snCode = $snCode;
        $this->purchase = $purchase;
        $this->outSaleRelation = $outSaleRelation;
        $this->backSaleRelation = $backSaleRelation;
    }

    /**
     * 校验销售出库单
     * @param array $data
     * @param int $locationId
     * @return array
     */
    public function checkSaleOut(array $data, int $locationId): array
    {
        $detail = collect($data);
        $serials = $detail->pluck('serials')->flatten()->toArray();
        $nums = $detail->groupBy('goods_no');
        $count = [];
        foreach ($nums as $key => $value) {
            $total = $value->pluck('num')->flatten()->sum();
            $count[$key] = $total;
        }
        $mergeIds = $this->getCount($serials);
        if (empty($mergeIds->toArray())) {
            error("商品sn码信息不存在");
        }
        $storeNums = $mergeIds->groupBy('goods_no');
        foreach ($storeNums->toArray() as $key => $value) {
            if (count($value) !== data_get($count, $key)) {
                error('存货编号为：' . $key . '的商品数量不正确');
            }
        }
        /*$snId = $mergeIds->pluck('id')->flatten()->toArray();
        $ret = $this->getCurrent($snId, $locationId);
        if ($ret->count() == 0) {
            $goodsNo = array_key_first($storeNums->toArray());
            $goods = $this->getGoodsByNo($goodsNo);
            error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码未入库");
        }
        $count = $this->checkStore($ret);
        if ($count != count($snId)) {
            error("部分商品sn码或箱码未入库");
        }*/
        $list = $mergeIds->groupBy('goods_no');
        $keys = $list->keys()->toArray();
        $items = [];
        foreach ($keys as $key) {
            $item['goods_no'] = $key;
            $item['total_num'] = -(data_get($list, $key)->count());
            $item['num'] = data_get($list, $key)->count();
            $item['location_id'] = $locationId;
            $item['serials'] = data_get($list, $key)->pluck('id')->flatten()->toArray();
            $items[] = $item;
        }
        return $items;
    }

    /**
     * 校验销售单据sn码信息
     * @param array $data
     * @param int $locationId
     * @return array
     */
    public function checkSn(array $data, int $locationId): array
    {
        $detail = collect($data);
        $serials = $detail->pluck('serials')->flatten()->toArray();
        $nums = $detail->groupBy('goods_no');
        $count = [];
        foreach ($nums as $key => $value) {
            $total = $value->pluck('num')->flatten()->sum();
            $count[$key] = $total;
        }
        $mergeIds = $this->getCount($serials);
        if (empty($mergeIds->toArray())) {
            error("商品sn码信息不存在");
        }
        $storeNums = $mergeIds->groupBy('goods_no');
        foreach ($storeNums->toArray() as $key => $value) {
            if (count($value) !== data_get($count, $key)) {
                error('存货编号为：' . $key . '的商品数量不正确');
            }
        }
        /*$snId = $mergeIds->pluck('id')->flatten()->toArray();
        $ret = $this->getCurrent($snId, $locationId);
        if ($ret->count() == 0) {
            $goodsNo = array_key_first($storeNums->toArray());
            $goods = $this->getGoodsByNo($goodsNo);
            error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码未入库");
        }
        $count = $this->checkStore($ret);
        if ($count != count($snId)) {
            error("部分商品sn码或箱码未入库");
        }*/
        $list = $mergeIds->groupBy('goods_no');
        $keys = $list->keys()->toArray();
        $items = [];
        foreach ($keys as $key) {
            $item['goods_no'] = $key;
            $item['total_num'] = -(data_get($list, $key)->count());
            $item['num'] = data_get($list, $key)->count();
            $item['location_id'] = $locationId;
            $item['ids'] = data_get($list, $key)->pluck('id')->flatten()->toArray();
            $items[] = $item;
        }
        return $items;
    }

    /**
     * 校验销售退货
     * @param array $data
     * @param int $locationId
     * @return array
     */
    public function checkSaleBack(array $data, int $locationId): array
    {
        foreach ($data as $value) {
            $shipIds = $this->backSaleRelation->where('purchase_id', data_get($value, 'parent_id'))
                ->get('parent_id')->pluck('parent_id')->unique()->flatten();
            $ret = $this->outSaleRelation->where([
                'sale_order_id' => data_get($value, 'id')
            ])->whereIn('parent_id', $shipIds)
                ->select('serials')->first();
            if (is_null($ret)) {
                error("sn码:" . implode(';', $value['serials']) . "未销售出库");
            }
            $serials = json_decode($ret['serials']);
            foreach ($value['serials'] as $serial) {
                if (!in_array($serial, $serials)) {
                    error("sn码：" . $serial . "未销售出库");
                }
            }
        }
        $serials = array_column($data, 'serials');
        $sns = collect($serials)->flatten()->toArray();
        $serials = $this->snCode->whereIn('box', $sns)->orWhereIn('sn', $sns)->get()->groupBy('goods_no');
        $purchaseDetails = $purchaseDetail = [];
        foreach ($serials as $serial) {
            $purchaseDetail['location_id'] = $locationId;
            $purchaseDetail['goods_no'] = data_get($serial, '0.goods_no');
            $purchaseDetail['total_num'] = $serial->count();
            $purchaseDetail['num'] = $serial->count();
            $purchaseDetail['ids'] = $serial->pluck('id')->flatten()->toArray();
            $purchaseDetails[] = $purchaseDetail;
        }
        return $purchaseDetails;
    }

    /**
     * 校验数量
     * @param $current
     * @return int
     */
    private function checkStore($current): int
    {
        $count = 0;
        foreach ($current->groupBy('type') as $type => $item) {
            if (in_array($type, $this->stockReduce)) {
                $count -= $item->count();
            } else {
                $count += $item->count();
            }
        }
        return $count;
    }

    /**
     * 根据提交的箱码或sn码信息查询id
     * @param array $serials
     * @return mixed
     */
    private function getCount(array $serials)
    {
        $boxIds = $this->snCode->whereIn('box', $serials)->get(['id', 'goods_no']);
        $singleIds = $this->snCode->whereIn('sn', $serials)->get(['id', 'goods_no']);
        return $singleIds->merge($boxIds);
    }

    /**
     * 获取当前sn信息出入库情况
     *
     * @param array $ids
     * @param int $locationId
     * @return Collection
     */
    private function getCurrent(array $ids, int $locationId): Collection
    {
        $current = DB::table('purchase_detail')->join(
            'pur_sn_relation',
            function ($join) use ($ids, $locationId) {
                $join->on('purchase_detail.id', '=', 'pur_sn_relation.purchase_detail_id')
                    ->whereIn('pur_sn_relation.sn_id', $ids)->where('purchase_detail.location_id', $locationId)
                    ->whereNull('pur_sn_relation.deleted_at');
            }
        )->get();
        $unfinishedPurchases = $current->where('finished', 0)
            ->pluck('purchase_id')->unique();
        if ($unfinishedPurchases->count() > 0) {
            $order = $this->purchase->whereIn('id', $unfinishedPurchases)->select('no')->get();
            error('关联表单：' . $order->pluck('no')->unique()->join(',') . ' 的表单待审核');
        }
        return $current;
    }
}
