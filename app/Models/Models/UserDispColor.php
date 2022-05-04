<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDispColor extends Model
{
    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'disp_color_id',
        'disp_color_name',
        'color_code',
        'display_order',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
        'delete_flg'
    ];

    protected $table = 'm_user_disp_color';
    protected $primaryKey = 'disp_color_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = self::FIELDS;
}
