<?php

namespace App\Models\Stock;

use App\Models\LttBaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceDetail extends LttBaseModel
{
    use SoftDeletes;

    protected $table = 'invoice_detail';
    protected $fillable = [
        'invoice_id',
        'num',
        'total_num',
        'unit_id',
        'price',
        'type',
        'tax',
        'goods_no'
    ];

    /**
     * 获取关联仓库
     *
     * @return HasOne
     */
    public function location(): HasOne
    {
        return $this->hasOne("App\Models\Stock\Location", 'id', 'location_id');
    }
}
