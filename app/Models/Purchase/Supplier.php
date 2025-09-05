<?php

namespace App\Models\Purchase;

use App\Models\LttBaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class SnCode
 * @package App\Models\Purchase
 */
class Supplier extends LttBaseModel
{
    protected $table = 'suppliers';
    //使用软删除
    use SoftDeletes;

    /**
     * 可修改字段
     * @var string[]
     */
    protected $fillable = [
        'name',
        'no',
        'link_name',
        'address',
        'phone',
        'status',
        'duty',
        'account_holder',
        'bank',
        'bank_account',
        'remark',
    ];
}
