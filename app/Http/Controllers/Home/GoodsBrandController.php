<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Services\GoodsBrandService;
use Illuminate\Http\Request;

class GoodsBrandController extends Controller
{
    // 获取商品分类
    public function goods_brands(GoodsBrandService $goods_brand_service){
        $rs = $goods_brand_service->getGoodsBrands();
        return $this->success($rs['data']);
    }
}
