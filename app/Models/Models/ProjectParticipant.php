<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectParticipant extends Model
{
    use HasFactory;
    use HasCompositePrimaryKey;

    protected $table = 't_project_participant';

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'project_id',
        'user_id',
        'role_id',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
        'delete_flg',
    ];

    protected $fillable = self::FIELDS;

    public const PROJECT = "project";
    public const USER = "user";
    public const ROLE_MST = "role_mst";

    protected $primaryKey = ['project_id', 'user_id'];
    public $incrementing = false;
    protected $keyType    = 'string';

    public function role_mst()
    {
        return $this->belongsTo(RoleMst::class, 'role_id', 'role_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
