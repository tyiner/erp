<?php

namespace App\Services\Stock;

use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * 采购发货单
 * Class StockShipService
 * @package App\Services\Stock
 */
class StockShipService extends BaseService
{
    /**
     * 获取采购发货单详情
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function getDetailList(array $data): LengthAwarePaginator
    {
        $type = data_get($data, 'type');
        $audit = $this->getAuditClassify($type);
        $limit = data_get($data, 'limit');
        $query = DB::table('purchase_detail')->join(
            'purchases',
            function ($join) use ($type) {
                $join->on('purchase_detail.purchase_id', '=', 'purchases.id')
                    ->where('purchase_detail.type', $type)
                    ->whereNull('purchases.deleted_at')
                    ->whereNull('purchase_detail.deleted_at');
            }
        );
        $current = $this->getCurrentUser();
        $companyId = data_get($current, 'company_id');
        $storageNum = $this->getStorageNumByCompanyId($companyId);
        $lockNum = $this->getLockNumByCompanyId($companyId);
        if (!isAdmin()) {
            /*$locationIds = getUsableLocation(data_get($data, 'location_ids', []));
            $query = $query->whereIn('purchases.location_id', $locationIds);*/
            $current = $this->getCurrentUser();
            $companyId = data_get($current, 'company_id');
            $query = $query->where('purchases.company_id', $companyId);
        } elseif (data_get($data, 'location_ids')) {
            $query = $query->whereIn('purchases.location_id', data_get($data, 'location_ids'));
        }
        if (data_get($data, 'no')) {
            $query = $query->where('purchases.no', data_get($data, 'no'));
        }
        if (data_get($data, 'company_id')) {
            $query = $query->where('purchases.company_id', data_get($data, 'company_id'));
        }
        if (data_get($data, 'goods_no')) {
            $query = $query->where('purchase_detail.goods_no', data_get($data, 'goods_no'));
        }
        if (data_get($data, 'begin_at')) {
            $query = $query->where('purchases.order_time', '>=', data_get($data, 'begin_at'));
        }
        if (data_get($data, 'end_at')) {
            $query = $query->where('purchases.order_time', '<=', data_get($data, 'end_at'));
        }
        if (data_get($data, 'location_id')) {
            $query = $query->where('purchase_detail.location_id', data_get($data, 'location_id'));
        }
        if (data_get($data, 'status')) {
            $query = $query->whereIn('purchases.status', data_get($data, 'status'));
        }
        if (data_get($data, 'check_status')) {
            $query = $query->whereIn('purchases.checked', data_get($data, 'check_status'));
        }
        $ret = $query->select(
            [
                '*',
                'purchases.remark as purchase_remark',
                'purchase_detail.remark as detail_remark'
            ]
        )->orderByDesc('purchases.order_time')->paginate($limit);
        if (!is_null($ret->items())) {
            $parent_ids = collect($ret->items())->pluck('parent_id')->unique();
            $query = DB::table('purchase_detail')->join(
                'purchases',
                function ($join) use ($parent_ids) {
                    $join->on('purchase_detail.purchase_id', '=', 'purchases.id')->whereIn('purchases.id', $parent_ids);
                }
            );
            $plans = $query->get(
                [
                    'purchases.id as id',
                    'purchase_detail.total_num as total',
                    'purchase_detail.goods_no'
                ]
            )->groupBy(
                [
                    'id',
                    function ($item) {
                        return data_get($item, 'goods_no');
                    },
                ],
                true
            );
            foreach ($ret->items() as &$item) {
                $goods = $this->getGoodsByNo(data_get($item, 'goods_no'));
                $supplier = $this->getSupplier(data_get($item, 'supplier_id'));
                $location = $this->getLocation(data_get($item, 'location_id'));
                $department = $this->getDepartment(data_get($item, 'department_id'));
                $subCompany = $this->getCompany(data_get($item, 'sub_company_id'));
                $storage = data_get($storageNum, data_get($item, 'goods_no'), 0);
                $lock = data_get($lockNum, data_get($item, 'goods_no'), 0);
                data_set($item, 'storage_num', $storage);
                data_set($item, 'usable_num', $storage - $lock);
                data_set($item, 'audit_classify', $audit);
                data_set($item, 'goods_name', data_get($goods, 'name'));
                data_set($item, 'classify', data_get($goods, 'classify'));
                data_set($item, 'goods_type', data_get($goods, 'goods_type'));
                data_set($item, 'supplier_name', data_get($supplier, 'name'));
                data_set($item, 'supplier_id', data_get($item, 'supplier_id'));
                data_set($item, 'department_name', data_get($department, 'name'));
                data_set($item, 'department_id', data_get($item, 'department_id'));
                data_set($item, 'location_name', data_get($location, 'name'));
                data_set($item, 'location_no', data_get($location, 'no'));
                data_set($item, 'sub_company_id', data_get($subCompany, 'id'));
                data_set($item, 'sub_company_name', data_get($subCompany, 'name'));
                data_set($item, 'unit_name', data_get($item, 'unit'));
                data_set($item, 'detail_remark', data_get($item, 'detail_remark'));
                data_set($item, 'purchase_remark', data_get($item, 'purchase_remark'));
                data_set($item, 'checked_user', json_decode(data_get($item, 'checked_user')));
                unset($item->remark);
                $collection = data_get($plans, $item->parent_id . '.' . $item->goods_no);
                if (!empty($collection)) {
                    data_set($item, 'plan_num', $collection->first()->total);
                } else {
                    data_set($item, 'plan_num', 0);
                }
            }
        }
        return $ret;
    }
}
