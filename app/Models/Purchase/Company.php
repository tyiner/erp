<?php

namespace App\Models\Purchase;

use App\Models\LttBaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Company
 * @package App\Models\Purchase
 */
class Company extends LttBaseModel
{
    protected $table = 'companies';

    /**
     * 可修改字段
     * @var string[]
     */
    protected $fillable = [
        'name',
        'company_no',
        'status',
        'user',
        'address',
        'address_detail',
        'phone'
    ];

    /**
     * 关联仓库
     * @return HasMany
     */
    public function location(): HasMany
    {
        return $this->hasMany("App\Models\Stock\Location", "company_id", "id");
    }
}
