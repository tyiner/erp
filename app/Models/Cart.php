<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    /**
     * 关联用户
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function member() {
        return $this->hasOne('App\Models\Member', 'id', 'user_id');
    }

    /**
     * 关联店铺商品
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function store_goods()
    {
        return $this->hasOne('App\Models\StoreGoods', 'goods_id', 'goods_id');
    }

    /**
     * 关联商品
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function goods()
    {
        return $this->hasOne('App\Models\Goods', 'id', 'goods_id');
    }

    /**
     * 关联商品规格
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function goods_sku()
    {
        return $this->hasOne('App\Models\GoodsSku', 'id', 'sku_id');
    }

    /**
     * 关联店铺
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function store()
    {
        return $this->hasOne('App\Models\Store', 'id', 'store_id');
    }
}
