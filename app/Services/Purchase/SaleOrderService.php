<?php

namespace App\Services\Purchase;

use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseSaleOrderRelation;
use App\Models\Purchase\SaleOrder;
use App\Models\Purchase\ShipSaleRelation;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Class SaleOrderService
 *
 * @package App\Services\Purchase
 */
class SaleOrderService extends BaseService
{
    private $model;
    private $purchase;
    private $purSaleOrderRelation;
    private $shipSaleRelation;

    public function __construct(
        SaleOrder $model,
        Purchase $purchase,
        PurchaseSaleOrderRelation $purSaleOrderRelation,
        ShipSaleRelation $shipSaleRelation
    ) {
        $this->model = $model;
        $this->purchase = $purchase;
        $this->purSaleOrderRelation = $purSaleOrderRelation;
        $this->shipSaleRelation = $shipSaleRelation;
    }

    /**
     * 修改销售订单审核状态
     * @param array $data
     * @return mixed
     */
    public function firstCheck(array $data)
    {
        if (-1 == $data['check_status']) {
            $ret = $this->purchase->where('parent_id', $data['id'])->first();
            if (!is_null($ret)) {
                error("单据已被引单，无法进行反审核");
            }
        }
        $purchase = $this->purchase->where('id', data_get($data, 'id'))->first();
        if (is_null($purchase)) {
            error("单据不存在");
        }
        if (1 == data_get($data, 'check_status') && empty(data_get($purchase, 'checked_user'))) {
            $user = $this->getCurrentUser();
            $info['user_id'] = data_get($user, 'id');
            $info['username'] = data_get($user, 'username');
            $info['checked_time'] = date('Y-m-d H:i:s', time());
            $checkInfo[] = $info;
            $purchase->checked_user = json_encode($checkInfo);
            $purchase->checked = 2;
            $this->purSaleOrderRelation->where('purchase_id', data_get($data, 'id'))->update(['finished' => 1]);
            return $purchase->save();
        } elseif (1 == data_get($data, 'check_status') && !empty(data_get($purchase, 'checked_user'))) {
            error("销售订单已审核");
        }
        if (-1 == data_get($data, 'check_status') && !empty(data_get($purchase, 'checked_user'))) {
            if (SECOND_CHECKED == data_get($purchase, 'checked')) {
                error("请先进行二级反审核");
            }
            $purchase->checked_user = null;
            $purchase->checked = 1;
            $this->purSaleOrderRelation->where('purchase_id', data_get($data, 'id'))->update(['finished' => 0]);
            return $purchase->save();
        } elseif (-1 == data_get($data, 'check_status') && empty(data_get($purchase, 'checked_user'))) {
            error("销售订单未审核");
        }
    }

