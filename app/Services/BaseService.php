<?php

namespace App\Services;

use App\Traits\HelperTrait;
use App\Traits\ResourceTrait;
use App\Traits\UserTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Class BaseService
 * @package App\Services
 */
class BaseService
{
    use HelperTrait, ResourceTrait, UserTrait;

    /**
     * 获取总数量
     *
     * @param array $data
     * @return float|int
     * @throws \Exception
     */
    public function getTotal(array $data)
    {
        if (in_array($data['type'], data_get($data, 'reduce'))) {
            return -abs(data_get($data, 'num'));
        }
        return abs(data_get($data, 'num'));
    }

    /**
     * 获取供应商
     *
     * @param  $id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getSupplier($id)
    {
        $redis = Redis::connection('cache');
        $supplier = $redis->hgetall($id . '_id_supplier');
        if (empty($supplier)) {
            $supplier = DB::table('suppliers')->where('id', $id)->first();
            if (!is_null($supplier)) {
                $redis->hset($id . '_id_supplier', 'id', $id);
                $redis->hset($id . '_id_supplier', 'name', data_get($supplier, 'name'));
                $redis->hset($id . '_id_supplier', 'no', data_get($supplier, 'no'));
                $redis->hset($id . '_id_supplier', 'link_name', data_get($supplier, 'link_name'));
                $redis->hset($id . '_id_supplier', 'phone', data_get($supplier, 'phone'));
                $redis->hset($id . '_id_supplier', 'address', data_get($supplier, 'address'));
                $redis->hset($id . '_id_supplier', 'status', data_get($supplier, 'status'));
            }
            $supplier = $redis->hgetall($id . '_id_supplier');
        }
        $redis->expire($id . '_id_supplier', 60 * 30);
        return $supplier;
    }

    /**
     * 获取部门详情
     *
     * @param  $id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getDepartment($id)
    {
        $redis = Redis::connection('cache');
        $department = $redis->hgetall($id . '_id_department');
        if (empty($department)) {
            $department = DB::table('departments')->where('id', $id)->first();
            if (!is_null($department)) {
                $redis->hset($id . '_id_department', 'id', $id);
                $redis->hset($id . '_id_department', 'name', data_get($department, 'name'));
                $redis->hset($id . '_id_department', 'status', data_get($department, 'status'));
            }
            $department = $redis->hgetall($id . '_id_department');
        }
        $redis->expire($id . '_id_department', 60 * 30);
        return $department;
    }

    /**
     * 获取公司
     *
     * @param  $id
     * @return mixed
     */
    public function getCompany($id)
    {
        $redis = Redis::connection('cache');
        $company = $redis->hgetall($id . '_id_company');
        if (empty($company)) {
            $company = DB::table('companies')->where('id', $id)->first();
            if (!is_null($company)) {
                $redis->hset($id . '_id_company', 'id', $id);
                $redis->hset($id . '_id_company', 'name', data_get($company, 'name'));
                $redis->hset($id . '_id_company', 'no', data_get($company, 'company_no'));
                $redis->hset($id . '_id_company', 'user', data_get($company, 'user'));
                $redis->hset($id . '_id_company', 'phone', data_get($company, 'phone'));
                $redis->hset($id . '_id_company', 'address', data_get($company, 'address'));
                $redis->hset($id . '_id_company', 'address_detail', data_get($company, 'address_detail'));
                $redis->hset($id . '_id_company', 'remark', data_get($company, 'remark'));
            }
            $company = $redis->hgetall($id . '_id_company');
        }
        $redis->expire($id . '_id_company', 60 * 30);
        return $company;
    }

