<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Goods extends Model
{
    protected $table = 'goods';
    protected $guarded = [];
    protected $fillable = [
        'goods_name',
        'purchase_name',
        'goods_type',
        'store_id',
        'classify',
        'attribute',
        'is_software',
        'send_status',
        'goods_subname',
        'goods_no',
        'brand_id',
        'class_id',
        'mobile_image',
        'goods_master_image',
        'goods_price',
        'goods_market_price',
        'cost_price',
        'goods_weight',
        'goods_stock',
        'stock_guard',
        'goods_content',
        'goods_content_mobile',
        'goods_status',
        'freight_id',
        'goods_images',
        'purchase_limit',
        'sort',
        'is_coupon',
        'purchase_start',
        'purchase_end',
        'purchase_point',
        'decr_stock_mode',
        'pack_length',
        'pack_width',
        'pack_height',
        'banner_height',
        'unit',
    ];

    public function goods_class()
    {
        return $this->hasOne('App\Models\GoodsClass', 'id', 'class_id');
    }

    public function goods_brand()
    {
        return $this->hasOne('App\Models\GoodsBrand', 'id', 'brand_id');
    }

    public function goods_skus()
    {
        return $this->hasMany('App\Models\GoodsSku', 'goods_id', 'id');
    }

    public function goods_sku()
    {
        return $this->hasOne('App\Models\GoodsSku', 'goods_id', 'id');
    }

    // 获取评论数量
    public function order_comment()
    {
        return $this->hasMany('App\Models\OrderComment', 'goods_id', 'id');
    }

    // 订单商品
    public function order_goods()
    {
        return $this->hasMany('App\Models\OrderGoods', 'goods_id', 'id');
    }

    // 获取分销ID
    public function distribution()
    {
        return $this->hasOne('App\Models\Distribution', 'goods_id', 'id');
    }

    // 获取秒杀
    public function seckill()
    {
        return $this->hasOne('App\Models\Seckill', 'goods_id', 'id');
    }

    // 获取拼团
    public function collective()
    {
        return $this->hasOne('App\Models\Collective', 'goods_id', 'id');
    }

    // 店铺商品
    public function store_goods()
    {
        return $this->hasOne('App\Models\StoreGoods', 'goods_id', 'id');
    }

    /**
     * 关联商品规格
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function goods_attr()
    {
        return $this->hasMany('App\Models\GoodsAttr', 'goods_id', 'id');
    }
}
