<?php

namespace App\Services\Stock;

use App\Models\Goods;
use App\Models\Purchase\SnCode;
use App\Models\Stock\Invoice;
use App\Services\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Class CheckService
 *
 * @package App\Services\Stock
 */
class CheckService extends BaseService
{
    private $goods;
    private $snCode;
    private $invoice;

    public function __construct(Goods $goods, SnCode $snCode, Invoice $invoice)
    {
        $this->goods = $goods;
        $this->snCode = $snCode;
        $this->invoice = $invoice;
    }

    //库存数量增加
    private $stockIncrease = [
        STOCK_BACK_PLAN,
        STOCK_ARRIVAL,
        STOCK_PLAN,
        STOCK_STORE_IN,
        STOCK_OTHER_IN,
    ];
    //库存数量减少
    private $stockReduce = [
        STOCK_SALE_OUT,
        STOCK_OTHER_OUT,
        STOCK_STORE_OUT,
    ];

    /**
     * 校验订单引入单
     *
     * @param array $data
     */
    public function checkStockParent(array $data)
    {
        if (STOCK_PLAN == data_get($data, 'type')) {
            return;
        }
        if (STOCK_ARRIVAL == data_get($data, 'type')) {
            $parent = $this->invoice->where('id', data_get($data, 'parent_id'))->first();
            if (!is_null($parent) && STOCK_PLAN == $parent->type) {
                return;
            }
            error("引入订单类型不是备货计划单");
        }
        if (STOCK_STORE_IN == data_get($data, 'type')) {
            $parent = $this->invoice->where('id', data_get($data, 'parent_id'))->first();
            if (!is_null($parent) && STOCK_ARRIVAL == $parent->type) {
                return;
            }
            error("引入订单类型不是备货到货单");
        }
    }

    /**
     * 数据校验
     *
     * @param array $data
     * @return array
     */
    public function checkStockUsable(array $data): array
    {
        $type = data_get($data, 'type');
        $detail = data_get($data, 'detail');
        $method = data_get(config("sncheck.check_map.stock"), $type);
        $data = $this->$method(compact('type', 'detail'));
        return $data['detail'];
    }

