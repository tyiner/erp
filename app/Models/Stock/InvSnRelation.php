<?php

namespace App\Models\Stock;

use App\Models\LttBaseModel;

/**
 * Class InvSnRelation
 * @package App\Models\Stock
 */
class InvSnRelation extends LttBaseModel
{
    protected $table = "inv_sn_relation";

    protected $fillable = [
        "invoice_detail_id",
        "sn_code_id"
    ];
}