    /**
     * 销售订单二级审核
     * @param array $data
     */
    public function secondCheck(array $data)
    {
        if (-1 == $data['check_status']) {
            $ret = $this->shipSaleRelation->where('parent_id', $data['id'])->first();
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
     * 获取销售订单列表
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function getSaleOrderList(array $data): LengthAwarePaginator
    {
        $audit = $this->getAuditClassify(data_get($data, 'type'));
        $limit = data_get($data, 'limit', 20);
        $query = DB::table('purchases')->join(
            'purchase_sale_order_relation',
            function ($join) {
                $join->on('purchases.id', '=', 'purchase_sale_order_relation.purchase_id')
                    ->whereNull('purchases.deleted_at');
            }
        );
        $query = $query->join(
            'sale_orders',
            function ($join) {
                $join->on('purchase_sale_order_relation.sale_order_id', '=', 'sale_orders.id')
                    ->whereNull('purchase_sale_order_relation.deleted_at');
            }
        );
        $query = $query->leftJoin('ship_sale_relation', function ($join) {
            $join->on('ship_sale_relation.sale_order_id', '=', 'sale_orders.id')
                ->where('ship_sale_relation.finished', 1);
        });
        $query = $query->leftJoin('out_sale_relation', function ($join) {
            $join->on('out_sale_relation.sale_order_id', '=', 'sale_orders.id')
                ->where('out_sale_relation.finished', 1)->whereNull('out_sale_relation.deleted_at');
        });
        $query = $query->leftJoin('back_sale_relation', function ($join) {
            $join->on('back_sale_relation.sale_order_id', '=', 'sale_orders.id')
                ->where('back_sale_relation.finished', 1)->whereNull('back_sale_relation.deleted_at');
        });
        $ret = $query->select(
            [
                'purchases.id as id',
                'sale_orders.id as sale_order_id',
                'purchases.no as purchase_no',
                'purchases.order_time as order_time',
                'purchases.tax as tax',
                'purchases.status as status',
                'purchases.sale_type as sale_type',
                'purchases.department_id as department_id',
                'purchases.remark as purchase_remark',
                'purchases.checked as checked',
                'purchases.user_id as user_id',
                'purchases.user as user',
                'purchases.checked_user as checked_user',
                'purchases.created_at as created_at',
                'sale_orders.remark as sale_order_remark',
                'sale_orders.order_no as order_no',
                'sale_orders.platform as platform',
                'sale_orders.customer as customer',
                'sale_orders.phone as phone',
                'sale_orders.address as address',
                'sale_orders.express as express',
                'sale_orders.tracking_code as tracking_code',
                'sale_orders.price as price',
                'sale_orders.goods_no as goods_no',
                'sale_orders.num as num',
                DB::raw('sum(ship_sale_relation.num) as ship_sale_num'),
                DB::raw('sum(JSON_LENGTH(out_sale_relation.serials)) as out_sale_num'),
                DB::raw('sum(back_sale_relation.num) as back_sale_num'),
            ]
        )->where(['purchases.type' => PURCHASE_SALE])->groupBy('sale_orders.id')
            ->orderByDesc('purchases.id')->paginate($limit);
        if ($ret->items()) {
            $saleOrders = $saleOrder = [];
            foreach ($ret->items() as $key) {
                $saleOrder['id'] = data_get($key, 'id');
                $saleOrder['audit_classify'] = $audit;
                $saleOrder['sale_order_id'] = data_get($key, 'sale_order_id');
                $saleOrder['purchase_no'] = data_get($key, 'purchase_no');
                $saleOrder['order_time'] = data_get($key, 'order_time');
                $saleOrder['tax'] = data_get($key, 'tax');
                $saleOrder['sale_type'] = data_get($key, 'sale_type');
                $saleOrder['status'] = data_get($key, 'status');
                $department = $this->getDepartment(data_get($key, 'department_id'));
                $saleOrder['department_id'] = data_get($department, 'id');
                $saleOrder['department_name'] = data_get($department, 'name');
                $goods = $this->getGoodsByNo(data_get($key, 'goods_no'));
                $saleOrder['goods_no'] = data_get($key, 'goods_no');
                $saleOrder['goods_name'] = data_get($goods, 'name');
                $saleOrder['goods_unit'] = data_get($goods, 'unit');
                $saleOrder['attribute'] = data_get($goods, 'attribute');
                $saleOrder['purchase_remark'] = data_get($key, 'purchase_remark');
                $saleOrder['checked'] = data_get($key, 'checked', 1);
                $saleOrder['checked_user'] = empty(data_get($key, 'checked_use')) ?
                    [] : json_decode(data_get($key, 'checked_use'));
                $saleOrder['user_id'] = data_get($key, 'user_id');
                $saleOrder['user_name'] = data_get($key, 'user');
                $saleOrder['sale_order_remark'] = data_get($key, 'sale_order_remark');
                $saleOrder['order_no'] = data_get($key, 'order_no');
                $saleOrder['platform'] = data_get($key, 'platform');
                $saleOrder['customer'] = data_get($key, 'customer');
                $saleOrder['phone'] = data_get($key, 'phone');
                $saleOrder['address'] = data_get($key, 'address');
                $saleOrder['express'] = data_get($key, 'express');
                $saleOrder['tracking_code'] = data_get($key, 'tracking_code');
                $saleOrder['price'] = data_get($key, 'price');
                $saleOrder['num'] = data_get($key, 'num');
                $saleOrder['ship_sale_num'] = data_get($key, 'ship_sale_num', 0);
                $saleOrder['out_sale_num'] = data_get($key, 'out_sale_num', 0);
                $saleOrder['back_sale_num'] = data_get($key, 'back_sale_num', 0);
                $saleOrders[] = $saleOrder;
            }
            $ret->setCollection(collect($saleOrders));
        }
        return $ret;
    }

    /**
     * 根据id获取销售订单详情
     *
     * @param array $ids
     * @return mixed
     */
    public function getSaleOrderByIds(array $ids)
    {
        $purchases = $this->purchase->whereIn('id', $ids)->with('saleOrders')->get();
        $companyId = data_get($this->getCurrentUser(), 'company_id');
        $storageNum = $this->getStorageNumByCompanyId($companyId);
        if ($purchases->count() > 0) {
            $purchases = $purchases->toArray();
            foreach ($purchases as &$ret) {
                $ret['audit_classify'] = $this->getAuditClassify(data_get($ret, 'type'));
                foreach ($ret['sale_orders'] as $key => &$value) {
                    $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                    $value['goods_name'] = data_get($goods, 'name');
                    $value['unit'] = data_get($goods, 'unit');
                    $value['attribute'] = data_get($goods, 'attribute');
                    $value['existing_num'] = data_get($storageNum, $value['goods_no'], 0);
                    $value['is_software'] = data_get($goods, 'is_software');
                    unset($value['pivot']);
                }
            }
        }
        return $purchases;
    }

    /**
     * 更新销售订单
     * @param array $data
     * @return Purchase
     */
    public function update(array $data): Purchase
    {
        $id = data_get($data, 'id');
        $purchaseOrder = $this->purchase->where('id', $id)->first();
        if (is_null($purchaseOrder)) {
            error("订单不存在");
        }
        if (UNCHECKED != data_get($purchaseOrder, 'checked')) {
            error("销售订单已经审核，请先进行反审核操作");
        }
        if (!is_null($purchaseOrder)) {
            $saleOrders = $this->purSaleOrderRelation->where('purchase_id', $id)
                ->get()->pluck('id')->flatten()->toArray();
            if (empty($saleOrders)) {
                unset($data['no']);
                $purchaseOrder->update($data);
                $detail = data_get($data, 'detail');
                $time = date('Y-m-d H:i:s', time());
                foreach ($detail as &$item) {
                    $temp = [];
                    $temp['order_no'] = data_get($item, 'order_no');
                    $temp['platform'] = data_get($item, 'platform');
                    $temp['customer'] = data_get($item, 'customer');
                    $temp['phone'] = data_get($item, 'phone');
                    $temp['address'] = data_get($item, 'address');
                    $temp['express'] = data_get($item, 'express');
                    $temp['tracking_code'] = data_get($item, 'tracking_code');
                    $temp['price'] = data_get($item, 'price');
                    $temp['goods_no'] = data_get($item, 'goods_no');
                    $temp['remark'] = data_get($item, 'remark');
                    $temp['num'] = data_get($item, 'num');
                    $temp['tax'] = data_get($data, 'tax');
                    $temp['created_at'] = $temp['updated_at'] = $time;
                    $item = $temp;
                }
                $this->model->addAll($detail);
                $saleOrderIds = $this->model->where('created_at', $time)
                    ->select('id', 'created_at')->orderByDesc('created_at')->get();
                $saleOrderIds = collect($saleOrderIds->toArray())->groupBy('created_at')
                    ->first()->pluck('id')->toArray();
                $purchaseId = data_get($purchaseOrder, 'id');
                $item = $items = [];
                foreach ($saleOrderIds as $saleItem) {
                    $item['purchase_id'] = $purchaseId;
                    $item['sale_order_id'] = $saleItem;
                    $items[] = $item;
                }
                $this->purSaleOrderRelation->addAll($items);
                return $this->purchase;
            } else {
                $ret = $this->shipSaleRelation->whereIn('sale_order_id', $saleOrders)
                    ->get()->pluck('purchase_id')->toArray();
                if (!empty($ret)) {
                    error("订单已发货");
                }
                $this->model->whereIn('id', $saleOrders)->delete();
                $this->purSaleOrderRelation->where('purchase_id', $id)->delete();
                unset($data['no']);
                $purchaseOrder->update($data);
                $detail = data_get($data, 'detail');
                $time = date('Y-m-d H:i:s');
                foreach ($detail as &$item) {
                    $temp = [];
                    $temp['order_no'] = data_get($item, 'order_no');
                    $temp['platform'] = data_get($item, 'platform');
                    $temp['customer'] = data_get($item, 'customer');
                    $temp['phone'] = data_get($item, 'phone');
                    $temp['address'] = data_get($item, 'address');
                    $temp['express'] = data_get($item, 'express');
                    $temp['tracking_code'] = data_get($item, 'tracking_code');
                    $temp['price'] = data_get($item, 'price');
                    $temp['goods_no'] = data_get($item, 'goods_no');
                    $temp['remark'] = data_get($item, 'remark');
                    $temp['num'] = data_get($item, 'num');
                    $temp['tax'] = data_get($data, 'tax');
                    $temp['created_at'] = $temp['updated_at'] = $time;
                    $item = $temp;
                }
                $this->model->addAll($detail);
                $saleOrderIds = $this->model->where('created_at', $time)
                    ->select('id', 'created_at')->orderByDesc('created_at')->get();
                $saleOrderIds = collect($saleOrderIds->toArray())->groupBy('created_at')
                    ->first()->pluck('id')->toArray();
                $purchaseId = data_get($purchaseOrder, 'id');
                $item = $items = [];
                foreach ($saleOrderIds as $saleItem) {
                    $item['purchase_id'] = $purchaseId;
                    $item['sale_order_id'] = $saleItem;
                    $items[] = $item;
                }
                $this->purSaleOrderRelation->addAll($items);
                return $purchaseOrder;
            }
        } else {
            error('销售订单不存在，请先进行创建');
        }
    }

    /**
     * 删除单个销售订单
     * @param int $id
     * @return mixed
     */
    public function delete(int $id)
    {
        $saleOrderIds = $this->purSaleOrderRelation->where('purchase_id', $id)->select('sale_order_id')->get();
        $ids = $saleOrderIds->pluck('sale_order_id')->flatten()->toArray();
        $ships = $this->shipSaleRelation->whereIn('sale_order_id', $ids)->get();
        if ($ships->count()) {
            error("销售订单存在发货，无法删除");
        }
        $this->purSaleOrderRelation->where('purchase_id', $id)->delete();
        $this->model->whereIn('id', $ids)->delete();
        return $this->purchase->where('id', $id)->delete();
    }

    /**
     * 新建销售订单
     *
     * @param array $data
     * @return Purchase
     */
    public function saleOrderAdd(array $data): Purchase
    {
        $ret = $this->purchase->where('no', data_get($data, 'no'))->first();
        if (!is_null($ret)) {
            error('订单已存在');
        }
        $user = $this->getCurrentUser();
        $data['user'] = data_get($user, 'username');
        $data['user_id'] = data_get($user, 'id');
        $data['company_id'] = data_get($user, 'company_id');
        $data['parent_id'] = 0;
        $data['checked'] = UNCHECKED;
        $data['status'] = 1;
        $this->purchase->fill($data)->save();
        $detail = data_get($data, 'detail');
        $time = date('Y-m-d H:i:s', time());
        foreach ($detail as &$item) {
            $temp = [];
            $temp['order_no'] = data_get($item, 'order_no');
            $temp['platform'] = data_get($item, 'platform');
            $temp['customer'] = data_get($item, 'customer');
            $temp['phone'] = data_get($item, 'phone');
            $temp['address'] = data_get($item, 'address');
            $temp['express'] = data_get($item, 'express');
            $temp['tracking_code'] = data_get($item, 'tracking_code');
            $temp['price'] = data_get($item, 'price');
            $temp['goods_no'] = data_get($item, 'goods_no');
            $temp['remark'] = data_get($item, 'remark');
            $temp['num'] = data_get($item, 'num');
            $temp['tax'] = data_get($data, 'tax');
            $temp['created_at'] = $temp['updated_at'] = $time;
            $item = $temp;
        }
        $this->model->addAll($detail);
        $saleOrderIds = $this->model->where('created_at', $time)
            ->select('id', 'created_at')->orderByDesc('created_at')->get();
        $saleOrderIds = collect($saleOrderIds->toArray())->groupBy('created_at')->first()->pluck('id')->toArray();
        $purchaseId = data_get($this->purchase, 'id');
        $item = $items = [];
        foreach ($saleOrderIds as $saleItem) {
            $item['purchase_id'] = $purchaseId;
            $item['sale_order_id'] = $saleItem;
            $item['finished'] = 0;
            $items[] = $item;
        }
        $this->purSaleOrderRelation->addAll($items);
        return $this->purchase;
    }
}
