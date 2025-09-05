<?php

namespace App\Models\Purchase;

use App\Models\LttBaseModel;
use Illuminate\Database\Eloquent\Model;

class SendGoodsInfo extends LttBaseModel
{
    //
    protected $table = 'send_goods_info_log';
    protected $fillable = [
        'goods_no',
        'location_no',
        'result',
        'message',
        'times',
        'user_id',
    ];
}
