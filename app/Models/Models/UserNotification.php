<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    use HasFactory;
    use HasCompositePrimaryKey;

    public const NOTIFICATION = "notification";
    public const USER = "user";

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'user_id',
        'notice_id',
        'read_flag',
        'mail_send_status',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    protected $table = 't_user_notification';
    protected $primaryKey = ['user_id','notice_id'];
    public $incrementing = false;

    protected $fillable = self::FIELDS;

    public function notification()
    {
        return $this->belongsTo(Notification::class, 'notice_id', 'notice_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
