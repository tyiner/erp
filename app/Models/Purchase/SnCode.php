<?php

namespace App\Models\Purchase;

use App\Models\LttBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class SnCode
 * @package App\Models\Purchase
 */
class SnCode extends LttBaseModel
{
    protected $table = 'sn_code';

    protected $fillable = [
        'box',
        'sn',
        'goods_no',
    ];

    /**
     * @return BelongsTo
     */
    public function goods(): BelongsTo
    {
        return $this->belongsTo('App\Models\Goods', 'goods_no', 'goods_no')
            ->select(['goods_name', 'unit', 'goods_master_image', 'goods_images']);
    }
}
