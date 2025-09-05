<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Services\AdvService;
use App\Services\GoodsClassService;
use App\Services\SeckillService;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    // 获取首页信息
    public function index(GoodsClassService $goods_class_service, AdvService $adv_service, SeckillService $seckill_service)
    {
        $data['goods'] = $goods_class_service->is_master()['data']; // 获取商品首页信息
        $data['banner_bottom_adv'] = $adv_service->getAdvList('PC_幻灯片下广告')['data'];
        $data['banner'] = $adv_service->getAdvList('PC_首页幻灯片')['data'];
        $data['seckill_list'] = $seckill_service->getIndexSeckillAndGoods(4)['data'];
        return $this->success($data);
    }

    // 测试
    public function test()
    {
//        $tool_service = new \App\Services\ToolService();
//        dd($tool_service->create_qrcode('123'));

//        dd(Order::makeOrderNo(auth('user')->user()['id']));

    }

}
