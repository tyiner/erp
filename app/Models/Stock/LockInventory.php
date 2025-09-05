<?php

namespace App\Models\Stock;

use App\Models\LttBaseModel;
use Illuminate\Database\Eloquent\Model;

/**
 * 锁定仓
 * Class LockInventory
 * @package App\Models\Stock
 */
class LockInventory extends LttBaseModel
{
    protected $table = "lock_inventory";
    protected $fillable = [
        "location_id",
        "location_no",
        "goods_no",
        "lock_num",
    ];
}
