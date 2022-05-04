<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskStatus extends Model
{
    const CREATED_AT = 'create_datetime';
    const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'task_status_id',
        'task_status_name',
        'display_order',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];
    public $incrementing = false;

    protected $table = "m_task_status";
    protected $primaryKey = 'task_status_id';

    protected $fillable = self::FIELDS;
}
