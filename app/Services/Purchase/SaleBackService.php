<?php

namespace App\Services\Purchase;

use App\Models\Purchase\BackSaleRelation;
use App\Models\Purchase\OutSaleRelation;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\RedSaleRelation;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Class SaleBackService
 * @package App\Services\Purchase
 */
class SaleBackService extends BaseService
{
    protected $purchase;
    protected $backSaleRelation;
    protected $redSaleRelation;
    protected $outSaleRelation;

    public function __construct(
        Purchase $purchase,
        BackSaleRelation $backSaleRelation,
        RedSaleRelation $redSaleRelation,
        OutSaleRelation $outSaleRelation
    ) {
        $this->purchase = $purchase;
        $this->backSaleRelation = $backSaleRelation;
        $this->redSaleRelation = $redSaleRelation;
        $this->outSaleRelation = $outSaleRelation;
    }

    /**
     * 新建销售退货单
     * @param array $data
     * @return Purchase
     */
    public function saleBackAdd(array $data): Purchase
    {
        $purchase = $this->purchase->where('no', data_get($data, 'no'))->first();
        if (!is_null($purchase)) {
            error("销售退货单已经存在");
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
            $purchaseId = data_get($this->purchase, 'id');
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
            $ret = $this->backSaleRelation->addAll($detail->toArray());
            if (!$ret) {
                DB::rollBack();
                error("销售发货关联关系添加失败");
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            error("销售退货单数据添加失败");
        }
        return $this->purchase;
    }

    /**
     * 更新销售退货单
     * @param array $data
     * @return bool
     */
    public function update(array $data): bool
    {
        $purchaseId = data_get($data, 'id');
        $purchase = $this->purchase->where('id', $purchaseId)->first();
        if (empty($purchase)) {
            error("销售退货单不存在");
        }
        if (!empty(data_get($purchase, 'checked_user'))) {
            error("单据已审核，不能被更改");
        }
        if (data_get($data, 'no') != data_get($purchase, 'no')) {
            error("单据编号不能被修改");
        }
        $ret = $purchase->update($data);
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
        $ret = $this->backSaleRelation->addAll($detail->toArray());
        return $ret;
    }

    /**
     * 根据id获取销售退货单详情
     * @param array $ids
     * @return mixed
     */
    public function getSaleBackByIds(array $ids)
    {
        $purchases = $this->purchase->whereIn('id', $ids)->with('saleBacks', 'saleBackRemarks')->get();
        if ($purchases->count() > 0) {
            $ids = $purchases->pluck('saleBacks.*.id')->flatten()->toArray();
            $purchases = $purchases->toArray();
            $numMap = $this->outSaleRelation->whereIn('purchase_id', $ids)->get()->groupBy('sale_order_id');
            foreach ($purchases as &$ret) {
                $ret['audit_classify'] = $this->getAuditClassify(data_get($ret, 'type'));
                $department = $this->getDepartment(data_get($ret, 'department_id'));
                $ret['department'] = data_get($department, 'name');
                $location = $this->getLocation(data_get($ret, 'location_id'));
                $ret['location'] = data_get($location, 'name');
                $ret['checked_user'] = json_decode(data_get($ret, 'checked_user') ?? '[]', true);
                foreach ($ret['sale_backs'] as $key => &$value) {
                    $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                    $value['parent_id'] = data_get($ret, 'sale_back_remarks.' . $key . '.parent_id');
                    $value['goods_name'] = data_get($goods, 'name');
                    $value['is_software'] = data_get($goods, 'is_software');
                    $value['unit'] = data_get($goods, 'unit');
                    $value['attribute'] = data_get($goods, 'attribute');
                    $value['num'] = data_get($numMap, $value['id'] . '.0.num');
                    $value['back_num'] = -abs(data_get($ret, 'sale_back_remarks.' . $key . '.num'));
                    $value['remark'] = data_get($ret, 'sale_back_remarks.' . $key . '.remark');
                    $value['sale_out_no'] = data_get($ret, 'sale_back_remarks.' . $key . '.sale_out.no');
                    unset($value['pivot']);
                }
                unset($ret['sale_back_remarks']);
            }
        }
        return $purchases;
    }

    /**
     * 按条件获取销售退货单列表
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function getSaleBackList(array $data): LengthAwarePaginator
    {
        $audit = $this->getAuditClassify(data_get($data, 'type'));
        $limit = data_get($data, 'limit', 20);
        $query = DB::table('purchases')->join(
            'back_sale_relation',
            function ($join) {
                $join->on('purchases.id', '=', 'back_sale_relation.purchase_id');
            }
        );
        $query = $query->join(
            'sale_orders',
            function ($join) {
                $join->on('back_sale_relation.sale_order_id', '=', 'sale_orders.id');
            }
        );
        $purchases = $query->select(
            [
                'sale_orders.*',
                'purchases.no as no',
                'purchases.type as type',
                'purchases.user as user',
                'purchases.location_id as location_id',
                'purchases.checked_user as checked_user',
                'purchases.checked as checked',
                'purchases.id as purchase_id',
                'purchases.order_time as order_time',
                'purchases.purchase_type as purchase_type',
                'purchases.sale_type as sale_type',
                'purchases.department_id as department_id',
                'purchases.remark as remark',
                'back_sale_relation.num as back_num',
                'back_sale_relation.remark as back_remark',
            ]
        )->where(['purchases.type' => PURCHASE_SALE_BACK])
            ->whereNull('purchases.deleted_at')
            ->orderByDesc('purchases.id')->paginate($limit);
        if ($purchases->total() > 0) {
            foreach ($purchases->items() as $item) {
                $item->checked_user = json_decode(data_get($item, 'checked_user') ?? '[]', true);
                $item->back_num = -abs(data_get($item, 'back_num'));
                $goods = $this->getGoodsByNo(data_get($item, 'goods_no'));
                $item->audit_classify = $audit;
                $item->goods_name = data_get($goods, 'name');
                $item->unit = data_get($goods, 'unit');
                $item->attribute = data_get($goods, 'attribute');
                $department = $this->getDepartment(data_get($item, 'department_id'));
                $location = $this->getLocation(data_get($item, 'location_id'));
                $item->location_name = data_get($location, 'name');
                $item->department = data_get($department, 'name');
            }
        }
        return $purchases;
    }

    /**
     * 修改销售退货单审核状态
     * @param array $data
     * @return mixed
     */
    public function audit(array $data)
    {
        $purchase = $this->purchase->where('id', data_get($data, 'id'))->first();
        if (is_null($purchase)) {
            error("销售发货单不存在");
        }
        if (1 == data_get($data, 'check_status') && empty(data_get($purchase, 'checked_user'))) {
            $user = $this->getCurrentUser();
            $info['user_id'] = data_get($user, 'id');
            $info['username'] = data_get($user, 'username');
            $info['checked_time'] = date('Y-m-d H:i:s', time());
            $checkInfo[] = $info;
            $purchase->checked_user = json_encode($checkInfo);
            $purchase->checked = 2;
            $this->backSaleRelation->where('purchase_id', data_get($data, 'id'))->update(['finished' => 1]);
        } elseif (1 == data_get($data, 'check_status') && !empty(data_get($purchase, 'checked_user'))) {
            error("销售退货单已审核");
        }
        if (-1 == data_get($data, 'check_status') && !empty(data_get($purchase, 'checked_user'))) {
            $redSaleRelation = $this->redSaleRelation->where('parent_id', $purchase->id)->first();
            if (!is_null($redSaleRelation)) {
                error("销售退货单已被引用，无法反审核");
            }
            $purchase->checked_user = null;
            $purchase->checked = 1;
            $this->backSaleRelation->where('purchase_id', data_get($data, 'id'))->update(['finished' => 0]);
        } elseif (-1 == data_get($data, 'check_status') && empty(data_get($purchase, 'checked_user'))) {
            error("销售退货单未审核");
        }
        return $purchase->save();
    }

    /**
     * 根据id删除销售退货单
     * @param int $id
     * @return mixed
     */
    public function delete(int $id)
    {
        $purchase = $this->purchase->where('id', $id)->first();
        if (is_null($purchase)) {
            error("销售退货单不存在");
        }
        if (!empty(data_get($purchase, 'checked_user'))) {
            error("销售退货单已经被审核");
        }
        $this->backSaleRelation->where('purchase_id', $id)->delete();
        return $purchase->delete();
    }
}
