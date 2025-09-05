<?php

namespace App\Models\Purchase;

use App\Models\LttBaseModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PurSnRelation
 * @package App\Models\Purchase
 */
class PurSnRelation extends LttBaseModel
{
    protected $table = 'pur_sn_relation';
    protected $fillable = [
        'purchase_detail_id',
        'sn_id',
    ];
}
