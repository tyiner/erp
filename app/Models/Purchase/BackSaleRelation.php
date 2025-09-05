<?php

namespace App\Models\Purchase;

use App\Models\LttBaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Class BackSaleRelation
 * @package App\Models\Purchase
 */
class BackSaleRelation extends LttBaseModel
{
    //
    protected $table = 'back_sale_relation';
    protected $fillable = [
        'purchase_id',
        'sale_order_id',
        'num',
        'finished',
        'parent_id',
        'remark',
    ];

    /**
     * 关联销售出库单号
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function saleOut(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo('App\Models\Purchase\Purchase', 'parent_id', 'id');
    }
}
