<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Services\Purchase\PartnerService;
use Illuminate\Http\Request;

/**
 * Class PartnerController
 * @package App\Http\Controllers\Purchase
 */
class PartnerController extends Controller
{
    private $service;

    public function __construct(PartnerService $service)
    {
        $this->service = $service;
    }

    /**
     * 第三方仓库发送采购到货信息
     *
     * @param Request $request
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function storeSend(Request $request)
    {
        $rules = [
            'id' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $ret = $this->service->storeSend($request->only('id'));
        if ($ret) {
            success(['no' => data_get($ret, '0.no')]);
        }
        error("数据推送失败");
    }

    /**
     * 第三方仓库发生采购退货信息
     * @param Request $request
     */
    public function storeOutSend(Request $request)
    {
        $rules = [
            'id' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $ret = $this->service->storeOutSend($request->only('id'));
        if ($ret) {
            success(['no' => data_get($ret, '0.no')]);
        }
        error("数据推送失败");
    }

    /**
     * 第三方仓库调拨信息发送
     * @param Request $request
     */
    public function transferSend(Request $request)
    {
        $rules = [
            'id' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $ret = $this->service->transfer($request->only('id'));
        if ($ret) {
            success(['no' => data_get($ret, '0.no')]);
        }
        error("数据推送失败");
    }

    /**
     * 备货发货信息发送
     * @param Request $request
     */
    public function saleSend(Request $request)
    {
        $rules = [
            'id' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $ret = $this->service->saleSend($request->only('id'));
        if ($ret) {
            success(['no' => data_get($ret, '0.no')]);
        }
        error("数据推送失败");
    }

    /**
     * 备货退货信息发送
     * @param Request $request
     */
    public function saleBackSend(Request $request)
    {
        $rules = [
            'id' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $this->service->saleBackSend($request->only('id'));
    }

    /**
     * 商品信息推送
     * @param Request $request
     */
    public function informGoods(Request $request)
    {
        $data = $request->only(['name', 'goods_no', 'unit']);
        $this->service->informGoods($data);
    }

    /**
     * 获取推送失败商品信息跟仓库编号
     * @param Request $request
     */
    public function unSuccess(Request $request)
    {
        $rules = [
            'location_no' => 'string',
            'goods_no' => 'string',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        $ret = $this->service->unSuccess($data);
        success($ret);
    }

    /**
     * 重新推送商品信息
     * @param Request $request
     */
    public function retry(Request $request)
    {
        $rules = [
            'id' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->only('id');
        $ret = $this->service->retry($data);
        success(['id' => data_get($ret, 'id')]);
    }
}
