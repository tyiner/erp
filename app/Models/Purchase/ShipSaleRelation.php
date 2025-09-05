<?php

namespace App\Models\Purchase;

use App\Models\LttBaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class ShipSaleRelation
 * @package App\Models\Purchase
 */
class ShipSaleRelation extends LttBaseModel
{
    protected $table = 'ship_sale_relation';
    protected $fillable = [
        'purchase_id',
        'sale_order_id',
        'finished',
        'num',
        'parent_id',
        'remark',
    ];

    /**
     * 关联销售订单
     * @return BelongsTo
     */
    public function saleOrder(): BelongsTo
    {
        return $this->belongsTo('App\Models\Purchase\Purchase', 'parent_id', 'id');
    }
}
