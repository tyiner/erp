<?php

namespace App\Services\Purchase;

use App\Models\Purchase\PurchaseDetail;
use App\Models\Purchase\PurSnRelation;
use App\Models\Purchase\SnCode;
use App\Models\Stock\Location;
use App\Models\Stock\LockInventory;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Class PurchaseDetailService
 *
 * @package App\Services\Purchase
 */
class PurchaseDetailService extends BaseService
{
    protected $model;
    protected $lockInventory;
    protected $purSnRelation;
    protected $snCode;
    protected $location;

    public function __construct(
        PurchaseDetail $model,
        LockInventory $lockInventory,
        PurSnRelation $purSnRelation,
        SnCode $snCode,
        Location $location
    ) {
        $this->model = $model;
        $this->lockInventory = $lockInventory;
        $this->purSnRelation = $purSnRelation;
        $this->snCode = $snCode;
        $this->location = $location;
    }

    /**
     * 获取总公司库存
     *
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function getInvByLocation(array $data): LengthAwarePaginator
    {
        $limit = data_get($data, 'limit', 20);
        if (!isAdmin()) {
            $data['location_ids'] = getUsableLocation(data_get($data, 'location_ids', []));
            if (data_get($data, 'location_name')) {
                $locations = $this->location
                    ->where('name', 'like', '%' . data_get($data, 'location_name') . '%')
                    ->get('id')->pluck('id')->toArray();
                $data['location_ids'] = array_intersect_assoc($locations, data_get($data['location_ids']));
            }
        } elseif (data_get($data, 'location_name')) {
            $locations = $this->location
                ->where('name', 'like', '%' . data_get($data, 'location_name') . '%')
                ->get('id')->pluck('id')->toArray();
            $data['location_ids'] = $locations;
        }
        $query = DB::table('purchase_detail')->join(
            'purchases',
            function ($join) {
                $join->on('purchase_detail.purchase_id', '=', 'purchases.id')->where(
                    'purchase_detail.finished',
                    1
                )->whereNull('purchases.deleted_at');
            }
        );
        if (data_get($data, 'location_ids')) {
            $query = $query->whereIn('purchase_detail.location_id', data_get($data, 'location_ids'));
        }
        if (data_get($data, 'goods_no')) {
            $query = $query->whereIn('goods_no', data_get($data, 'goods_no'));
        }
        if (data_get($data, 'company_ids')) {
            $query = $query->whereIn('purchases.company_id', data_get($data, 'company_ids'));
        }
        $ret = $query->select(
            \DB::raw('sum(purchase_detail.total_num) as qty'),
            'purchase_detail.location_id',
            'purchases.company_id',
            'purchase_detail.attribute',
            'purchase_detail.unit',
            'purchase_detail.goods_no'
        )->groupBy('purchase_detail.location_id', 'purchase_detail.goods_no')
            ->orderBy('purchases.id', 'desc')
            ->where('purchase_detail.finished', 1)
            ->paginate($limit);
        if ($ret->count()) {
            $result = $this->makePageData($ret->items());
            $ret->setCollection(collect($result));
        }
        return $ret;
    }

    /**
     * @param array $data
     * @return array
     */
    private function makePageData(array $data): array
    {
        $items = $item = [];
        foreach ($data as $value) {
            $lockNum = $this->lockInventory->where('location_id', data_get($value, 'location_id'))->where(
                'goods_no',
                data_get($value, 'goods_no')
            )->first();
            $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
            $location = $this->getLocation(data_get($value, 'location_id'));
            $company = $this->getCompany(data_get($value, 'company_id'));
            $item['company_name'] = data_get($company, 'name');
            $item['company_id'] = data_get($company, 'id');
            $item['goods_name'] = data_get($goods, 'name');
            $item['goods_no'] = data_get($value, 'goods_no');
            $item['classify'] = data_get($goods, 'classify');
            $item['goods_type'] = json_decode(data_get($goods, 'goods_type', '[]'), true);
            $item['attribute'] = data_get($goods, 'attribute');
            $item['location_id'] = data_get($location, 'id');
            $item['location_name'] = data_get($location, 'name');
            $item['location_no'] = data_get($location, 'no');
            $item['unit'] = data_get($goods, 'unit');
            $item['qty'] = data_get($value, 'qty');
            $item['lock_num'] = data_get($lockNum, 'lock_num', 0);
            $items[] = $item;
        }
        return $items;
    }

    /**
     * 按公司获取库存
     *
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function getInvByCompany(array $data): LengthAwarePaginator
    {
        $limit = data_get($data, 'limit', 20);
        if (!isAdmin()) {
            $current = $this->getCurrentUser();
            //$data['company_ids'] = getUsableCompany(data_get($data, 'company_ids', []));
            $data['company_ids'] = [data_get($current, 'company_id')];
        }
        $query = DB::table('purchase_detail')->join(
            'purchases',
            function ($join) {
                $join->on('purchase_detail.purchase_id', '=', 'purchases.id')->where(
                    'purchase_detail.finished',
                    1
                )->whereNull('purchases.deleted_at');
            }
        );
        if (data_get($data, 'company_ids')) {
            $query = $query->whereIn('purchases.company_id', $data['company_ids']);
        }
        if (data_get($data, 'goods_no')) {
            $query = $query->whereIn('purchase_detail.goods_no', data_get($data, 'goods_no'));
        }
        $query->select(
            \DB::raw('sum(purchase_detail.total_num) as qty'),
            'purchase_detail.goods_no',
            'purchases.company_id'
        )->groupBy('purchases.company_id', 'purchase_detail.goods_no');
        return $query->paginate($limit);
    }

    /**
     * 根据purchase_id 进行数据删除
     *
     * @param array $purchaseIds
     * @return mixed
     */
    public function deleteByPurchaseId(array $purchaseIds)
    {
        return $this->model->whereIn('purchase_id', $purchaseIds)->delete();
    }

    /**
     * 绑定单号跟Sn码信息
     * @param $serials
     * @param $order
     */
    public function bindSerials($serials, $order)
    {
        $purchaseDetailIds = $goodsInfo = [];
        foreach (data_get($order, 'detail', []) as $value) {
            $goods['goods_no'] = data_get($value, 'goods_no');
            $goods['num'] = abs(data_get($value, 'total_num'));
            $goods['purchase_detail_id'] = $purchaseDetailIds[] = abs(data_get($value, 'id'));
            $goodsInfo[] = $goods;
        }
        $relations = $this->purSnRelation->whereIn('purchase_detail_id', $purchaseDetailIds)->get();
        if (0 != $relations->count()) {
            error("单据已经关联Sn信息");
        }
        $ret = $this->snCode->whereIn('sn', $serials)->orWhereIn('box', $serials)->get()->groupBy('goods_no');
        $purSn = [];
        foreach ($goodsInfo as $goods) {
            $items = data_get($ret, $goods['goods_no']);
            if (empty($items)) {
                error("商品编号为" . $goods['goods_no'] . '的商品在表单中不存在');
            }
            if ($items->count() != $goods['num']) {
                error("商品编号为" . $goods['goods_no'] . "的商品数量不对");
            }
            foreach ($items as $value) {
                $relation['purchase_detail_id'] = $goods['purchase_detail_id'];
                $relation['sn_id'] = data_get($value, 'id');
                $purSn[] = $relation;
            }
        }
        try {
            DB::beginTransaction();
            $ret = $this->purSnRelation->addAll($purSn);
            if ($ret) {
                DB::commit();
            } else {
                DB::rollBack();
                error("数据关联失败");
            }
        } catch (\Exception $e) {
            DB::rollBack();
            error("数据关联失败");
        }
    }
}
