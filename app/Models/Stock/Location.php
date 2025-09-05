<?php

namespace App\Models\Stock;

use App\Models\LttBaseModel;
use Illuminate\Database\Eloquent\Model;

class Location extends LttBaseModel
{
    protected $table = 'locations';

    const YE_HAI = 100015;
    const BAI_LU = 100012;

    protected $fillable = [
        'name',
        'location_no',
        'company_id',
        'link_user',
        'link_phone',
        'status',
        'address',
        'address_detail',
        'remark',
        'type',
    ];
}
