<?php

namespace App\Services\Partner;

use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Class YeHaiLocationService
 *
 * @package App\Services\Partner
 */
class YeHaiLocationService extends BaseService
{
    private $postData = [
        'warehouseId' => '2',
        'customerCode' => 'LCS2020071403192',
        'customerId' => 'LCS2020071403192',
    ];

    /**
     * 同步商品信息
     *
     * @param array $data
     * @return array
     */
    public function informGoods(array $data): array
    {
        $appKey = env('YUE_HAI_APP_KEY');
        $goodsInfo = $this->postData;
        $goodsInfo['name'] = data_get($data, 'name');
        $goodsInfo['code'] = data_get($data, 'goods_no');
        $goodsInfo['unit'] = data_get($data, 'unit');
        $goodsInfo['isExpControl'] = false;
        $headers = [
            'appKey' => $appKey,
            'timeStamp' => date("Y-m-d H:i:s"),
            'sign' => $this->makeSign($goodsInfo),
        ];
        $goodsInfo = json_encode($goodsInfo);
        $response = Http::withHeaders($headers)->withBody(
            $goodsInfo,
            "application/json; charset=UTF-8"
        )->acceptJson()
            ->post(YUE_HAI_INFORM_GOODS);
        $arr = json_decode($response->body(), true);
        if ($arr['success'] == false) {
            return [
                'code' => -1,
                'message' => data_get($arr, 'msg'),
                'data' => data_get($data, 'no'),
            ];
        } else {
            return [
                'code' => 200,
                'message' => data_get($arr, 'msg'),
                'data' => data_get($data, 'no'),
            ];
        }
    }

    /**
     * 销售退货入库
     *
     * @param array $data
     * @return array
     */
    public function saleBackSend(array $data): array
    {
        return $this->sendStoreMsg($data);
    }

    /**
     * 采购信息推送越海仓
     *
     * @param array $data
     * @return array
     */
    public function sendStoreMsg(array $data): array
    {
        $type = data_get(collect($data)->first(), 'type');
        switch ($type) {
            case PURCHASE_STORE_IN:
                $flag = 10;
                $origin = '采购入库';
                break;
            case PURCHASE_STOCK_BACK:
                $flag = 30;
                $origin = '退货入库';
                break;
            case PURCHASE_TRANSFER:
                $flag = 20;
                $origin = '调拨入库';
                break;
            default:
                error("不存在的入库类型");
        }
        $appKey = env('YUE_HAI_APP_KEY');
        $custOrdNo = data_get($data, '0.no');
        $customerId = data_get($data, '0.supplier_id');
        $details = $detail = [];
        foreach ($data as $value) {
            $detail['code'] = data_get($value, 'goods_no');
            $detail['barcode'] = data_get($value, 'goods_no');
            $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
            $detail['name'] = data_get($goods, 'name');
            $detail['qualityId'] = 'E';
            $detail['qty'] = data_get($value, 'num');
            $details[] = $detail;
        }
        $postData = [
            'custOrdNo' => $custOrdNo,    //客户单号
            'asnType' => $flag, //入库类型 10 采购入库;20 转仓入库;30 退货入库
            'origin' => $origin,
            'beginTime' => date("Y-m-d H:i:s"), //预计抵达时间
            'reason' => '采购入库',
            'remark' => data_get($value, 'remark'), //备注
            'cargoList' => $details,
        ];
        $postData = array_merge_recursive_distinct($this->postData, $postData);
        $headers = [
            'appKey' => $appKey,
            'timeStamp' => date("Y-m-d H:i:s"),
            'sign' => $this->makeSign($postData),
        ];
        $postData = json_encode($postData);
        $response = Http::withHeaders($headers)->withBody(
            $postData,
            "application/json; charset=UTF-8"
        )->acceptJson()
            ->post(YUE_HAI_STORE);
        $arr = json_decode($response->body(), true);
        if ($arr['success'] == false) {
            return [
                'code' => -1,
                'message' => $arr['msg'],
                'data' => data_get($data, 'no'),
            ];
        } else {
            return [
                'code' => 200,
                'message' => '越海入库信息推送成功',
                'data' => data_get($data, 'no'),
            ];
        }
    }

