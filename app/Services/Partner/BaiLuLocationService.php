<?php

namespace App\Services\Partner;

use App\Services\BaseService;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Class BaiLuLocationService
 *
 * @package App\Services\Partner
 */
class BaiLuLocationService extends BaseService
{
    private $http;

    private $postData = [
        'sid' => 'wms_litiWDT',
        'sign_method' => 'md5',
        'format' => 'xml',
    ];

    public function __construct(Client $http)
    {
        $this->http = $http;
    }

    /**
     * 推送商品信息
     * @param array $data
     * @return array
     */
    public function informGoods(array $data): array
    {
        $postData = $this->postData;
        $postData['method'] = 'WDT_WMS_SINGLEITEM_SYNCHRONIZE';
        $postData['timestamp'] = date('Y-m-d H:i:s');
        $postData['appkey'] = env('BAI_LU_APP_KEY');
        $goodsInfo = [
            'actionType' => data_get($data, 'actionType', 'add'),
            'warehouseCode' => $this->postData['sid'],
            'item' => [
                'itemCode' => data_get($data, 'goods_no'),
                'itemName' => data_get($data, 'name'),
                'barCode' => data_get($data, 'goods_no'),
                'stockUnit' => data_get($data, 'unit'),
                'itemType' => 'OTHER',
            ],
        ];
        $content = $this->arrayToXml($goodsInfo);
        $sign = $this->makeSign($postData, $content);
        $postData['sign'] = $sign;
        $url = BAI_LU_URL . '?' . http_build_query($postData);
        $response = Http::withBody(
            $content,
            "text/xml"
        )->post($url);
        $response = $response->body();
        $arr = $this->xmlToArray($response);
        if ($arr['flag'] == 'failure') {
            return [
                'code' => -1,
                'message' => data_get($arr, 'message'),
                'data' => $goodsInfo['item']
            ];
        } else {
            return [
                'code' => 200,
                'message' => data_get($arr, 'message'),
                'data' => $goodsInfo['item'],
            ];
        }
    }

    /**
     * 采购入库信息推送
     * @param array $data
     * @return array
     */
    public function sendStoreMsg(array $data): array
    {
        $postData = $this->postData;
        $postData['method'] = 'WDT_WMS_ENTRYORDER_CREATE';
        $postData['timestamp'] = date('Y-m-d H:i:s');
        $postData['appkey'] = env('BAI_LU_APP_KEY');
        $type = data_get($data, '0.type');
        switch ($type) {
            case PURCHASE_TRANSFER:
                $flag = 'DBRK';
                break;
            case PURCHASE_STORE_IN:
                $flag = 'CGRK';
                break;
            case PURCHASE_OTHER_IN:
                $flag = 'QTRK';
                break;
            default:
                error("不存在的出库类型");
        }
        $details = $detail = [];
        foreach ($data as $key => $value) {
            $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
            $detail['orderLineNo'] = $key + 1;
            $detail['itemCode'] = data_get($value, 'goods_no');
            $detail['inventoryType'] = 'ZP';
            $detail['itemName'] = data_get($goods, 'name');
            $detail['planQty'] = floor(data_get($value, 'num') / PACKAGE_NUM);
            $details['orderLine'][] = $detail;
        }
        $delivery = [
            'entryOrder' => [
                'entryOrderCode' => data_get($data, '0.no'),
                'orderType' => $flag,
                'warehouseCode' => $this->postData['sid'],
                'orderCreateTime' => date('Y-m-d H:i:s'),
                'supplierCode' => data_get($data, '0.supplier_id'),
                'supplierName' => '科技公司',
            ],
            'orderLines' => $details,
        ];
        $content = $this->arrayToXml($delivery);
        $sign = $this->makeSign($postData, $content);
        $postData['sign'] = $sign;
        $url = BAI_LU_URL . '?' . http_build_query($postData);
        $response = Http::withBody(
            $content,
            "text/xml"
        )->post($url);
        $response = $response->body();
        $arr = $this->xmlToArray($response);
        if ($arr['flag'] == 'failure' && false == strpos($arr['message'], '已存在')) {
            return [
                'code' => -1,
                'message' => data_get($arr, 'message', data_get($data, '0.no') . '百路驰入库推送失败'),
                'data' => ''
            ];
        } else {
            return [
                'code' => 200,
                'message' => data_get($arr, 'message'),
                'data' => ''
            ];
        }
    }

    /**
     * 百路驰回调正确信息
     * @param string $message
     * @param string $flag
     * @param int $code
     */
    public function retMsg($message = '', $flag = 'success', $code = 0)
    {
        $response = [
            'flag' => $flag,
            'code' => $code,
            'message' => $message,
        ];
        $response = $this->arrayToXml($response, false, 'response');
        exit($response);
    }

    /**
     * 百路驰回调错误信息
     * @param string $message
     * @param string $flag
     * @param int $code
     */
    public function retErrorMsg($message = '', $flag = 'error', $code = -1)
    {
        $this->retMsg($message, $flag, $code);
    }

