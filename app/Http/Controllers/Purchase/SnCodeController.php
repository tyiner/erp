<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Services\GoodsService;
use App\Services\Purchase\UnitService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Predis\Client;
use App\Services\Purchase\SnCodeService;

/**
 * Class SnCodeController
 *
 * @package  App\Http\Controllers\Purchase
 * @property Client $redis
 */
class SnCodeController extends Controller
{
    private $redis;
    private $snCodeService;
    private $goodsService;
    private $unitService;

    public function __construct(
        SnCodeService $snCodeService,
        GoodsService $goodsService,
        UnitService $unitService
    ) {
        $this->redis = Redis::connection('cache');
        $this->snCodeService = $snCodeService;
        $this->goodsService = $goodsService;
        $this->unitService = $unitService;
    }

    /**
     * 保存并缓存商品串码信息
     *
     * @param Request $request
     */
    public function storeSnInfo(Request $request)
    {
        $postData = $request->post();
        count($postData) < 0 && error('传输数据为空！');
        $boxCodes = array_column($postData, 'pkg');
        Log::info('开始批量插入SN码：');
        $errors = "";
        foreach ($postData as $data) {
            if (empty($data['cinvcode']) || empty($data['pkg']) || PACKAGE_NUM != count($data['sns'])) {
                Log::info(
                    'SN码信息不完整：',
                    [
                        'goods_no' => $data['cinvcode'],
                        'box' => $data['pkg'],
                        'numbers' => count($data['sns'])
                    ]
                );
                $errors .= "箱码：" . $data['cinvcode'] . '信息不完整';
                continue;
            }
            $insertData = $this->makeInsertData($data);
            try {
                $ret = $this->snCodeService->addAll($insertData);
                if ($ret) {
                    Log::info('sn码插入成功:', ['box' => $data['pkg']]);
                }
            } catch (\Exception $e) {
                Log::info('sn码插入失败:', ['box' => $data['pkg']]);
                $this->redis->lpush('error_insert_box', $data['pkg']);
            }
        }
        Log::info('SN码保存完成；');
        $snInfo = $this->snCodeService->getBatchByBox($boxCodes);
        $searchData = array_unique(array_column($snInfo->toArray(), 'box'));
        //$this->cacheSn($snInfo);
        $errorSn = '';
        do {
            $errorInfo = $this->redis->lpop('error_insert_box');
            if (!in_array($errorInfo, $searchData)) {
                $errorSn .= $errorInfo . ';';
            }
        } while (!empty($errorInfo));

        $errorSn = rtrim($errorSn, ';');
        $errorSn .= $errors;
        if (empty($errorSn)) {
            success();
        }
        error("SN码添加失败", $errorSn);
    }

    /**
     * @param Collection $data
     */
    private function cacheSn(Collection $data): void
    {
        foreach ($data as $line) {
            $this->redis->hset($line['sn'], 'id', $line['id']);
            $this->redis->hset($line['sn'], 'box', $line['box']);
            $this->redis->hset($line['sn'], 'goods_no', $line['goods_no']);
        }
    }

    /**
     * 处理推送sn码数据
     *
     * @param array $box
     * @return array
     */
    private function makeInsertData(array $box): array
    {
        $datas = [];
        $goodsNo = $box['cinvcode'];
        $boxCode = $box['pkg'];
        $now = Carbon::now()->format("Y-m-d H:i:s");
        $res = array_map(
            function ($line) use ($goodsNo, $boxCode, $now) {
                //$this->redis->sadd($goodsNo, $line);
                return [
                    'box' => $boxCode,
                    'goods_no' => $goodsNo,
                    'sn' => $line,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            },
            $box['sns']
        );
        $datas = array_merge($res, $datas);
        return $datas;
    }

    /**
     * 根据SN码获取商品信息
     *
     * @param Request $request
     */
    public function getGoodsByCode(Request $request)
    {
        $rules = [
            'serial_no' => 'required|string',
        ];
        $this->handleValidateRequest($request, $rules);
        $serial_no = $request->input('serial_no');
        $ret = $this->snCodeService->getGoodsInfo(['box' => $serial_no]);
        if (!empty($ret)) {
            $num = PACKAGE_NUM;
        } else {
            $ret = $this->snCodeService->getGoodsInfo(['sn' => $serial_no]);
            if (!empty($ret)) {
                $num = 1;
            }
        }
        if (empty($ret)) {
            error("sn码或箱码不存在");
        }
        $goods = $this->snCodeService->getGoodsByNo($ret->goods_no);
        if (!empty($goods)) {
            $goods['num'] = $num;
            success($goods);
        }
        error("商品详情不存在");
    }
}
