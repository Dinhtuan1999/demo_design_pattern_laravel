<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectLog extends Model
{
    use HasFactory;

    protected $table = 't_project_log';

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const USER = "user";

    public const FIELDS = [
        'log_id',
        'identifying_code',
        'project_id',
        'task_group_id',
        'task_id',
        'log_message',
        'regist_datetime',
        'update_user_id',
        'create_datetime',
        'create_user_id',
        'update_datetime'
    ];

    protected $fillable = self::FIELDS;


    public const PROJECT = "project";

    protected $casts = [
        'regist_datetime' => 'datetime'
    ];


    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'task_id', 'task_id');
    }

    public function task_groups()
    {
        return $this->hasMany(TaskGroup::class, 'task_group_id', 'task_group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'create_user_id', 'user_id');
    }
}
