<?php

namespace App\Services\Purchase;

use App\Models\Goods;
use App\Models\Purchase\PartnerSendLog;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\SendGoodsInfo;
use App\Models\Stock\Location;
use App\Services\BaseService;
use App\Services\Partner\BaiLuLocationService;
use App\Services\Partner\YeHaiLocationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Class PartnerService
 *
 * @package App\Services\Purchase
 */
class PartnerService extends BaseService
{
    private $baiLu;
    private $yueHai;
    private $log;
    private $sendGoodsInfo;
    private $purchase;
    private $goods;

    public function __construct(
        BaiLuLocationService $baiLu,
        YeHaiLocationService $yueHai,
        PartnerSendLog $log,
        SendGoodsInfo $sendGoodsInfo,
        Purchase $purchase,
        Goods $goods
    ) {
        $this->baiLu = $baiLu;
        $this->yueHai = $yueHai;
        $this->log = $log;
        $this->sendGoodsInfo = $sendGoodsInfo;
        $this->purchase = $purchase;
        $this->goods = $goods;
    }

    /**
     * 百路驰回调信息处理
     * @param $data
     * @return mixed
     */
    public function wmsEntryOrderConfirm($data)
    {
        $ret = $this->baiLu->xmlToArray($data);
        if (!is_array($ret)) {
            $this->baiLu->retErrorMsg('xml信息解析失败');
        }
        $no = data_get($ret, 'entryOrder.entryOrderCode');
        $type = data_get($ret, 'entryOrder.entryOrderType');
        if ('DBRK' == $type) {
            $purchase = $this->purchase->where('no', $no)->first();
            $log = $this->log->where([
                'purchase_id' => data_get($purchase, 'id'),
                'location_id' => data_get($purchase, 'receiving_location_id')
            ])->first();
            if (is_null($log)) {
                $this->baiLu->retErrorMsg("不存在的入库推送");
            }
            $log->async_result = $ret;
            $log->save();
        } else {
            $purchase = $this->purchase->where('no', $no)->first();
            $log = $this->log->where([
                'purchase_id' => data_get($purchase, 'id'),
                'location_id' => data_get($purchase, 'location_id')
            ])->first();
            if (is_null($log)) {
                $this->baiLu->retErrorMsg("不存在的入库推送");
            }
            $log->async_result = $ret;
            $log->save();
        }
        $this->baiLu->retMsg("回调成功");
    }

    /**
     * 备货发货接口回调接口处理
     * @param $data
     */
    public function wmsStockOutConfirm($data)
    {
        $ret = $this->baiLu->xmlToArray($data);
        if (!is_array($ret)) {
            $this->baiLu->retErrorMsg('xml信息解析失败');
        }
        $no = data_get($ret, 'deliveryOrder.deliveryOrderCode');
        $purchase = $this->purchase->where('no', $no)->first();
        $log = $this->log->where([
            'purchase_id' => data_get($purchase, 'id'),
            'location_id' => data_get($purchase, 'location_id')
        ])->first();
        if (is_null($log)) {
            $this->baiLu->retErrorMsg("不存在的出库推送");
        }
        $log->async_result = $ret;
        $log->save();
        $this->baiLu->retMsg("回调成功");
    }

