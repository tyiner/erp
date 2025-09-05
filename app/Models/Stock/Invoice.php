<?php

namespace App\Models\Stock;

use App\Models\LttBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends LttBaseModel
{
    use SoftDeletes;

    //
    protected $table = 'invoice';
    protected $fillable = [
        'company_id',
        'location_id',
        'type',
        'status',
        'user',
        'no',
        'checked_user',
        'checked',
        'supplier_id',
        'parent_id',
        'post_data'
    ];

    /**
     * 获取公司名称
     *
     * @return BelongsTo
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo('App\Models\Purchase\Company', 'company_id', 'id');
    }

    /**
     * @return HasOne
     */
    public function supplier(): HasOne
    {
        return $this->hasOne("App\Models\Purchase\Supplier", 'id', 'supplier_id');
    }

    /**
     * 备货订单详情
     *
     * @return HasMany
     */
    public function invoiceDetail(): HasMany
    {
        return $this->hasMany("App\Models\Stock\InvoiceDetail", 'invoice_id', 'id')
            ->with('location');
    }

    /**
     * @return HasMany
     */
    public function findSon(): HasMany
    {
        return $this->hasMany("App\Models\Stock\Invoice", 'parent_id', 'id')
            ->with('invoiceDetail');
    }
}
