<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderGoods extends Model
{
    protected $guarded = [];

    public function distribution()
    {
        return $this->hasOne("App\Models\Distribution", "goods_id", 'goods_id');
    }

    /**
     * 关联用户
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function member()
    {
        return $this->hasOne("App\Models\Member", "id", 'user_id');
    }

    /**
     * 关联商品
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function goods()
    {
        return $this->hasOne("App\Models\Goods", "id", 'goods_id');
    }
}
