<?php

namespace App\Services;

use App\Helpers\TreeHelper;
use App\Models\Goods;
use App\Models\GoodsBrand;
use Illuminate\Support\Facades\Cache;

class GoodsBrandService extends BaseService
{
    public $cache_name = 'goods_brands_cache';

    // 获取所有品牌
    public function getBrands()
    {
        $goods_brand_model = new GoodsBrand();
        $data = $goods_brand_model->select('id', 'name', 'thumb')->orderBy('id')->orderBy('is_sort')->get();
        return $this->format($data);
    }

    public function getGoodsBrands()
    {
        $goods_brand_model = new GoodsBrand();
        $cache_name = $this->cache_name;
        $list = [];
        if (!Cache::has($cache_name)) {
            $goods_brand_list = $goods_brand_model->orderBy('is_sort', 'asc')->get()->toArray();
            $list = TreeHelper::children($goods_brand_list);
            Cache::set($cache_name, $list);
        } else {
            $list = Cache::get($cache_name);
        }
        return $this->format($list);
    }

    // 清空缓存
    public function clearCache()
    {
        $rs = Cache::forget($this->cache_name);
        return $this->format($rs);
    }

    // 根据商品ID 获取分类信息
    public function getGoodsBrandByGoodsId($id)
    {
        $goods_model = new Goods;
        $goods_brand_model = new GoodsBrand;
        $goods_info = $goods_model->find($id);
        $first_class = $goods_brand_model->select('id', 'pid', 'name')->where('id', $goods_info['brand_id'])->first();
        $sec_class = $goods_brand_model->select('id', 'pid', 'name')->where('id', $first_class['pid'])->first();
        $tr_class = $goods_brand_model->select('id', 'pid', 'name')->where('id', $sec_class['pid'])->first();
        $data = [$tr_class, $sec_class, $first_class];
        return $this->format($data);
    }
}