    /**
     * 备货出库
     *
     * @param array $data
     * @return array
     */
    public function saleSend(array $data): array
    {
        $type = data_get($data, '0.type');
        switch ($type) {
            case PURCHASE_STOCK_OUT:
                $flag = 10;
                $origin = '备货出库';
                break;
            case PURCHASE_TRANSFER:
                $flag = 20;
                $origin = '调拨出库';
                break;
            default:
                error("不存在的出库类型");
        }
        $appKey = env('YUE_HAI_APP_KEY');
        $details = $detail = [];
        foreach ($data as $value) {
            $detail['code'] = data_get($value, 'goods_no');
            $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
            $detail['name'] = data_get($goods, 'name');
            $detail['qualityId'] = 'E';
            $detail['qty'] = data_get($value, 'num');
            $details[] = $detail;
        }
        $parent_id = data_get($data, '0.parent_id');
        $parent = DB::table('purchases')->where('id', $parent_id)->select('no')->first();
        $custOrdNo = data_get($data, '0.no');
        $consigneeInfo = json_decode(data_get($data, '0.consignee_info'));
        $postData = [
            'CustOrderNo' => $custOrdNo,    //客户单号
            'asnType' => $flag,            //入库类型 10 采购出库;20 转仓出库;30 退货出库(暂时未使用)
            'origin' => $origin,
            'priority' => 1,              //优先级
            'IsCOD' => false,             //是否货到付款
            'carrierId' => 'jd',
            'SourceOrderNo' => data_get($parent, 'no'),     //来源单号
            'beginTime' => date("Y-m-d H:i:s"), //预计抵达时间
            'endTime' => '',               //预期抵达时间结束
            'reason' => $origin,
            'remark' => data_get($data, '0.remark'), //备注
            'receiverInfo' => [
                //'consigneeZipcode' => generateRandomCode('NUMBER'),
                'consigneeAddress' => data_get($consigneeInfo, 'address'),
                'consigneeName' => data_get($consigneeInfo, 'name'),
                'consigneePhone' => data_get($consigneeInfo, 'phone'),
            ],
            'saleOrderList' => $details,
        ];
        $postData = array_merge_recursive_distinct($this->postData, $postData);
        $headers = [
            'appKey' => $appKey,
            'timeStamp' => date("Y-m-d H:i:s"),
            'sign' => $this->makeSign($postData),
        ];
        $postData = json_encode($postData);
        $response = Http::withHeaders($headers)->withBody(
            $postData,
            "application/json; charset=UTF-8"
        )->acceptJson()
            ->post(YUE_HAI_SALES);
        $arr = json_decode($response->body(), true);
        if ($arr['success'] == false) {
            return [
                'code' => -1,
                'message' => $arr['msg'],
                'data' => data_get($data, 'no'),
            ];
        } else {
            return [
                'code' => 200,
                'message' => '越海出库信息推送成功',
                'data' => data_get($data, 'no'),
            ];
        }
    }

    /**
     * 调拨出库
     *
     * @param array $data
     * @return array
     */
    public function transferOut(array $data): array
    {
        return $this->saleSend($data);
    }

    /**
     * 其他出库
     *
     * @param array $data
     * @return string
     */
    public function otherOut(array $data): string
    {
        $appKey = env('YUE_HAI_APP_KEY');
        $details = $detail = [];
        foreach ($data as $value) {
            $detail['skuId'] = data_get($value, 'goods_no');
            $detail['cargoCode'] = data_get($value, 'goods_no');
            $detail['qualityId'] = 'E';
            $detail['qty'] = data_get($value, 'num');
            $details[] = $detail;
        }
        $customerId = data_get($data, '0.company_id');
        $type = data_get($data, '0.type');
        $custOrdNo = data_get($data, '0.no');
        $consigneeInfo = json_decode(data_get($data, '0.consignee_info'));
        $data = [
            'custOrdNo' => $custOrdNo,    //客户单号
            'stockOutReason' => '调拨出库',
            'stockOutType' => $type,              //入库类型 10 采购入库;20 转仓入库;30 退货入库
            'origin' => '采购入库',
            'priority' => 1,              //优先级
            'IsCOD' => false,             //是否货到付款
            'beginTime' => date("Y-m-d H:i:s"), //预计抵达时间
            'endTime' => '',               //预期抵达时间结束
            'reason' => '采购入库',
            'remark' => '', //备注
            'receiverInfo' => [
                'consigneeZipcode' => generateRandomCode('NUMBER', 9),
                'consigneeAddress' => data_get($consigneeInfo, 'address'),
                'consigneeName' => data_get($consigneeInfo, 'name'),
                'consigneePhone' => data_get($consigneeInfo, 'phone'),
            ],
            'stockOutItems' => $details,
        ];

        $data = json_encode($data);
        $headers = [
            'appKey' => $appKey,
            'timeStamp' => date("Y-m-d H:i:s"),
            'sign' => $this->makeSign($data),
        ];
        $response = Http::withHeaders($headers)->withBody(
            $data,
            "application/json; charset=UTF-8"
        )->acceptJson()
            ->post(YUE_HAI_OTHER_OUT);
        return $response->body();
    }

    /**
     * 制作签名
     *
     * @param array $data
     * @return string
     */
    private function makeSign(array $data): string
    {
        $secretKey = env('YUE_HAI_SECRET');
        empty($secretKey) && error("请配置第三方密钥");
        $postData = json_encode($data);
        $str = md5($secretKey . $postData . $secretKey, true);
        return bin2hex($str);
    }
}
