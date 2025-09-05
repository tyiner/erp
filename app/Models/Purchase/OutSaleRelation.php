<?php

namespace App\Models\Purchase;

use App\Models\LttBaseModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Class OutSaleRelation
 * @package App\Models\Purchase
 */
class OutSaleRelation extends LttBaseModel
{
    protected $table = 'out_sale_relation';
    protected $fillable = [
        'purchase_id',
        'sale_order',
        'serials',
        'num',
        'finished',
        'parent_id',
        'remark',
    ];

    /**
     * 关联销售订单
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function saleShip(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo('App\Models\Purchase\Purchase', 'parent_id', 'id');
    }
}
