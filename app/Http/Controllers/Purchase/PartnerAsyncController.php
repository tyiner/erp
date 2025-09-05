<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Services\Purchase\PartnerService;
use Illuminate\Http\Request;

/**
 * Class PartnerAsyncController
 * @package App\Http\Controllers\Purchase
 */
class PartnerAsyncController extends Controller
{
    private $service;

    public function __construct(PartnerService $service)
    {
        $this->service = $service;
    }

    /**
     * 百路驰回调数据接口
     * @param Request $request
     * @return mixed
     */
    public function baiLuChi(Request $request)
    {
        $method = $request->input('method');
        $xml = file_get_contents("php://input");
        if ('WDT_WMS_ENTRYORDER_CONFIRM' == $method) {
            $this->service->wmsEntryOrderConfirm($xml);
        }
        if ('WDT_WMS_STOCKOUT_CONFIRM' == $method) {
            $this->service->wmsStockOutConfirm($xml);
        }
        if ('WDT_WMS_RETURNORDER_CONFIRM' == $method) {
            $this->service->wmsReturnOrderConfirm($xml);
        }
    }

    /**
     * 采购入库信息回传
     * @param Request $request
     */
    public function store(Request $request)
    {
        $data = $request->all();
        $ret = $this->service->asyncStore($data);
        header('content-type:json/text');
        if ($ret) {
            $back = [
                'success' => true,
                'code' => SUCCESS_CODE,
                'msg' => '数据推送成功',
            ];
        } else {
            $back = [
                'success' => false,
                'code' => COMMON_ERROR_CODE,
                'msg' => '数据推送失败',
            ];
        }
        exit(json_encode($back));
    }

    /**
     * 销售出库信息回传
     * @param Request $request
     */
    public function sale(Request $request)
    {
        $data = $request->all();
        $ret = $this->service->asyncSale($data);
        header('content-type:json/text');
        if ($ret) {
            $back = [
                'success' => true,
                'code' => SUCCESS_CODE,
                'msg' => '数据推送成功',
            ];
        } else {
            $back = [
                'success' => false,
                'code' => COMMON_ERROR_CODE,
                'msg' => '数据推送失败',
            ];
        }
        exit(json_encode($back));
    }

    /**
     * 其他出库信息回传
     * @param Request $request
     */
    public function other(Request $request)
    {
        $data = $request->all();
        $ret = $this->service->asyncOther($data);
        header('content-type:json/text');
        if ($ret) {
            $back = [
                'success' => true,
                'code' => SUCCESS_CODE,
                'msg' => '数据推送成功',
            ];
        } else {
            $back = [
                'success' => false,
                'code' => COMMON_ERROR_CODE,
                'msg' => '数据推送失败',
            ];
        }
        exit(json_encode($back));
    }
}
