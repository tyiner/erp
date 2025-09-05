<?php

namespace App\Services\Purchase;

use App\Models\Goods;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\SnCode;
use App\Services\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Class PurCheckService
 *
 * @package App\Services\Stock
 */
class PurCheckService extends BaseService
{
    private $goods;
    private $snCode;
    private $purchase;

    private $stockReduce = [
        PURCHASE_SALE_OUT,  //销售出库
        PURCHASE_STORE_OUT, //采购入库红字
        PURCHASE_OTHER_OUT, //其他出库
        PURCHASE_STOCK_OUT, //备货出库
        PURCHASE_TRANSFER,  //调拨出库
    ];

    public function __construct(Goods $goods, SnCode $snCode, Purchase $purchase)
    {
        $this->goods = $goods;
        $this->snCode = $snCode;
        $this->purchase = $purchase;
    }
    //库存数量减少

    /**
     * 采购其他出库退货校验
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function purOtherOutback(array $data): array
    {
        return $this->purOtherIn($data);
    }

    /**
     * 根据提交数据详情校验sn码信息数量
     * @param array $data
     * @return mixed
     */
    public function getDetailSnInfo(array $data): array
    {
        foreach ($data as &$value) {
            $snIds = $this->snCode->whereIn('box', $value['serials'])->orWhereIn('sn', $value['serials'])
                ->get('id')->pluck('id')->flatten()->toArray();
            if ($value['num'] != count($snIds)) {
                error('存货编号为：' . data_get($value, 'goods_no') . "的存货数量不正确");
            }
            $value['ids'] = $snIds;
        }
        return $data;
    }

    /**
     * 校验调拨单sn码信息
     * @param array $data
     * @return array|mixed
     * @throws \Exception
     */
    public function purTransfer(array $data): array
    {
        $detail = data_get($data, 'detail');
        $type = data_get($data, 'type');
        $locationId = data_get($data, 'locationId');
        $transferOutDetail = $this->purTransferReduce(compact('detail', 'type', 'locationId'));
        $locationId = data_get($data, 'receivingLocationId');
        $transferInDetail = $this->purTransferAdd(compact('detail', 'type', 'locationId'));
        $detail = array_merge($transferInDetail['detail'], $transferOutDetail['detail']);
        return $detail;
    }

    /**
     * 校验订单引入单
     *
     * @param array $data
     */
    public function checkStockParent(array $data)
    {
        if (STOCK_ARRIVAL == data_get($data, 'type')) {
            $parent = $this->purchase->where('id', data_get($data, 'parent_id'))->first();
            if (!is_null($parent) && STOCK_PLAN == $parent->type) {
                return;
            }
            error("引入订单类型不是备货计划单");
        }
        if (STOCK_STORE_IN == data_get($data, 'type')) {
            $parent = $this->purchase->where('id', data_get($data, 'parent_id'))->first();
            if (!is_null($parent) && STOCK_ARRIVAL == $parent->type) {
                return;
            }
            error("引入订单类型不是备货到货单");
        }
    }

    /**
     * 采购入库
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function purStoreAdd(array $data): array
    {
        foreach ($data['detail'] as &$value) {
            $value['type'] = $data['type'];
            $postSn = data_get($value, 'serials');
            $this->obtainNum($value);
            $totalNum = $value['total_num'];
            $value['ids'] = $this->getSnInfo(compact('postSn', 'totalNum'));
            $current = $this->getCurrent($value['ids'], data_get($data, 'locationId'));
            if ($current->count() == 0) {
                continue;
            }
            $count = $this->checkStore($current);
            if ($count > 0) {
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码存在已入库");
            }
            if ($count < 0) {
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码库存数量不正确");
            }
        }
        return $data;
    }

    /**
     * 校验调拨入库单
     *
     * @param array $data
     * @return mixed
     * @throws \Exception
     */
    public function purTransferAdd(array $data): array
    {
        foreach ($data['detail'] as &$value) {
            $value['location_id'] = data_get($data, 'locationId');
            $value['type'] = $data['type'];
            $postSn = $value['serials'];
            $this->obtainNum($value);
            $value['total_num'] = abs($value['total_num']);
            $totalNum = $value['total_num'];
            $value['ids'] = $this->getSnInfo(compact('postSn', 'totalNum'));
            /*$current = $this->getCurrent($value['ids'], data_get($data, 'locationId'));
            if ($current->count() == 0) {
                continue;
            }*/
            /*$count = $this->checkStore($current);
            if ($count > 0) {
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码存在已入库");
            }
            if ($count < 0) {
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码库存数量不正确");
            }*/
        }
        return $data;
    }

