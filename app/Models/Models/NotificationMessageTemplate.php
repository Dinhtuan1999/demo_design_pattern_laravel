<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationMessageTemplate extends Model
{
    use HasFactory;

    public const NOTIFICATION_KINDS = "notification_kinds";

    protected $table = 't_notification_message_template';

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    protected $primaryKey = 'notice_message_id';
    public $incrementing = false;

    protected $fillable = [
        'notice_message_id',
        'template_name',
        'notice_message',
        'notice_kinds_id',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    // public function notification_kinds() {
    //     return $this->belongsTo(NotificationKinds::class, 'notice_kinds_id', 'notice_kinds_id');
    // }
}
