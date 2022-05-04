<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectNotification extends Model
{
    use HasFactory;

    protected $table = 't_project_notification';
    protected $primaryKey = 'project_notice_id';

    public $incrementing = false;

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'project_notice_id',
        'project_id',
        'project_notice_message',
        'message_notice_start_date',
        'message_notice_end_date',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    protected $fillable = self::FIELDS;
}
