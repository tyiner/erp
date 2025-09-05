<?php

namespace App\Models\Purchase;

use App\Models\LttBaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SaleOrder extends LttBaseModel
{
    //
    /**
     * @var string
     */
    protected $table = 'sale_orders';

    /**
     * @var string[]
     */
    protected $fillable = [
        'order_no',
        'platform',
        'customer',
        'phone',
        'address',
        'tracking_code',
        'tax',
        'price',
        'express',
        'goods_no',
        'num',
        'remark',
    ];

    /**
     * @return BelongsToMany
     */
    public function purchaseRelation(): BelongsToMany
    {
        return $this->belongsToMany(
            'App\Models\Purchase\Purchase',
            'purchase_sale_order_relation',
            'purchase_id',
            'sale_order_id'
        );
    }
}
