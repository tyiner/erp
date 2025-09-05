<?php


namespace App\Models\Purchase;

use App\Models\LttBaseModel;

/**
 * Class PartnerSendLog
 * @package App\Models\Purchase
 */
class PartnerSendLog extends LttBaseModel
{
    protected $table = 'partner_send_log';
    protected $fillable = [
        'user_id',
        'response',
        'purchase_id',
        'location_id',
        'async_result',
    ];
}
