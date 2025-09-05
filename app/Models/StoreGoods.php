<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreGoods extends Model
{
    protected $table = 'store_goods';
    protected $guarded = [];

    public function goods(){
        return $this->hasOne('App\Models\Goods','id','goods_id');
    }

    public function store(){
        return $this->hasOne('App\Models\Store','id','store_id');
    }
}
