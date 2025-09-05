<?php

namespace App\Models\Purchase;

use App\Models\LttBaseModel;

class Department extends LttBaseModel
{
    protected $table = 'departments';
    protected $fillable = [
        'status',
        'name',
        'company_id',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo('App\Models\Company', 'company_id', 'id');
    }
}
