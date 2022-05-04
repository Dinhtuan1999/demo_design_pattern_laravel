<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectOwnedAttribute extends Model
{
    use HasFactory;
    use HasCompositePrimaryKey;
    protected $table = 't_project_owned_attribute';

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'project_id',
        'project_attribute_id',
        'others_message',
        'delete_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
    ];

    protected $fillable = self::FIELDS;

    protected $primaryKey = ['project_id','project_attribute_id'];
    public $incrementing = false;
    protected $keyType    = 'string';

    public const PROJECT = "project";
    public const PROJECT_ATTRIBUTE = "project_attribute";

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    /**
     * TODO
     */
    public function project_attribute()
    {
        return $this->belongsTo(ProjectAttribute::class, 'project_attribute_id', 'project_attribute_id');
    }
}
