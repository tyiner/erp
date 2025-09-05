<?php

namespace App\Services\Stock;

use App\Models\Purchase\SnCode;
use App\Models\Purchase\Unit;
use App\Models\Stock\Invoice;
use App\Models\Stock\InvoiceDetail;
use App\Models\Stock\InvSnRelation;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Class StockService
 *
 * @package App\Services\Stock
 */
class StockService extends BaseService
{
    private $model;
    private $invDetail;
    private $unit;
    private $snCode;
    private $invSn;

    private $reduce = [];

    /**
     * StockService constructor.
     *
     * @param Invoice $model
     * @param InvoiceDetail $invDetal
     * @param Unit $unit
     * @param SnCode $snCode
     * @param InvSnRelation $invSn
     */
    public function __construct(
        Invoice $model,
        InvoiceDetail $invDetal,
        Unit $unit,
        SnCode $snCode,
        InvSnRelation $invSn
    ) {
        $this->snCode = $snCode;
        $this->unit = $unit;
        $this->model = $model;
        $this->invDetail = $invDetal;
        $this->invSn = $invSn;
    }

    /**
     * 新建备货单
     *
     * @param array $data
     * @return Invoice
     * @throws \Exception
     */
    public function add(array $data): Invoice
    {
        $detail = data_get($data, 'detail');
        $type = data_get($data, 'type');
        DB::beginTransaction();
        $ret = $this->model->fill($data)->save();
        if (!$ret) {
            DB::rollBack();
            error("数据添加失败");
        }
        try {
            array_walk(
                $detail,
                function (&$item, $k, $id) use ($type) {
                    $item['type'] = $type;
                    $item['total_num'] = data_get($item, 'num');
                    $item['unit'] = data_get($item, 'unit');
                },
                $this->model->id
            );
            $ret = $this->invDetail->addAll($detail);
            if (!$ret) {
                DB::rollBack();
                error("添加数据失败");
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            error("添加数据失败");
        }
        return $this->model;
    }

    /**
     * 添加影响库存数据
     *
     * @param array $data
     * @return Invoice
     */
    public function addDetail(array $data): Invoice
    {
        $detail = data_get($data, 'detail');
        DB::beginTransaction();
        $this->model->fill($data)->save();
        try {
            array_walk(
                $detail,
                function (&$item, $k, $id) {
                    $info['invoice_id'] = $id;
                    $info['type'] = data_get($item, 'type');
                    $info['price'] = data_get($item, 'price');
                    $info['tax'] = data_get($item, 'tax');
                    $info['location_id'] = data_get($item, 'location_id');
                    $info['goods_id'] = data_get($item, 'goods_id');
                    $info['num'] = data_get($item, 'num');
                    $info['total_num'] = data_get($item, 'total_num');
                    $info['unit_id'] = data_get($item, 'unit_id');
                    $ret = $this->invDetail->fill($info)->save();
                    if (!$ret) {
                        DB::rollBack();
                        error("添加表格详情数据失败");
                    }
                    $id = $this->invDetail->id;
                    $data = array_map(
                        function ($item) use ($id) {
                            $info['invoice_detail_id'] = $id;
                            $info['sn_code_id'] = $item;
                            return $info;
                        },
                        data_get($item, 'ids')
                    );
                    $ret = $this->invSn->addAll($data);
                    if (!$ret) {
                        DB::rollBack();
                        error("sn码关联信息数据添加失败");
                    }
                },
                $this->model->id
            );
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            error("添加数据失败");
        }
        return $this->model;
    }

    /**
     * 删除订单
     *
     * @param array $ids
     */
    public function delete(array $ids)
    {
        $invIds = $this->invDetail->whereIn('invoice_id', $ids)->select('id')->get();
        DB::beginTransaction();
        try {
            $this->invSn->whereIn('invoice_detail_id', $invIds)->delete();
            $this->invDetail->whereIn('id', $invIds)->delete();
            $this->model->whereIn('id', $ids)->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            error("删除失败");
        }
    }

    /**
     * 获取备货单列表
     *
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function getList(array $data): LengthAwarePaginator
    {
        $limit = data_get($data, 'limit', 20);
        $query = $this->model;
        if (data_get($data, 'type')) {
            $query = $query->where('type', data_get($data, 'type'));
        }
        if (data_get($data, 'company_id')) {
            $query = $query->where('company_id', data_get($data, 'company_id'));
        }
        if (data_get($data, 'ids')) {
            $query = $query->whereIn('id', $data['ids']);
        }
        return $query->with('company', 'supplier')->paginate($limit);
    }

    /**
     * 获取明细列表
     *
     * @param array $conditions
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getDetailList(array $conditions): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $limit = data_get($conditions, 'limit');
        $query = DB::table('invoice_detail')->join(
            'invoice',
            function ($join) {
                $join->on('invoice_detail.invoice_id', '=', 'invoice.id');
            }
        );
        if (data_get($conditions, 'begin_at')) {
            $query = $query->where('invoice_detail.created_at', '>', $conditions['begin_at']);
        }
        if (data_get($conditions, 'end_at')) {
            $query = $query->where('invoice_detail.created_at', '<', $conditions['end_at']);
        }
        return $query->paginate($limit);
    }

    /**
     * 获取公司库存
     */
    public function getInventory()
    {
    }

    /**
     * 根据订单号获取信息
     *
     * @param array $data
     * @return mixed
     */
    public function getByNo(array $data)
    {
        return $this->model->where('no', $data['no'])->first();
    }


    /**
     * @param InvoiceDetail $invDetail
     */
    public function setInvDetail(InvoiceDetail $invDetail): void
    {
        $this->invDetail = $invDetail;
    }

    /**
     * @param int $id
     * @return array
     */
    public function get(int $id): array
    {
        $ret = $this->model->with('invoiceDetail', 'findSon', 'company', 'supplier')
            ->where('id', $id)->first();
        $data = [];

        if (!is_null($ret) && in_array(
                $ret['type'],
                [STOCK_PLAN, STOCK_BACK_PLAN]
            )
        ) {
            $data = $this->getPreparePlan($ret);
        }

        if (!is_null($ret) && in_array(
                $ret['type'],
                [STOCK_ARRIVAL]
            )
        ) {
            $this->getArrival($ret);
        }

        if (!is_null($ret) && in_array(
                $ret['type'],
                [STOCK_OTHER_OUT, STOCK_OTHER_IN]
            )
        ) {
            $data = $this->getStorage($ret);
        }

        return $data;
    }

    /**
     * 获取到货单详情
     *
     * @param $ret
     */
    private function getArrival($ret)
    {
        dd($ret->toArray());
    }

    /**
     * 获取出入库详情
     *
     * @param $ret
     */
    private function getStorage($ret)
    {
        dd($ret);
    }

    /**
     * 获取备货计划详情
     *
     * @param  $ret
     * @return array
     */
    private function getPreparePlan($ret): array
    {
        $data['company'] = data_get($ret, 'company.name', '');
        $data['no'] = data_get($ret, 'no', '');
        $data['post_data'] = json_decode(data_get($ret, 'post_data', ''), true);
        foreach ($data['post_data'] as &$value) {
            $unit = data_get($value, 'unit');
            $location = $this->getLocation(data_get($value, 'location_id'));
            $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
            $value['total_num'] = data_get($value, 'num') * $unit->unit_num;
            $value['unit_num'] = data_get($unit, 'min_unit_name');
            $value['location'] = data_get($location, 'name', '');
            $value['goods_name'] = data_get($goods, 'name');
        }
        $data['checked'] = data_get($ret, 'checked', '');
        $data['checked_user'] = data_get($ret, 'checked_user', '');
        $data['supplier']['name'] = data_get($ret, 'supplier.name', '');
        $data['supplier']['link_name'] = data_get($ret, 'supplier.link_name', '');
        $data['supplier']['address'] = data_get($ret, 'supplier.address', '');
        $data['supplier']['phone'] = data_get($ret, 'supplier.phone', '');
        if (data_get($ret, 'findSon')) {
            $arrivalDetail = [];
            foreach (data_get($ret, 'findSon') as $arrival) {
                $company = $this->getCompany(data_get($arrival, 'company_id'));
                $item['company'] = data_get($company, 'name');
                $item['no'] = data_get($arrival, 'no', '');
                $item['status'] = data_get($arrival, 'status');
                $item['user'] = data_get($arrival, 'user');
                $item['checked'] = data_get($arrival, 'checked');
                $item['checked_user'] = json_decode(data_get($arrival, 'checked_user'), true);
                $item['post_data'] = json_decode(data_get($arrival, 'post_data', ''), true);
                foreach ($item['post_data'] as &$value) {
                    $unit = data_get($value, 'unit');
                    $location = $this->getLocation(data_get($value, 'location_id'));
                    $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
                    $value['unit_name'] = data_get($unit, 'min_unit_name');
                    $value['location'] = data_get($location, 'name', '');
                    $value['goods_name'] = data_get($goods, 'name');
                }
                $arrivalDetail[] = $item;
            }
            $data['arrival'] = $arrivalDetail;
        }
        return $data;
    }
}
