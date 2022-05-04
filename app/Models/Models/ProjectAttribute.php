<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectAttribute extends Model
{
    use HasFactory;

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';
    public const KIND = "kind";

    public const FIELDS = [
        'project_attribute_id',
        'kinds_id',
        'project_attribute_name',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];
    public $incrementing = false;

    protected $table = "m_project_attribute";
    protected $primaryKey = 'project_attribute_id';

    protected $fillable = self::FIELDS;

    public function kind()
    {
        return $this->belongsTo(Kind::class, 'kinds_id', 'kinds_id');
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 't_project_owned_attribute', 'project_attribute_id', 'project_id', 'project_attribute_id', 'project_id');
    }
}