    /**
     * 百路池发货通知接口
     *
     * @param array $data
     * @return array
     */
    public function saleSend(array $data): array
    {
        $postData = $this->postData;
        $postData['method'] = 'WDT_WMS_STOCKOUT_CREATE';
        $postData['timestamp'] = date('Y-m-d H:i:s');
        $postData['appkey'] = env('BAI_LU_APP_KEY');
        $consigneeInfo = data_get($data, '0.consignee_info');
        $consignee = json_decode($consigneeInfo, true);
        $type = data_get(collect($data)->first(), 'type');
        $no = data_get(collect($data)->first(), 'no');
        switch ($type) {
            case PURCHASE_TRANSFER:
                $flag = 'DBCK';
                break;
            case PURCHASE_STOCK_OUT:
                $flag = 'PTCK';
                break;
            default:
                error("不存在的出库类型");
        }
        $details = $detail = [];
        foreach ($data as $key => $value) {
            $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
            $detail['orderLineNo'] = $key + 1;
            $detail['itemCode'] = data_get($value, 'goods_no');
            $detail['inventoryType'] = 'ZP';
            $detail['itemName'] = data_get($goods, 'name');
            $detail['planQty'] = floor(data_get($value, 'num') / PACKAGE_NUM);
            $details['orderLine'][] = $detail;
        }
        $delivery = [
            'deliveryOrder' => [
                'deliveryOrderCode' => $no,
                'orderType' => $flag,
                'warehouseCode' => $this->postData['sid'],
                'createTime' => date('Y-m-d H:i:s'),
                'supplierCode' => data_get($data, '0.company_id'),
                'supplierName' => '总公司',
                'receiverInfo' => [
                    'name' => data_get($consignee, 'name'),
                    'tel' => '',
                    'mobile' => data_get($consignee, 'phone'),
                    'detailAddress' => data_get($consignee, 'address'),
                ],
            ],
            'orderLines' => $details,
        ];
        $content = $this->arrayToXml($delivery);
        $sign = $this->makeSign($postData, $content);
        $postData['sign'] = $sign;
        $url = BAI_LU_URL . '?' . http_build_query($postData);
        $response = Http::withBody(
            $content,
            "text/xml"
        )->post($url);
        $response = $response->body();
        $arr = $this->xmlToArray($response);
        if ($arr['flag'] == 'failure' && false == strpos($arr['message'], '已存在')) {
            return [
                'code' => -1,
                'message' => data_get($arr, 'message', data_get($data, '0.no') . '百路池出库信息推送失败'),
                'data' => $delivery
            ];
        } else {
            return [
                'code' => 200,
                'message' => data_get($arr, 'message'),
                'data' => $delivery
            ];
        }
    }

    /**
     * 退货入库推送
     *
     * @param array $data
     * @return array
     */
    public function saleBackSend(array $data): array
    {
        $postData = $this->postData;
        $postData['method'] = 'WDT_WMS_RETURNORDER_CREATE';
        $postData['timestamp'] = date('Y-m-d H:i:s');
        $postData['appkey'] = env('BAI_LU_APP_KEY');
        $parentId = data_get(collect($data)->first(), 'parent_id');
        $details = $detail = [];
        foreach ($data as $key => $value) {
            $goods = $this->getGoodsByNo(data_get($value, 'goods_no'));
            $detail['orderLineNo'] = $key + 1;
            $detail['itemCode'] = data_get($value, 'goods_no');
            $detail['inventoryType'] = 'ZP';
            $detail['itemName'] = data_get($goods, 'name');
            $detail['planQty'] = floor(data_get($value, 'num') / PACKAGE_NUM);
            $details['orderLine'][] = $detail;
        }
        $parent_no = DB::table('purchases')
            ->where('id', $parentId)
            ->select('no')->first();
        $delivery = [
            'returnOrder' => [
                'returnOrderCode' => data_get($data, '0.no'),
                'warehouseCode' => $this->postData['sid'],
                'orderType' => 'THRK',
                'preDeliveryOrderCode' => data_get($parent_no, 'no'),
                'buyerNick' => data_get($data, '0.sub_company_id'),
                'orderCreateTime' => date('Y-m-d H:i:s'),
            ],
            'orderLines' => $details,
        ];
        $content = $this->arrayToXml($delivery);
        $sign = $this->makeSign($postData, $content);
        $postData['sign'] = $sign;
        $url = BAI_LU_URL . '?' . http_build_query($postData);
        $response = Http::withBody(
            $content,
            "text/xml"
        )->post($url);
        $response = $response->body();
        $arr = $this->xmlToArray($response);
        if ($arr['flag'] == 'failure') {
            return [
                'code' => -1,
                'message' => data_get($arr, 'message', data_get($data, '0.no') . '百路池入库信息推送失败'),
                'data' => $delivery
            ];
        } else {
            return [
                'code' => 200,
                'message' => data_get($arr, 'message'),
                'data' => $delivery
            ];
        }
    }

    /**
     * 调拨出库信息
     *
     * @param array $data
     * @return array
     */
    public function transferOut(array $data): array
    {
        return $this->saleSend($data);
    }

    /**
     * 制作验签
     *
     * @param array $data
     * @param  $content
     * @return string
     */
    private function makeSign(array $data, $content): string
    {
        $str = env('BAI_LU_SECRET');
        ksort($data);
        foreach ($data as $key => $value) {
            if ('sign' == $key) {
                continue;
            }
            if (!empty($key) && !empty($value)) {
                $str .= $key . $value;
            }
        }
        $str .= $content;

        $str .= env('BAI_LU_SECRET');
        // 对接trade.weight等定制接口（接口名字一般是小写）不用去除空白字符
        // 对接通用推送接口、通用回传接口才去除空白
        $str = str_replace(array("\t", "\r", "\n", " "), "", $str);  // 去除空白字符
        return strtoupper(md5($str));
    }

    /**
     * 数组转换XML
     *
     * @param array $data
     * @param bool $eIsArray
     * @param string $root
     * @return mixed
     */
    public function arrayToXml(array $data, $eIsArray = false, $root = '')
    {
        $xml = new ArrayToXml();//实例化类
        return $xml->toXml($data, $eIsArray, $root);//转为数组
    }

    /**
     * xml转成数组
     *
     * @param string $xml
     * @return mixed
     */
    public function xmlToArray(string $xml)
    {
        $string = simplexml_load_string($xml);
        $json = json_encode($string);
        return json_decode($json, true);
    }
}
