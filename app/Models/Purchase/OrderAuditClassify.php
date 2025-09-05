<?php

namespace App\Models\Purchase;

use App\Models\LttBaseModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Class OrderAuditClassify
 * @package App\Models\Purchase
 */
class OrderAuditClassify extends LttBaseModel
{
    protected $table = 'order_audit_classify';
    protected $fillable = [
        'order_name',
        'order_type',
        'classify',
    ];
}