    /**
     * 备货退货结果异步回调处理
     * @param $xml
     */
    public function wmsReturnOrderConfirm($xml)
    {
        $ret = $this->baiLu->xmlToArray($xml);
        if (!is_array($ret)) {
            $this->baiLu->retErrorMsg('xml信息解析失败');
        }
        $no = data_get($ret, 'deliveryOrder.returnOrderCode');
        $purchase = $this->purchase->where('no', $no)->first();
        $log = $this->log->where([
            'purchase_id' => data_get($purchase, 'id'),
            'location_id' => data_get($purchase, 'location_id')
        ])->first();
        if (is_null($log)) {
            $this->baiLu->retErrorMsg("不存在的退货推送");
        }
        $log->async_result = $ret;
        $log->save();
        $this->baiLu->retMsg("回调成功");
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function asyncStore(array $data): bool
    {
        $no = data_get($data, 'CustOrdNo');
        $order = $this->purchase->where('no', $no)->first();
        if (empty($order)) {
            return false;
        }
        $detail = data_get($data, 'SkuDetails');
        $location_id = data_get($order, 'location_id');
        $purchase_id = data_get($order, 'id');
        $log = $this->log->where(['purchase_id' => $purchase_id, 'location_id' => $location_id])->first();
        $log->async_result = $detail;
        return $log->save();
    }

    /**
     * 第三方发货异步通知
     * @param array $data
     * @return bool
     */
    public function asyncSale(array $data): bool
    {
        $no = data_get($data, 'CustOrdNo');
        $order = $this->purchase->where('no', $no)->first();
        if (empty($order)) {
            return false;
        }
        $detail = data_get($data, 'PackageList');
        $location_id = data_get($order, 'location_id');
        $purchase_id = data_get($order, 'id');
        $log = $this->log->where(['purchase_id' => $purchase_id, 'location_id' => $location_id])->first();
        $log->async_result = $detail;
        return $log->save();
    }

    /**
     * 其他出库异步推送结果
     * @param array $data
     * @return bool
     */
    public function asyncOther(array $data): bool
    {
        $no = data_get($data, 'CustOrdNo');
        $order = $this->purchase->where('no', $no)->first();
        if (empty($order)) {
            return false;
        }
        $detail = data_get($data, 'saleOrderList');
        $location_id = data_get($order, 'location_id');
        $purchase_id = data_get($order, 'id');
        $log = $this->log->where(['purchase_id' => $purchase_id, 'location_id' => $location_id])->first();
        $log->async_result = $detail;
        return $log->save();
    }

    /**
     * 重新推送商品信息
     * @param array $data
     * @return mixed
     */
    public function retry(array $data)
    {
        $model = $this->sendGoodsInfo->where('id', data_get($data, 'id'))->first();
        if (-1 != data_get($model, 'result')) {
            error("商品已经提交成功");
        }
        $location = $this->getLocationByNo(data_get($model, 'location_no'));
        $locationModel = $this->getClass(data_get($location, 'id'));
        $goods = $this->getGoodsByNo(data_get($model, 'goods_no'));
        $goodsData = [
            'goods_name' => data_get($goods, 'name'),
            'goods_no' => data_get($goods, 'no'),
            'unit' => data_get($goods, 'unit'),
            'actionType' => 'add',
        ];
        $ret = $locationModel->informGoods($goodsData);
        if (200 == $ret['code']) {
            $model->times++;
            $model->result = 1;
            $model->save();
        } else {
            $model->times++;
            $model->save();
            error("商品信息重新推送失败");
        }
        return $model;
    }

    /**
     * 获取推送失败商品列表
     * @param array $data
     * @return mixed
     */
    public function unSuccess(array $data): array
    {
        $query = $this->sendGoodsInfo->select('id', 'goods_no', 'location_no', 'result', 'message');
        if (data_get($data, 'location_no')) {
            $query = $query->where('location_no', data_get($data, 'location_no'));
        }
        if (data_get($data, 'goods_no')) {
            $query = $query->where('goods_no', data_get($data, 'goods_no'));
        }
        $ret = $query->where('result', -1)->groupBy('goods_no')->groupBy('location_no')->get();
        $items = $item = [];
        foreach ($ret as $value) {
            $item['id'] = data_get($value, 'id');
            $item['result'] = data_get($value, 'result');
            $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
            $location = $this->getLocationByNo(data_get($value, 'location_no'));
            $item['goods_name'] = data_get($goods, 'name');
            $item['goods_no'] = data_get($goods, 'no');
            $item['message'] = data_get($value, 'message');
            $item['location_no'] = data_get($location, 'no');
            $item['location_name'] = data_get($location, 'name');
            $items[] = $item;
        }
        return $items;
    }

    /**
     * 采购入库推送接口
     *
     * @param array $data
     * @return Collection|bool
     */
    public function storeSend(array $data)
    {
        $id = data_get($data, 'id');
        $order = $this->getInfo($id);
        if (empty($order->all())) {
            error("不存在的表单信息");
        }
        $type = data_get($order->first(), 'type');
        if (PURCHASE_STORE_IN !== $type) {
            error("单据类型不是采购入库单");
        }
        $user_id = auth('admin')->id();
        $purchase_id = $id;
        $location_id = data_get($order->first(), 'location_id');
        $class = $this->getClass($location_id);
        is_null($class) && error("不存在的推送仓");
        $ret = $class->sendStoreMsg($order->all());
        $response = json_encode($ret);
        $log = $this->log->where(['purchase_id' => $purchase_id, 'location_id' => $location_id])->first();
        if (empty($log)) {
            $this->log->fill(compact('user_id', 'purchase_id', 'location_id', 'response'))->save();
        } else {
            $log->response = $response;
            $log->save();
        }
        if (200 == $ret['code']) {
            return $order;
        }
        return false;
    }

    /**
     * 采购退货单接口
     * @param array $data
     */
    public function storeOutSend(array $data)
    {
        $id = data_get($data, 'id');
        $order = $this->getInfo($id);
        if (empty($order->all())) {
            error("不存在的表单信息");
        }
        $type = data_get($order->first(), 'type');
        if (PURCHASE_STORE_OUT !== $type) {
            error("单据类型不是采购入库退货单");
        }
        $location_id = data_get($order->first(), 'location_id');
        $class = $this->getClass($location_id);
        is_null($class) && error("不存在的推送仓");
        $class->storeOutSend($order->all());
    }

    /**
     * 备货出库第三方推送
     * @param array $data
     * @return false|Collection
     */
    public function saleSend(array $data)
    {
        $id = data_get($data, 'id');
        $order = $this->getInfo($id);

        if (empty($order->all())) {
            error("不存在的表单信息");
        }
        $type = data_get($order->first(), 'type');
        if (PURCHASE_STOCK_OUT !== $type) {
            error("单据类型不是备货出库单");
        }
        $location_id = data_get($order->first(), 'location_id');
        $class = $this->getClass($location_id);
        is_null($class) && error("不存在的推送仓");
        $ret = $class->saleSend($order->all());
        $response = json_encode($ret);
        $user_id = auth('admin')->id();
        $purchase_id = $id;
        $location_id = data_get($order->first(), 'location_id');
        $log = $this->log->where(['purchase_id' => $purchase_id, 'location_id' => $location_id])->first();
        if (empty($log)) {
            $this->log->fill(compact('user_id', 'purchase_id', 'location_id', 'response'))->save();
        } else {
            $log->response = $response;
            $log->save();
        }
        if (200 == $ret['code']) {
            return $order;
        }
        return false;
    }

    /**
     * 调拨推送
     * @param array $data
     * @return mixed
     */
    public function transfer(array $data): bool
    {
        $id = data_get($data, 'id');
        $order = $this->getInfo($id);
        if (empty($order->all())) {
            error("不存在的表单信息");
        }
        $type = data_get($order->first(), 'type');
        if (PURCHASE_TRANSFER !== $type) {
            error("单据类型不是调拨单");
        }
        $order = $order->where('total_num', '<', 0);
        $location_id = data_get($order->first(), 'location_id');
        $receivingLocationId = data_get($order->first(), 'receiving_location_id');
        $class = $this->getClass($location_id);
        $classReceive = $this->getClass($receivingLocationId);
        $user_id = auth('admin')->id();
        $purchase_id = $id;
        if (!is_null($class)) {
            $ret1 = $class->transferOut($order->all());
            $response = json_encode($ret1);
            $location_id = data_get($order->first(), 'location_id');
            $log = $this->log->where(['purchase_id' => $purchase_id, 'location_id' => $location_id])->first();
            if (empty($log)) {
                $this->log->fill(compact('purchase_id', 'response', 'location_id', 'user_id'))->save();
            } else {
                $log->response = $response;
                $log->save();
            }
        }
        if (!is_null($classReceive)) {
            $ret2 = $classReceive->sendStoreMsg($order->all());
            $response = json_encode($ret2);
            $location_id = data_get($order->first(), 'receiving_location_id');
            $log = $this->log->where(['purchase_id' => $purchase_id, 'location_id' => $location_id])->first();
            if (empty($log)) {
                $this->log->fill(compact('purchase_id', 'response', 'location_id', 'user_id'))->save();
            } else {
                $log->response = $response;
                $log->save();
            }
        }
        if (isset($ret1) && isset($ret2)) {
            if (200 == $ret1['code'] && 200 == $ret2['code']) {
                return $order;
            }
        }
        if (!isset($ret1) && isset($ret2)) {
            if (200 == $ret2['code']) {
                return $order;
            }
        }
        if (isset($ret1) && !isset($ret2)) {
            if (200 == $ret1['code']) {
                return $order;
            }
        }
        if (!isset($ret1) && !isset($ret2)) {
            return $order;
        }
        return false;
    }

    /**
     * 备货退货
     * @param array $data
     */
    public function saleBackSend(array $data)
    {
        $id = data_get($data, 'id');
        $order = $this->getInfo($id);
        if (empty($order->all())) {
            error("不存在的表单信息");
        }
        $type = data_get($order->first(), 'type');
        if (PURCHASE_STOCK_BACK !== $type) {
            error("单据类型不是备货仓库调整单");
        }
        $location_id = data_get($order->first(), 'location_id');
        $class = $this->getClass($location_id);
        is_null($class) && error("不存在的推送仓");
        $class->saleBackSend($order->all());
    }

    /**
     * 获取单据详情
     *
     * @param  $id
     * @return Collection
     */
    private function getInfo($id): Collection
    {
        $query = DB::table('purchases')->join(
            'purchase_detail',
            function ($join) use ($id) {
                $join->on('purchases.id', '=', 'purchase_detail.purchase_id')->where('purchases.id', $id);
            }
        );
        return $query->get();
    }

    /**
     * 根据仓库id获取对应类
     *
     * @param  $location_id
     * @return BaiLuLocationService|YeHaiLocationService
     */
    private function getClass($location_id)
    {
        $location = $this->getLocation($location_id);
        switch (data_get($location, 'no')) {
            case Location::YE_HAI:
                $class = $this->yueHai;
                break;
            case Location::BAI_LU:
                $class = $this->baiLu;
                break;
            default:
                $class = null;
        }
        return $class;
    }

    /**
     * 商品信息推送
     * @param array $data
     */
    public function informGoods(array $data)
    {
        $this->goods->where('goods_no', data_get($data, 'goods_no'))->update(['send_status' => 1]);
        $goodsLogs = $this->sendGoodsInfo->where('goods_no', data_get($data, 'goods_no'))->first();
        if ($goodsLogs) {
            error('商品数据已经推送');
        }
        $locations['yueHai']['ret'] = $this->yueHai->informGoods($data);
        $locations['baiLu']['ret'] = $this->baiLu->informGoods($data);
        $locations['yueHai']['no'] = Location::YE_HAI;
        $locations['baiLu']['no'] = Location::BAI_LU;
        $user = $this->getCurrentUser();
        $user_id = data_get($user, 'id');
        if (200 == $locations['yueHai']['ret']['code'] && 200 == $locations['baiLu']['ret']['code']) {
            foreach ($locations as $value) {
                $log['goods_no'] = data_get($data, 'goods_no');
                $log['location_no'] = $value['no'];
                $log['message'] = $value['ret']['message'];
                $log['result'] = 1;
                $log['times'] = 1;
                $log['user_id'] = $user_id;
                $logs[] = $log;
            }
            $this->sendGoodsInfo->addAll($logs);
            success("商品推送成功");
        } elseif (200 != $locations['yueHai']['ret']['code'] && 200 == $locations['baiLu']['ret']['code']) {
            foreach ($locations as $key => $value) {
                $log['goods_no'] = data_get($data, 'goods_no');
                $log['location_no'] = $value['no'];
                $log['message'] = $value['ret']['message'];
                if ($key == 'yueHai') {
                    $log['result'] = -1;
                } else {
                    $log['result'] = 1;
                }
                $log['times'] = 1;
                $log['user_id'] = $user_id;
                $logs[] = $log;
            }
            $this->sendGoodsInfo->addAll($logs);
            error("商品推送越海仓失败");
        } elseif (200 == $locations['yueHai']['ret']['code'] && 200 != $locations['baiLu']['ret']['code']) {
            foreach ($locations as $key => $value) {
                $log['goods_no'] = data_get($data, 'goods_no');
                $log['location_no'] = $value['no'];
                $log['message'] = $value['ret']['message'];
                if ($key == 'yueHai') {
                    $log['result'] = 1;
                } else {
                    $log['result'] = -1;
                }
                $log['times'] = 1;
                $log['user_id'] = $user_id;
                $logs[] = $log;
            }
            $this->sendGoodsInfo->addAll($logs);
            error("商品推送百路池失败");
        } else {
            foreach ($locations as $key => $value) {
                $log['goods_no'] = data_get($data, 'goods_no');
                $log['location_no'] = $value['no'];
                $log['message'] = $value['ret']['message'];
                $log['result'] = -1;
                $log['times'] = 1;
                $log['user_id'] = $user_id;
                $logs[] = $log;
            }
            $this->sendGoodsInfo->addAll($logs);
            error("商品推送越海仓，百路池失败");
        }
    }
}
