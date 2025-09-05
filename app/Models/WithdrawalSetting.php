<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawalSetting extends Model
{

    /**
     * 关联店铺信息
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function store()
    {
        return $this->hasOne('App\Models\Store', 'id', 'store_id');
    }
}
