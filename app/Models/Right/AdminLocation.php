<?php

namespace App\Models\Right;

use App\Models\LttBaseModel;
use Illuminate\Database\Eloquent\Model;

class AdminLocation extends LttBaseModel
{
    //
    protected $table = 'admin_location';
    protected $fillable = ['location_id', 'admin_id'];
}
