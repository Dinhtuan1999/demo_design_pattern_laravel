<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispColor extends Model
{
    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'disp_color_id',
        'disp_color_name',
        'color_code',
        'classification',
        'display_order',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
        'delete_flg'
    ];

    protected $table = 'm_disp_color';
    protected $primaryKey = 'disp_color_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = self::FIELDS;

    public function user()
    {
        return $this->hasMany(User::class, 'display_color_id', 'disp_color_id');
    }

    public function task_group()
    {
        return $this->hasMany(TaskGroup::class, 'disp_color_id', 'disp_color_id');
    }
}
