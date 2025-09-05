<?php

namespace App\Models\Purchase;

use App\Models\LttBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class SnCode
 * @package App\Models\Purchase
 */
class Purchase extends LttBaseModel
{
    const PLAN = PURCHASE_PLAN;
    const SHIP = PURCHASE_ARRIVAL;
    const STORE = PURCHASE_STORE_IN;

    protected $table = 'purchases';
    //使用软删除
    use SoftDeletes;

    /**
     * 可修改字段
     * @var string[]
     */
    protected $fillable = [
        'no',
        'type',
        'user',
        'user_id',
        'supplier_id',
        'customer',
        'company_id',
        'sub_company_id',
        'location_id',
        'receiving_location_id',
        'consignee_info',
        'status',
        'order_time',
        'tax',
        'business_type',
        'purchase_type',
        'sale_type',
        'department_id',
        'checked_user',
        'checked',
        'source_id',
        'parent_id',
        'post_data',
        'remark',
    ];

    /**
     * @return HasMany
     */
    public function detail(): HasMany
    {
        return $this->hasMany('App\Models\Purchase\PurchaseDetail', 'purchase_id', 'id');
    }

    /**
     * 关联供应商
     * @return BelongsTo
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo('App\Models\Purchase\Supplier', 'supplier_id', 'id');
    }

    /**
     * 关联公司
     * @return BelongsTo
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo('App\Models\Purchase\Company', 'company_id', 'id');
    }

    /**
     * 关联仓库
     * @return BelongsTo
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo('App\Models\Stock\Location', 'location_id', 'id');
    }

    /**
     * 关联销售订单详情
     * @return BelongsToMany
     */
    public function saleOrders(): BelongsToMany
    {
        return $this->belongsToMany(
            'App\Models\Purchase\SaleOrder',
            'purchase_sale_order_relation',
            'purchase_id',
            'sale_order_id'
        );
    }

    /**
     * 关联销售发货单详情
     * @return BelongsToMany
     */
    public function saleShips(): BelongsToMany
    {
        return $this->belongsToMany(
            'App\Models\Purchase\SaleOrder',
            'ship_sale_relation',
            'purchase_id',
            'sale_order_id'
        )->whereNull('ship_sale_relation.deleted_at');
    }

    /**
     * 关联销售发货单备注
     * @return HasMany
     */
    public function saleShipRemarks(): HasMany
    {
        return $this->hasMany('App\Models\Purchase\ShipSaleRelation', 'purchase_id', 'id')
            ->with('saleOrder');
    }

    /**
     * 关联销售出库单详情
     * @return BelongsToMany
     */
    public function saleOuts(): BelongsToMany
    {
        return $this->belongsToMany(
            'App\Models\Purchase\SaleOrder',
            'out_sale_relation',
            'purchase_id',
            'sale_order_id'
        )->whereNull('out_sale_relation.deleted_at');
    }

    /**
     * 关联销售出库单备注
     * @return HasMany
     */
    public function saleOutRemarks(): HasMany
    {
        return $this->hasMany('App\Models\Purchase\OutSaleRelation', 'purchase_id', 'id')
            ->with('saleShip');
    }

    /**
     * 关联销售退货单详情
     * @return BelongsToMany
     */
    public function saleBacks(): BelongsToMany
    {
        return $this->belongsToMany(
            'App\Models\Purchase\SaleOrder',
            'back_sale_relation',
            'purchase_id',
            'sale_order_id'
        )->whereNull('back_sale_relation.deleted_at');
    }

    /**
     * 关联销售退货单备注
     * @return HasMany
     */
    public function saleBackRemarks(): HasMany
    {
        return $this->hasMany('App\Models\Purchase\BackSaleRelation', 'purchase_id', 'id')
            ->with("saleOut");
    }

    /**
     * 关联销售出库红字单详情
     * @return BelongsToMany
     */
    public function saleReds(): BelongsToMany
    {
        return $this->belongsToMany(
            'App\Models\Purchase\SaleOrder',
            'red_sale_relation',
            'purchase_id',
            'sale_order_id'
        )->whereNull('red_sale_relation.deleted_at');
    }

    /**
     * 关联销售出库红字单备注
     * @return HasMany
     */
    public function saleRedRemarks(): HasMany
    {
        return $this->hasMany('App\Models\Purchase\RedSaleRelation', 'purchase_id', 'id')
            ->with("saleBack");
    }
}
