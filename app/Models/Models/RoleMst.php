<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleMst extends Model
{
    use HasFactory;

    protected $table = 't_role_mst';

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'role_id',
        'company_id',
        'role_name',
        'delete_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    protected $fillable = self::FIELDS;

    public const PROJECT_PARTICIPANTS = "project_participants";
    public const COMPANY = "company";

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function project_participants()
    {
        return $this->hasMany(ProjectParticipant::class, 'role_id', 'role_id');
    }
}
