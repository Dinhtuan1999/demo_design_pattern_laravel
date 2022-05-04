<?php

namespace App\Models;

use App\Traits\HasScopeExtend;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;
    use HasScopeExtend;

    public const NOTIFICATION_KINDS = "notification_kinds";
    public const USER_NOTIFICATION = "user_notification";

    protected $table = 't_notification';

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    protected $primaryKey = 'notice_id';
    public $incrementing = false;

    protected $fillable = [
        'notice_id',
        'notice_kinds_id',
        'link_id',
        'notice_message',
        'notice_start_datetime',
        'notice_end_datetime',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
        'notice_message_footer'
    ];

    public function notification_kinds()
    {
        return $this->belongsTo(NoticeKind::class, 'notice_kinds_id', 'notice_kinds_id');
    }

    public function user_notification()
    {
        return $this->belongsTo(UserNotification::class, 'notice_id', 'notice_id');
    }
    public function user_notifications()
    {
        return $this->hasMany(UserNotification::class, 'notice_id', 'notice_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 't_user_notification', 'notice_id', 'user_id', 'notice_id', 'user_id');
    }

    public function scopeWithWhereHas($query, $relation, $constraint)
    {
        return $query->whereHas($relation, $constraint)
            ->with([$relation => $constraint]);
    }
}