    /**
     * 其他入库
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function purOtherIn(array $data): array
    {
        foreach ($data['detail'] as &$value) {
            $value['type'] = $data['type'];
            $postSn = $value['serials'];
            $this->obtainNum($value);
            $totalNum = $value['total_num'];
            $value['ids'] = $this->getSnInfo(compact('postSn', 'totalNum'));
            $current = $this->getCurrent($value['ids'], data_get($data, 'locationId'));
            if ($current->count() == 0) {
                continue;
            }
            $count = $this->checkStore($current);
            if ($count > 0) {
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码存在已入库");
            }
            if ($count < 0) {
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码库存数量不正确");
            }
        }
        return $data;
    }

    /**
     * 其他出库校验
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function purOtherOut(array $data): array
    {
        foreach ($data['detail'] as &$value) {
            $value['type'] = $data['type'];
            $postSn = $value['serials'];
            $this->obtainNum($value);
            $totalNum = $value['total_num'];
            $value['ids'] = $this->getSnInfo(compact('postSn', 'totalNum'));
            $current = $this->getCurrent($value['ids'], data_get($data, 'locationId'));
            if ($current->count() == 0) {
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码未入库");
            }
            $count = $this->checkStore($current);
            if ($count < abs($value['total_num'])) {
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码存在未入库");
            }
            if ($count > abs($value['total_num'])) {
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码库存数量不正常");
            }
        }
        return $data;
    }

    /**
     * 备货退货入库
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function purStockBack(array $data): array
    {
        foreach ($data['detail'] as &$value) {
            $value['type'] = $data['type'];
            $postSn = data_get($value, 'serials');
            $this->obtainNum($value);
            $totalNum = $value['total_num'];
            $value['ids'] = $this->getSnInfo(compact('postSn', 'totalNum'));
            $current = $this->getCurrent($value['ids'], data_get($data, 'locationId'));
            if ($current->count() == 0) {
                continue;
            }
            $count = $this->checkStore($current);
            if ($count > 0) {
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码存在已入库");
            }
            if ($count < 0) {
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码库存数量不正确");
            }
        }
        return $data;
    }

    /**
     * 校验备货出库
     *
     * @param array $data
     * @return array|bool
     * @throws \Exception
     */
    public function purStockOut(array $data): array
    {
        foreach ($data['detail'] as &$value) {
            $value['type'] = $data['type'];
            $postSn = $value['serials'];
            $this->obtainNum($value);
            $totalNum = $value['total_num'];
            $value['ids'] = $this->getSnInfo(compact('postSn', 'totalNum'));
            $current = $this->getCurrent($value['ids'], data_get($data, 'locationId'));
            if ($current->count() == 0) {
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码未入库");
            }
            $count = $this->checkStore($current);
            if ($count < abs($value['total_num'])) {
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码存在未入库");
            }
            if ($count > abs($value['total_num'])) {
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码库存数量不正常");
            }
        }
        return $data;
    }

    /**
     * 校验采购退货出库
     *
     * @param array $data
     * @return array|bool
     * @throws \Exception
     */
    public function purStoreReduce(array $data): array
    {
        foreach ($data['detail'] as &$value) {
            $value['type'] = $data['type'];
            $postSn = $value['serials'];
            $this->obtainNum($value);
            $totalNum = $value['total_num'];
            $value['ids'] = $this->getSnInfo(compact('postSn', 'totalNum'));
            $current = $this->getCurrent($value['ids'], data_get($data, 'locationId'));
            if ($current->count() == 0) {
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码未入库");
            }
            $count = $this->checkStore($current);
            if ($count < abs($value['total_num'])) {
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码存在未入库");
            }
            if ($count > abs($value['total_num'])) {
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码库存数量不正常");
            }
        }
        return $data;
    }

    /**
     * 校验调拨出库
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function purTransferReduce(array $data): array
    {
        foreach ($data['detail'] as &$value) {
            $value['location_id'] = data_get($data, 'locationId');
            $value['type'] = $data['type'];
            $postSn = $value['serials'];
            $this->obtainNum($value);
            $totalNum = $value['total_num'];
            $value['ids'] = $this->getSnInfo(compact('postSn', 'totalNum'));
            /*$current = $this->getCurrent($value['ids'], data_get($data, 'locationId'));
            if ($current->count() == 0) {
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码未入库");
            }*/
            array_push($this->stockReduce, PURCHASE_TRANSFER);
            /*$count = $this->checkStore($current);
            if ($count < abs($value['total_num'])) {
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码存在部分未入库");
            }*/
            /*if ($count > abs($value['total_num'])) {
                $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                error("商品名称为：" . data_get($goods, 'name') . " 的商品sn码或箱码库存数量不正常");
            }*/
        }
        return $data;
    }

    /**
     * 校验入库数量
     *
     * @param  $current
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
     * @param  $value
     * @throws \Exception
     */
    private function obtainNum(&$value)
    {
        $numInfo = ['type' => $value['type'], 'num' => data_get($value, 'num')];
        $numInfo['reduce'] = $this->stockReduce;
        $value['total_num'] = $this->getTotal($numInfo);
    }


    /**
     * 获取sn码对应存储的id值；
     *
     * @param array $items
     * @return mixed
     */
    private function getSnInfo(array $items)
    {
        $box = $this->snCode->whereIn('box', $items['postSn'])
            ->groupBy('sn')->orderBy('created_at', 'desc')->select('id', 'box', 'sn', 'goods_no')->get();
        $sn = $this->snCode->whereIn('sn', $items['postSn'])
            ->groupBy('sn')->orderBy('created_at', 'desc')->select('id', 'box', 'sn', 'goods_no')->get();
        $serialsIds = $box->pluck('id')->merge($sn->pluck('id'))->unique();
        if (count($serialsIds) != abs($items['totalNum'])) {
            $notExist = array_diff($items['postSn'], $box->pluck('box')->merge($sn->pluck('sn'))->unique()->toArray());
            if (empty($notExist)) {
                $goods_no = data_get($box, '0.goods_no');
                if (empty($goods_no)) {
                    $goods_no = data_get($sn, '0.goods_no');
                }
                if (empty($goods_no)) {
                    error("数量不正确");
                }
                error('商品编码：' . $goods_no . '的商品数量不正确');
            }
            error("箱码或SN码：" . implode(',', $notExist) . " 的信息不存在");
        }
        return $serialsIds->toArray();
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
                    ->whereIn('pur_sn_relation.sn_id', $ids)->where('purchase_detail.location_id', $locationId);
            }
        )->get();
        $unfinishedPurchases = $current->where('finished', 0)->pluck('purchase_id')->unique();
        if ($unfinishedPurchases->count() > 0) {
            $order = $this->purchase->whereIn('id', $unfinishedPurchases)->select('no')->get();
            error('关联表单：' . $order->pluck('no')->unique()->join(',') . ' 的表单待审核');
        }
        return $current;
    }
}

