<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    /**
     * 关联省份
     * @return HasOne
     */
    public function province(): HasOne
    {
        return $this->hasOne('App\Models\Area', 'id', 'province_id');
    }

    /**
     * 关联城市
     * @return HasOne
     */
    public function city(): HasOne
    {
        return $this->hasOne('App\Models\Area', 'id', 'city_id');
    }

    /**
     * 关联区县
     * @return HasOne
     */
    public function region(): HasOne
    {
        return $this->hasOne('App\Models\Area', 'id', 'region_id');
    }
}
