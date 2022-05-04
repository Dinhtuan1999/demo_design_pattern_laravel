<?php

namespace App\Models;

use App\Traits\HasScopeExtend;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskGroup extends Model
{
    use HasFactory;
    use HasScopeExtend;

    protected $table = 't_task_group';
    protected $primaryKey = 'task_group_id';
    public $incrementing = false;
    protected $keyType    = 'string';

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'task_group_id',
        'project_id',
        'group_name',
        'disp_color_id',
        'display_parent_id',
        'display_order',
        'delete_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    protected $fillable = self::FIELDS;


    public const DISP_COLOR = "disp_color";
    public const PROJECT = "project";
    public const TRASHS = "trashs";
    public const TASKS = "tasks";
    public const TASKS_FULLL = "task_full";
    public const TASKS_NOT_COMPLETE_FULLL = "tasks_not_complete_full";

    public function disp_color()
    {
        return $this->belongsTo(TaskGroupDispColor::class, 'disp_color_id', 'disp_color_id')->avaiable();
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    public function project_log()
    {
        return $this->belongsTo(ProjectLog::class, 'task_group_id', 'task_group_id');
    }
    /**
     * TODO
     */
//    public function trashs()
//    {
//        return $this->hasMany(Trash::class, 'task_group_id', 'task_group_id');
//    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'task_group_id', 'task_group_id')->avaiable();
    }

    public function tasks_not_complete()
    {
        return $this->hasMany(Task::class, 'task_group_id', 'task_group_id')
                ->notComplete()
                ->avaiable();
    }

    public function task_full()
    {
        return $this->hasMany(Task::class, 'task_group_id', 'task_group_id')
            ->where('t_task.delete_flg', config('apps.general.not_deleted'))
            ->where(function ($query) {
                $query->orWhere('t_task.parent_task_id', null)
                    ->orWhere('t_task.parent_task_id', '');
            })
            ->with(Task::TASK_RELATION);
    }

    public function tasks_not_complete_full()
    {
        return (clone $this->task_full())
            ->where('t_task.task_status_id', '<>', config('apps.task.complete'));
    }


    public function tasks_parent()
    {
        return $this->tasks()->avaiable()->whereNull('t_task.parent_task_id');
    }
    public function tasks_parent_not_complete()
    {
        return $this->tasks_parent()->notComplete();
    }
}
