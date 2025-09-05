<?php

namespace App\Services\SerialStream;

use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseDetail;
use App\Models\Purchase\PurSnRelation;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class StreamLogService extends BaseService
{
    private $orderTypeMap = [
        180023 => 8,  //销售出库
        120023 => 10, //采购入库
        120028 => 11, //备货出库
        120030 => 12, //备货退货
        180025 => 13, //销售退货
        140021 => 14, //其它入库
        140022 => 15, //其它出库
        140023 => 15, //其它退库
        160621 => [
            17,  //调拨出
            18,  //调拨入
        ]
    ];
    private $purchase;
    private $purchaseDetail;
    private $purSnRelation;

    public function __construct(
        Purchase $purchase,
        PurchaseDetail $purchaseDetail,
        PurSnRelation $purSnRelation
    ) {
        $this->purchase = $purchase;
        $this->purchaseDetail = $purchaseDetail;
        $this->purSnRelation = $purSnRelation;
    }

    /**
     * 发送Sn码到溯源系统
     * @param int $id
     */
    public function send(int $id)
    {
        $purchaseDetails = $this->purchaseDetail->where('purchase_id', $id)->get();
        $purchaseDetailIds = $purchaseDetails->pluck('id')->flatten()->toArray();
        $query = DB::table('pur_sn_relation')->join('sn_code', function ($join) use ($purchaseDetailIds) {
            return $join->on('pur_sn_relation.sn_id', '=', 'sn_code.id')
                ->whereIn('pur_sn_relation.purchase_detail_id', $purchaseDetailIds)
                ->whereNull('pur_sn_relation.deleted_at');
        });
        $ret = $query->get(DB::raw('distinct(sn)'))->pluck('sn')->flatten()->toArray();
        $purchase = $this->purchase->where('id', $id)->first();
        $type = data_get($purchase, 'type');
        if (PURCHASE_SALE_OUT != $type && PURCHASE_SALE_RED != $type) {
            if (count($ret) == 0) {
                error("单据未绑定SN码");
            }
        }
        if (empty($type) || !in_array($type, array_keys($this->orderTypeMap))) {
            error("不存在的单据类型");
        }
        $data['serialno'] = implode(',', $ret);
        $data['location_id'] = data_get($purchase, 'location_id');
        $data['receiving_location_id'] = data_get($purchase, 'receiving_location_id');
        $subCompanyId = data_get($purchase, 'sub_company_id');
        $supplierId = data_get($purchase, 'supplier_id');
        $data['supplier_name'] = data_get($this->getSupplier($supplierId), 'name');
        $data['sub_company_name'] = data_get($this->getCompany($subCompanyId), 'name');
        $data['customer'] = data_get($purchase, 'customer');
        $data['lcid'] = $this->orderTypeMap[$type];
        switch ($type) {
            case PURCHASE_STORE_IN:
                $this->sendStoreIn($data);
                break;
            case PURCHASE_SALE_OUT:
                $data['id'] = $id;
                $this->sendSaleOut($data);
                break;
            case PURCHASE_SALE_RED:
                $data['id'] = $id;
                $this->sendSaleRed($data);
                break;
            case PURCHASE_STOCK_OUT:
                $this->sendStockOut($data);
                break;
            case PURCHASE_STOCK_RED:
                $this->sendStockRed($data);
                break;
            case PURCHASE_OTHER_IN:
                $this->sendOtherIn($data);
                break;
            case PURCHASE_OTHER_OUT:
                $this->sendOtherOut($data);
                break;
            case PURCHASE_OTHER_RED:
                $this->sendOtherRed($data);
                break;
            case PURCHASE_TRANSFER:
                $this->sendTransfer($data);
                break;
            default:
                error("不存在的推送类型");
        }
    }

    /**
     * 其它出库单SN码信息推送
     * @param array $data
     */
    private function sendOtherOut(array $data)
    {
        $locationName = data_get($this->getLocation($data['location_id']), 'name');
        $datas[] = [
            'serialno' => $data['serialno'],
            'lcid' => $data['lcid'],
            'process' => $locationName . '(仓库)  其它出库到 ' . data_get($data, 'customer', '未知') . '（领用人）',
            'agent' => data_get($this->getCurrentUser(), 'username', 'admin'),
        ];
        sendSnStatus($datas);
        success("其它出库单SN码推送成功");
    }

    /**
     * 其它入库SN码推送溯源系统
     * @param array $data
     */
    private function sendOtherIn(array $data)
    {
        $locationName = data_get($this->getLocation($data['location_id']), 'name');
        $datas[] = [
            'serialno' => $data['serialno'],
            'lcid' => $data['lcid'],
            'process' => data_get($data, 'customer', '未知') . '(经办人)  其它入库到 ' . $locationName . '（仓库）',
            'agent' => data_get($this->getCurrentUser(), 'username', 'admin'),
        ];
        sendSnStatus($datas);
        success("其它入库单SN码推送成功");
    }

    /**
     * 其它出库退货SN码推送溯源系统
     * @param array $data
     */
    private function sendOtherRed(array $data)
    {
        $locationName = data_get($this->getLocation($data['location_id']), 'name');
        $datas[] = [
            'serialno' => $data['serialno'],
            'lcid' => $data['lcid'],
            'process' => data_get($data, 'customer', '未知') . '(领用人)  其它退货到 ' . $locationName . '（仓库）',
            'agent' => data_get($this->getCurrentUser(), 'username', 'admin'),
        ];
        sendSnStatus($datas);
        success("其它退库单SN码推送成功");
    }

    /**
     * 采购入库单Sn码信息推送
     * @param array $data
     */
    private function sendStoreIn(array $data)
    {
        $locationName = data_get($this->getLocation($data['location_id']), 'name');
        $supplier = data_get($data, 'supplier_name');
        $datas[] = [
            'serialno' => $data['serialno'],
            'lcid' => $data['lcid'],
            'process' => $locationName . ':采购入库（供应商' . $supplier . '）',
            'agent' => data_get($this->getCurrentUser(), 'username', 'admin'),
        ];
        sendSnStatus($datas);
        success("采购入库单SN码推送成功");
    }

    /**
     * 销售出库SN码推送
     * @param array $data
     */
    private function sendSaleOut(array $data)
    {
        $locationName = data_get($this->getLocation($data['location_id']), 'name', 'erp仓库id:' . $data['location_id']);
        $purchaseId = data_get($data, 'id');
        $query = DB::table('out_sale_relation')->join('sale_orders', function ($join) use ($purchaseId) {
            return $join->on('out_sale_relation.sale_order_id', '=', 'sale_orders.id')
                ->where('out_sale_relation.purchase_id', $purchaseId);
        });
        $ret = $query->get()->toArray();
        foreach ($ret as $item) {
            $serials = implode(',', json_decode(data_get($item, 'serials', '{}'), true));
            $datas[] = [
                'serialno' => $serials,
                'lcid' => $data['lcid'],
                'process' => $locationName . '(仓库) 销售出库到 ' . data_get($item, 'order_no') . '',
                'agent' => data_get($this->getCurrentUser(), 'username', 'admin'),
            ];
        }
        sendSnStatus($datas);
        success("销售出库单SN码推送成功");
    }

    /**
     * 销售出库红字SN码推送
     * @param array $data
     */
    private function sendSaleRed(array $data)
    {
        $locationName = data_get($this->getLocation($data['location_id']), 'name', 'erp仓库id:' . $data['location_id']);
        $purchaseId = data_get($data, 'id');
        $query = DB::table('red_sale_relation')->join('sale_orders', function ($join) use ($purchaseId) {
            return $join->on('red_sale_relation.sale_order_id', '=', 'sale_orders.id')
                ->where('red_sale_relation.purchase_id', $purchaseId);
        });
        $ret = $query->get()->toArray();
        foreach ($ret as $item) {
            $serials = implode(',', json_decode(data_get($item, 'serials', '{}'), true));
            $datas[] = [
                'serialno' => $serials,
                'lcid' => $data['lcid'],
                'process' => $locationName . '(仓库) 销售退货到 ' . data_get($item, 'order_no') . '',
                'agent' => data_get($this->getCurrentUser(), 'username', 'admin'),
            ];
        }
        sendSnStatus($datas);
        success("销售退货SN码推送成功");
    }

    /**
     * 备货出库单SN码推送
     * @param array $data
     */
    private function sendStockOut(array $data)
    {
        $locationName = data_get($this->getLocation($data['location_id']), 'name');
        $subCompanyName = data_get($data, 'sub_company_name');
        $datas[] = [
            'serialno' => $data['serialno'],
            'lcid' => $data['lcid'],
            'process' => $locationName . '（仓库）:备货出库到 ' . $subCompanyName . '（备货单位）',
            'agent' => data_get($this->getCurrentUser(), 'username', 'admin'),
        ];
        sendSnStatus($datas);
        success("备货出库单SN码推送成功");
    }

    /**
     * 备货出库单红字Sn码推送溯源系统
     * @param array $data
     */
    private function sendStockRed(array $data)
    {
        $locationName = data_get($this->getLocation($data['location_id']), 'name');
        $subCompanyName = data_get($data, 'sub_company_name');
        $datas[] = [
            'serialno' => $data['serialno'],
            'lcid' => $data['lcid'],
            'process' => $subCompanyName . '（备货单位）:备货退货到 ' . $locationName . '（仓库）',
            'agent' => data_get($this->getCurrentUser(), 'username', 'admin'),
        ];
        sendSnStatus($datas);
        success("备货出库单红字SN码推送成功");
    }

    /**
     * 调拨单Sn码信息推送
     * @param array $data
     */
    private function sendTransfer(array $data)
    {
        $locationId = data_get($data, 'location_id');
        $locationName = data_get($this->getLocation($locationId), 'name');
        $receivingLocationId = data_get($data, 'receiving_location_id');
        $receivingLocationName = data_get($this->getLocation($receivingLocationId), 'name');
        $time = time();
        $datas[] = [
            'serialno' => $data['serialno'],
            'lcid' => $data['lcid'][0],
            'process' => $locationName . ':调拨出库',
            'agent' => data_get($this->getCurrentUser(), 'username', 'admin'),
            'czdate' => date("Y-m-d H:i:s", $time),
        ];
        $datas[] = [
            'serialno' => $data['serialno'],
            'lcid' => $data['lcid'][1],
            'process' => $receivingLocationName . ':调拨入库',
            'agent' => data_get($this->getCurrentUser(), 'username', 'admin'),
            'czdate' => date("Y-m-d H:i:s", $time + 1),
        ];
        sendSnStatus($datas);
        success("调拨单SN码推送成功");
    }
}
