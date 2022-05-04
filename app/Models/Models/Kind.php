<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kind extends Model
{
    use HasFactory;

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';
    public const PROJECT_ATTRIBUTES = "project_attributes";

    public const FIELDS = [
        'kinds_id',
        'kinds_name',
        'color_code',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
        'delete_flg'
    ];
    public $incrementing = false;

    protected $table = "m_kinds";
    protected $primaryKey = 'kinds_id';

    protected $fillable = self::FIELDS;

    public function project_attributes()
    {
        return $this->hasMany(ProjectAttribute::class, 'kinds_id', 'kinds_id');
    }
}