    /**
     * 获取仓库
     *
     * @param  $id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getLocation($id)
    {
        $redis = Redis::connection('cache');
        $location_info = $redis->hgetall($id . '_id_location');
        if (empty($location_info)) {
            $location_info = DB::table('locations')->where('id', $id)->first();
            if (!is_null($location_info)) {
                $redis->hset($id . '_id_location', 'id', $id);
                $redis->hset($id . '_id_location', 'name', data_get($location_info, 'name'));
                $redis->hset($id . '_id_location', 'no', data_get($location_info, 'location_no'));
                $redis->hset($id . '_id_location', 'company_id', data_get($location_info, 'company_id'));
                $redis->hset($id . '_id_location', 'link_user', data_get($location_info, 'link_user'));
                $redis->hset($id . '_id_location', 'link_phone', data_get($location_info, 'link_phone'));
                $redis->hset($id . '_id_location', 'address', data_get($location_info, 'address'));
                $redis->hset($id . '_id_location', 'address_detail', data_get($location_info, 'address_detail'));
                $redis->hset($id . '_id_location', 'remark', data_get($location_info, 'remark'));
            }
            $location_info = $redis->hgetall($id . '_id_location');
        }
        $redis->expire($id . '_id_location', 60 * 30);
        return $location_info;
    }

    /**
     * 根据编号获取仓库详情
     * @param $no
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getLocationByNo($no)
    {
        $redis = Redis::connection('cache');
        $location_info = $redis->hgetall($no . '_location');
        if (empty($location_info)) {
            $location_info = DB::table('locations')->where('location_no', $no)
                ->whereNull('deleted_at')->first();
            if (!is_null($location_info)) {
                $redis->hset($no . '_location', 'id', data_get($location_info, 'id'));
                $redis->hset($no . '_location', 'name', data_get($location_info, 'name'));
                $redis->hset($no . '_location', 'no', $no);
                $redis->hset($no . '_location', 'company_id', data_get($location_info, 'company_id'));
                $redis->hset($no . '_location', 'link_user', data_get($location_info, 'link_user'));
                $redis->hset($no . '_location', 'link_phone', data_get($location_info, 'link_phone'));
                $redis->hset($no . '_location', 'address', data_get($location_info, 'address'));
                $redis->hset($no . '_location', 'address_detail', data_get($location_info, 'address_detail'));
                $redis->hset($no . '_location', 'remark', data_get($location_info, 'remark'));
            }
            $location_info = $redis->hgetall($no . '_location');
        }
        $redis->expire($no . '_location', 30 * 60);
        return $location_info;
    }

    /**
     * 根据商品编号获取商品详情
     *
     * @param  $goods_no
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getGoodsByNo($goods_no)
    {
        $redis = Redis::connection('cache');
        $goods_info = $redis->hgetall($goods_no . '_goods');
        if (empty($goods_info)) {
            $goods_info = DB::table('goods')->where('goods_no', $goods_no)->first();
            if (!is_null($goods_info)) {
                $redis->hset($goods_no . '_goods', 'name', data_get($goods_info, 'purchase_name'));
                $redis->hset($goods_no . '_goods', 'goods_name', data_get($goods_info, 'goods_name'));
                $redis->hset($goods_no . '_goods', 'no', $goods_no);
                $redis->hset($goods_no . '_goods', 'unit', data_get($goods_info, 'unit'));
                $redis->hset($goods_no . '_goods', 'attribute', data_get($goods_info, 'attribute'));
                $redis->hset($goods_no . '_goods', 'classify', data_get($goods_info, 'classify'));
                $redis->hset($goods_no . '_goods', 'goods_type', data_get($goods_info, 'goods_type'));
                $redis->hset($goods_no . '_goods', 'is_software', data_get($goods_info, 'is_software'));
                $redis->hset(
                    $goods_no . '_goods',
                    'master_image',
                    data_get($goods_info, 'master_image')
                );
                $redis->hset($goods_no . '_goods', 'goods_images', data_get($goods_info, 'goods_images'));
            }
            $goods_info = $redis->hgetall($goods_no . '_goods');
        }
        $redis->expire($goods_no . '_goods', 30 * 60);
        return $goods_info;
    }

    /**
     * 获取审核等级
     * @param $type
     * @return mixed
     */
    public function getAuditClassify($type)
    {
        $redis = Redis::connection('cache');
        $classify = $redis->hget('audit_classify', $type);
        if (empty($classify)) {
            $classify = DB::table('order_audit_classify')->where('order_type', $type)
                ->whereNull('deleted_at')->first();
            if (!is_null($classify)) {
                $redis->hset('audit_classify', $type, data_get($classify, 'classify'));
            }
            $classify = $redis->hget('audit_classify', $type);
        }
        $redis->expire('audit_classify', 30 * 60);
        return $classify;
    }

