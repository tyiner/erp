<?php

namespace App\Models\Purchase;

use App\Models\LttBaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class RedSaleRelation
 * @package App\Models\Purchase
 */
class RedSaleRelation extends LttBaseModel
{
    //
    protected $table = 'red_sale_relation';
    protected $fillable = [
        'purchase_id',
        'sale_order_id',
        'serials',
        'num',
        'parent_id',
        'finished',
        'remark',
    ];

    /**
     * 关联销售退货单号
     * @return BelongsTo
     */
    public function saleBack(): BelongsTo
    {
        return $this->belongsTo('App\Models\Purchase\Purchase', 'parent_id', 'id');
    }
}
