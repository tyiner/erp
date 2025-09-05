<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $table = 'admins';
    protected $guarded = [];
    protected $fillable = [
        'username',
        'password',
        'nickname',
        'avatar',
        'ip',
        'department_id',
        'company_id',
        'employee_no',
        'status',
        'phone',
        'email'
    ];

    public function roles()
    {
        return $this->belongsToMany('App\Models\Role', 'admin_role', 'admin_id', 'role_id');
    }

    public function company()
    {
        return $this->belongsTo('App\Models\Company', 'company_id', 'id');
    }

    public function department()
    {
        return $this->belongsTo('App\Models\Purchase\Department', 'department_id', 'id');
    }

    public function locations()
    {
        return $this->belongsToMany('App\Models\Stock\Location', 'admin_location', 'admin_id', 'location_id');
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