    /**
     * 备货入库
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function stockStoreAdd(array $data): array
    {
        foreach ($data['detail'] as &$value) {
            $value['type'] = $data['type'];
            $postSn = explode(',', $value['serials']);
            $this->obtainNum($value);
            $totalNum = $value['total_num'];
            $value['ids'] = $this->getSnInfo(compact('postSn', 'totalNum'));
            $current = $this->getCurrent($value['ids']);
            if ($current->count() == 0) {
                continue;
            }
            $count = $this->checkStore($current);
            if ($count > 0) {
                $goods = $this->goods->where('goods_no', data_get($value, 'goods_no'))->first();
                error("商品名称为：" . $goods->goods_name . " 的商品sn码或箱码存在已入库");
            }
            if ($count < 0) {
                $goods = $this->goods->where('goods_no', data_get($value, 'goods_no'))->first();
                error("商品名称为：" . $goods->goods_name . " 的商品sn码或箱码库存数量不正确");
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
    public function stockTransferAdd(array $data): array
    {
        foreach ($data['detail'] as &$value) {
            $value['type'] = $data['type'];
            $postSn = explode(',', $value['serials']);
            $this->obtainNum($value);
            $totalNum = $value['total_num'];
            $value['ids'] = $this->getSnInfo(compact('postSn', 'totalNum'));
            $current = $this->getCurrent($value['ids']);
            if ($current->count() == 0) {
                continue;
            }
            $count = $this->checkStore($current);
            if ($count > 0) {
                $goods = $this->goods->where('goods_no', data_get($value, 'goods_no'))->first();
                error("商品名称为：" . $goods->goods_name . " 的商品sn码或箱码存在已入库");
            }
            if ($count < 0) {
                $goods = $this->goods->where('goods_no', data_get($value, 'goods_no'))->first();
                error("商品名称为：" . $goods->goods_name . " 的商品sn码或箱码库存数量不正确");
            }
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
    public function stockOtherAdd(array $data): array
    {
        foreach ($data['detail'] as &$value) {
            $value['type'] = $data['type'];
            $postSn = explode(',', $value['serials']);
            $this->obtainNum($value);
            $totalNum = $value['total_num'];
            $value['ids'] = $this->getSnInfo(compact('postSn', 'totalNum'));
            $current = $this->getCurrent($value['ids']);
            if ($current->count() == 0) {
                continue;
            }
            $count = $this->checkStore($current);
            if ($count > 0) {
                $goods = $this->goods->where('goods_no', data_get($value, 'goods_no'))->first();
                error("商品名称为：" . $goods->goods_name . " 的商品sn码或箱码存在已入库");
            }
            if ($count < 0) {
                $goods = $this->goods->where('goods_no', data_get($value, 'goods_no'))->first();
                error("商品名称为：" . $goods->goods_name . " 的商品sn码或箱码库存数量不正确");
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
    public function stockOtherReduce(array $data): array
    {
        foreach ($data['detail'] as &$value) {
            $value['type'] = $data['type'];
            $postSn = explode(',', $value['serials']);
            $this->obtainNum($value);
            $totalNum = $value['total_num'];
            $value['ids'] = $this->getSnInfo(compact('postSn', 'totalNum'));
            $current = $this->getCurrent($value['ids']);
            if ($current->count() == 0) {
                $goods = $this->goods->where('goods_no', data_get($value, 'goods_no'))->first();
                error("商品名称为：" . $goods->goods_name . " 的商品sn码或箱码未入库");
            }
            $count = $this->checkStore($current);
            if ($count < abs($value['total_num'])) {
                $goods = $this->goods->where('goods_no', data_get($value, 'goods_no'))->first();
                error("商品名称为：" . $goods->goods_name . " 的商品sn码或箱码存在未入库");
            }
            if ($count > abs($value['total_num'])) {
                $goods = $this->goods->where('goods_no', data_get($value, 'goods_no'))->first();
                error("商品名称为：" . $goods->goods_name . " 的商品sn码或箱码库存数量不正常");
            }
        }
        return $data;
    }

    /**
     * 校验备货退货出库
     *
     * @param array $data
     * @return array|bool
     * @throws \Exception
     */
    public function stockStoreReduce(array $data): array
    {
        foreach ($data['detail'] as &$value) {
            $value['type'] = $data['type'];
            $postSn = explode(',', $value['serials']);
            $this->obtainNum($value);
            $totalNum = $value['total_num'];
            $value['ids'] = $this->getSnInfo(compact('postSn', 'totalNum'));
            $current = $this->getCurrent($value['ids']);
            if ($current->count() == 0) {
                $goods = $this->goods->where('goods_no', data_get($value, 'goods_no'))->first();
                error("商品名称为：" . $goods->goods_name . " 的商品sn码或箱码未入库");
            }
            $count = $this->checkStore($current);
            if ($count < abs($value['total_num'])) {
                $goods = $this->goods->where('goods_no', data_get($value, 'goods_no'))->first();
                error("商品名称为：" . $goods->goods_name . " 的商品sn码或箱码存在未入库");
            }
            if ($count > abs($value['total_num'])) {
                $goods = $this->goods->where('goods_no', data_get($value, 'goods_no'))->first();
                error("商品名称为：" . $goods->goods_name . " 的商品sn码或箱码库存数量不正常");
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
    protected function stockTransferReduce(array $data): array
    {
        foreach ($data['detail'] as &$value) {
            $value['type'] = $data['type'];
            $postSn = explode(',', $value['serials']);
            $this->obtainNum($value);
            $totalNum = $value['total_num'];
            $value['ids'] = $this->getSnInfo(compact('postSn', 'totalNum'));
            $current = $this->getCurrent($value['ids']);
            if ($current->count() == 0) {
                $goods = $this->goods->where('goods_no', data_get($value, 'goods_no'))->first();
                error("商品名称为：" . $goods->goods_name . " 的商品sn码或箱码未入库");
            }
            $count = $this->checkStore($current);
            if ($count < abs($value['total_num'])) {
                $goods = $this->goods->where('goods_no', data_get($value, 'goods_no'))->first();
                error("商品名称为：" . $goods->goods_name . " 的商品sn码或箱码存在部分未入库");
            }
            if ($count > abs($value['total_num'])) {
                $goods = $this->goods->where('goods_no', data_get($value, 'goods_no'))->first();
                error("商品名称为：" . $goods->goods_name . " 的商品sn码或箱码库存数量不正常");
            }
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
            if (in_array($type, $this->stockIncrease)) {
                $count += $item->count();
            }
            if (in_array($type, $this->stockReduce)) {
                $count -= $item->count();
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
            ->groupBy('sn')->orderBy('created_at', 'desc')->select('id', 'box', 'sn')->get();
        $sn = $this->snCode->whereIn('sn', $items['postSn'])
            ->groupBy('sn')->orderBy('created_at', 'desc')->select('id', 'box', 'sn')->get();
        $serialsIds = $box->pluck('id')->merge($sn->pluck('id'))->unique();
        if (count($serialsIds) != abs($items['totalNum'])) {
            $notExist = array_diff($items['postSn'], $box->pluck('box')->merge($sn->pluck('sn'))->unique()->toArray());
            if (empty($notExist)) {
                error("箱码或SN码的信息缺失");
            }
            error("箱码或SN码：" . implode(',', $notExist) . " 的信息不存在");
        }
        return $serialsIds->toArray();
    }

    /**
     * 获取当前sn信息出入库情况
     *
     * @param  $ids
     * @return Collection
     */
    private function getCurrent($ids): Collection
    {
        $current = DB::table('invoice_detail')->join(
            'inv_sn_relation',
            function ($join) use ($ids) {
                $join->on('invoice_detail.id', '=', 'inv_sn_relation.invoice_detail_id')
                    ->whereIn('inv_sn_relation.sn_code_id', $ids);
            }
        )->get();
        $unfinishedInvoices = $current->where('finished', 0)->pluck('invoice_id')->unique();
        if ($unfinishedInvoices->count() > 0) {
            $order = $this->invoice->whereIn('id', $unfinishedInvoices)->select('no')->get();
            error('表单序号：' . $order->pluck('no')->unique()->join(',') . ' 的表单待完成');
        }
        return $current;
    }
}
