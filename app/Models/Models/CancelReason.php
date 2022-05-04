<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CancelReason extends Model
{
    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'cancel_reason_id',
        'cancel_reason',
        'display_order',
        'delete_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    protected $table = 'm_cancel_reason';
    protected $primaryKey = 'cancel_reason_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = self::FIELDS;
}