    /**
     * 获取当前用户信息
     */
    public function getCurrentUser()
    {
        $id = auth('admin')->id();
        $query = DB::table('admins')->join(
            'admin_role',
            function ($join) use ($id) {
                $join->on('admins.id', '=', 'admin_role.admin_id')->where('admins.id', $id);
            }
        );
        $query->leftJoin(
            'roles',
            function ($join) {
                $join->on('admin_role.admin_id', '=', 'roles.id');
            }
        );
        return $query->select(
            'admins.id as id',
            'admins.username as account',
            'admins.nickname as username',
            'roles.name as role',
            'admins.company_id as company_id',
            'admins.department_id as department_id'
        )->first();
    }


    /**
     * 获取公司库存数量
     * @param int $companyId
     * @return array
     */
    public function getStorageNumByCompanyId(int $companyId): array
    {
        $locationIds = DB::table('locations')->where(['company_id' => $companyId, 'status' => 0])
            ->whereNull('deleted_at')
            ->get('id')->pluck('id')->unique()->flatten()->toArray();
        $total = DB::table('purchase_detail')->whereIn('location_id', $locationIds)
            ->where('finished', 1)->whereNull('deleted_at')->get();
        $items = [];
        if ($total->count() > 0) {
            $totalGroup = $total->groupBy('goods_no');
            foreach ($totalGroup as $goods_no => $group) {
                $items[$goods_no] = $group->sum('total_num');
            }
        }
        return $items;
    }

    /**
     * 获取公司仓库锁定数量
     * @param int $companyId
     * @return array
     */
    public function getLockNumByCompanyId(int $companyId): array
    {
        $locationIds = DB::table('locations')->where(['company_id' => $companyId, 'status' => 0])
            ->whereNull('deleted_at')
            ->get('id')->pluck('id')->unique()->flatten()->toArray();
        $total = DB::table('lock_inventory')->whereIn('location_id', $locationIds)->get();
        $items = [];
        if ($total->count() > 0) {
            $totalGroup = $total->groupBy('goods_no');
            foreach ($totalGroup as $goods_no => $group) {
                $items[$goods_no] = $group->sum('lock_num');
            }
        }
        return $items;
    }

    /**
     * 获取仓库可用库存
     * @param array $locationIds
     * @return array
     */
    public function getStorageNumByLocationId(array $locationIds): array
    {
        $total = DB::table('purchase_detail')->whereIn('location_id', $locationIds)
            ->where('finished', 1)->whereNull('deleted_at')->get();
        $items = [];
        if ($total->count() > 0) {
            $totalGroup = $total->groupBy(['location_id', 'goods_no']);
            foreach ($totalGroup as $locationId => $goodsInfo) {
                foreach ($goodsInfo as $goods_no => $group) {
                    $items[$locationId][$goods_no] = $group->sum('total_num');
                }
            }
        }
        return $items;
    }

    /**
     * 获取仓库锁定库存
     * @param array $locationIds
     * @return array
     */
    public function getLockNumByLocationId(array $locationIds): array
    {
        $total = DB::table('lock_inventory')->whereIn('location_id', $locationIds)
            ->whereNull('deleted_at')->get();
        $items = [];
        if ($total->count() > 0) {
            $totalGroup = $total->groupBy(['location_id', 'goods_no']);
            foreach ($totalGroup as $locationId => $goodsInfo) {
                foreach ($goodsInfo as $goods_no => $group) {
                    $items[$locationId][$goods_no] = $group->sum('lock_num');
                }
            }
        }
        return $items;
    }
}
