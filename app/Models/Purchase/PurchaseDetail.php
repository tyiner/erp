<?php

namespace App\Models\Purchase;

use App\Models\LttBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Class PurchaseDetail
 * @package App\Models\Purchase
 */
class PurchaseDetail extends LttBaseModel
{
    protected $table = 'purchase_detail';
    //
    protected $fillable = [
        "price",
        "purchase_id",
        "source_id",
        "type",
        "tax",
        "location_id",
        "unit",
        "num",
        "finished",
        "total_num",
        "remark",
        "goods_no",
        "attribute",
        "location_id",
        "plan_delivery_date"
    ];

    /**
     * 关联商品详情
     * @return HasOne
     */
    public function goods(): HasOne
    {
        return $this->hasOne('App\Models\Goods', 'goods_no', 'goods_no');
    }

    /**
     * 关联仓库
     * @return HasOne
     */
    public function location(): HasOne
    {
        return $this->hasOne('App\Models\Stock\Location', 'id', 'location_id');
    }

    /**
     * 查询获取库存
     * @return BelongsTo
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo('App\Models\Purchase\Purchase', 'purchase_id', 'id')
            ->with('company', 'supplier');
    }
}
