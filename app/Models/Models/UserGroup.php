<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserGroup extends Model
{
    use HasFactory;

    public const COMPANY = "company";
    public const USER = "user";

    const CREATED_AT = 'create_datetime';
    const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'user_group_id',
        'company_id',
        'user_group_name',
        'remarks',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    protected $table = 't_user_group';
    protected $primaryKey = 'user_group_id';
    public $incrementing = false;

    protected $fillable = self::FIELDS;

    public function company() {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function user()
    {
        return $this->hasMany(User::class, 'user_group_id', 'user_group_id');
    }
}
