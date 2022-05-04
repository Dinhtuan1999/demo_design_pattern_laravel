<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactRejectionReason extends Model
{
    public const CREATED_AT = 'create_datetime';

    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'contact_rejection_reason_id',
        'contact_rejection_reason',
        'display_order',
        'delete_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
    ];

    protected $fillable = self::FIELDS;

    protected $primaryKey = 'contact_rejection_reason_id';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = "m_contact_rejection_reason";
}
