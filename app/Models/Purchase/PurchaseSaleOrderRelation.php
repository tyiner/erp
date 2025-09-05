<?php

namespace App\Models\Purchase;

use App\Models\LttBaseModel;
use Illuminate\Database\Eloquent\Model;

class PurchaseSaleOrderRelation extends LttBaseModel
{
    //
    protected $table = 'purchase_sale_order_relation';
    protected $fillable = [
        'purchase_id',
        'sale_order_id',
        'finished',
    ];
}
