<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationManagement extends Model
{
    use HasFactory;

    protected $table = 't_notification_management';

    public const NOTIFICATION_KINDS = "notification_kinds";
    public const USER = "user";

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'notice_kinds_id',
        'inapp_notification_flg',
        'desktop_notification_flg',
        'mail_notification_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    // public function notification_kinds() {
    //     return $this->belongsTo(NotificationKinds::class, 'notice_kinds_id', 'notice_kinds_id');
    // }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
