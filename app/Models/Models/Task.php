<?php

namespace App\Models;

use App\Scopes\DeleteFlgNotDeleteScope;
use App\Traits\HasScopeExtend;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Task extends Model
{
    use HasFactory;
    use HasScopeExtend;

    protected $table = 't_task';

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'task_id',
        'task_group_id',
        'project_id',
        'task_name',
        'priority_id',
        'user_id',
        'disclosure_range_id',
        'task_status_id',
        'task_memo',
        'start_plan_date',
        'end_plan_date',
        'start_date',
        'end_date',
        'parent_task_id',
        'display_parent_id',
        'parent_task_display_order',
        'sub_task_display_order',
        'parent_task_display_id',
        'delete_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
        'task_copy_id',
    ];

    protected $fillable = self::FIELDS;
    protected $primaryKey = 'task_id';
    protected $keyType = 'string';
    public $incrementing = false;

    public const USER = "user";
    public const MANAGER = "manager";
    public const DISCLOSURE_RANGE_MST = "disclosure_range_mst";
    public const PRIORITY_MST = "priority_mst";
    public const TASK_STATUS = "task_status";
    public const PROJECT = "project";
    public const TASK_GROUP = "task_group";
    public const MY_PROJECT_DISPLAY_ORDERS = "my_project_display_orders";
    public const TRASH = "trash";
    public const TRASHES = "trashes";
    public const GOODS = "goods";
    public const BREAKDOWNS = "breakdowns";
    public const REMINDS = "reminds";
    public const WATCH_LISTS = "watch_lists";
    public const COMMENTS = "comments";
    public const ATTACHMENT_FILES = "attachment_files";
    public const CHECK_LISTS = "check_lists";
    public const CHECK_LISTS_COMPLETE = "check_lists_complete";
    public const TASK_PARENT = "task_parent";
    public const SUB_TASKS = "sub_tasks";
    public const SUB_TASKS_COMPLETE = "sub_tasks_complete";
    public const TASK_RELATION = [self::PROJECT, self::USER, self::CHECK_LISTS, self::SUB_TASKS,
        self::SUB_TASKS_COMPLETE, self::CHECK_LISTS_COMPLETE, self::PRIORITY_MST, self::TASK_GROUP, self::TASK_STATUS,
        self::DISCLOSURE_RANGE_MST, self::BREAKDOWNS, self::ATTACHMENT_FILES, self::REMINDS, self::GOODS, self::WATCH_LISTS];
    public const TASK_NAME = "task_name";
    public const WATCH_LISTS_BY_CURRENT_USER = "watch_lists_by_current_user";


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id')->avaiable()
            ->select(['user_id', 'disp_name', 'icon_image_path']);
    }

    public function create_user()
    {
        return $this->belongsTo(User::class, 'create_user_id', 'user_id')->avaiable()
            ->select(['user_id', 'disp_name', 'icon_image_path']);
    }

    /**
     * TODO
     */
    public function disclosure_range_mst()
    {
        return $this->belongsTo(DisclosureRange::class, 'disclosure_range_id', 'disclosure_range_id');
    }

    public function priority_mst()
    {
        return $this->belongsTo(PriorityMst::class, 'priority_id', 'priority_id');
    }

    public function task_status()
    {
        return $this->belongsTo(TaskStatus::class, 'task_status_id', 'task_status_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id')
            ->where('delete_flg', config('apps.general.not_deleted'));
    }

    public function task_group()
    {
        return $this->belongsTo(TaskGroup::class, 'task_group_id', 'task_group_id')
            ->where('delete_flg', config('apps.general.not_deleted'));
    }

    public function task_parent()
    {
        return $this->belongsTo(__CLASS__, 'parent_task_id', 'task_id')
            ->where('delete_flg', config('apps.general.not_deleted'));
    }

    public function project_log()
    {
        return $this->belongsTo(ProjectLog::class, 'task_id', 'task_id');
    }

    /**
     * TODO
     */
    //    public function my_project_display_orders()
    //    {
    //        return $this->hasMany(MyProjectDisplayOrder::class, 'object_id', 'task_id');
    //    }

    /**
     * TODO
     */
    public function trash()
    {
        return $this->hasMany(Trash::class, 'task_id', 'task_id');
    }

    /**
     * TODO
     */
    public function goods()
    {
        return $this->hasMany(Good::class, 'task_id', 'task_id')
            ->where('t_good.delete_flg', config('apps.general.not_deleted'))->with(Good::USER);
    }

    public function breakdowns()
    {
        return $this->hasMany(Breakdown::class, 'task_id', 'task_id')
            ->leftjoin('t_user', 't_breakdown.reportee_user_id', '=', 't_user.user_id')
            ->where('t_breakdown.delete_flg', config('apps.general.not_deleted'))
            ->orderBy('t_breakdown.create_datetime', 'DESC')
            ->select(['t_breakdown.*', 't_user.disp_name as reportee_user_name']);
    }

    public function reminds()
    {
        return $this->hasMany(Remind::class, 'task_id', 'task_id')
            ->where('t_remind.delete_flg', config('apps.general.not_deleted'));
    }

    public function watch_lists()
    {
        return $this->hasMany(WatchList::class, 'task_id', 'task_id')
            ->where('t_watch_list.delete_flg', config('apps.general.not_deleted'));
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'task_id', 'task_id')
            ->where('delete_flg', config('apps.general.not_deleted'));
    }

    /**
     * TODO
     */
    public function attachment_files()
    {
        return $this->hasMany(AttachmentFile::class, 'task_id', 'task_id')
            ->where('delete_flg', config('apps.general.not_deleted'));
    }

    public function check_lists()
    {
        return $this->hasMany(CheckList::class, 'task_id', 'task_id')->avaiable();
    }

    public function check_lists_complete()
    {
        return $this->check_lists()->complete();
    }

    public function check_lists_not_complete()
    {
        return $this->check_lists()->notComplete();
    }

    public function sub_tasks()
    {
        return $this->hasMany(Task::class, 'parent_task_id', 'task_id')->avaiable();
    }

    public function sub_tasks_complete()
    {
        return $this->sub_tasks()->complete();
    }

    public function sub_tasks_not_complete()
    {
        return $this->sub_tasks()->notComplete();
    }

    public function likes()
    {
        return $this->hasMany(Good::class, 'task_id', 'task_id')->avaiable();
    }

    public function user_likes()
    {
        return $this->belongsToMany(User::class, 't_good', 'task_id', 'user_id', 'task_id', 'user_id');
    }

    public function scopeNotComplete($query)
    {
        return $query->where('t_task.task_status_id', '<>', config('apps.task.status_key.complete'));
    }

    public function scopeComplete($query)
    {
        return $query->where('task_status_id', config('apps.task.status_key.complete'));
    }

    public function user_watch_lists()
    {
        return $this->belongsToMany(User::class, 't_watch_list', 'task_id', 'user_id', 'task_id', 'user_id');
    }

    public function isSubTask()
    {
        return empty($this->parent_task_id) ? false : true;
    }

    public function isParentTask()
    {
        return empty($this->parent_task_id) ? true : false;
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'create_user_id', 'user_id');
    }

    public function watch_lists_by_current_user()
    {
        return $this->hasMany(WatchList::class, 'task_id', 'task_id')
            ->where([
                't_watch_list.delete_flg' => config('apps.general.not_deleted'),
                'user_id' => Auth::id()
            ]);
    }
}
