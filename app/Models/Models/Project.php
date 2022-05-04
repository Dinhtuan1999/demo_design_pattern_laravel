<?php

namespace App\Models;

use App\Scopes\DeleteFlgNotDeleteScope;
use App\Traits\HasScopeExtend;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;
    use HasScopeExtend;

    protected $table = 't_project';
    protected $primaryKey = 'project_id';
    public $incrementing = false;
    protected $keyType    = 'string';

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'project_id',
        'company_id',
        'project_name',
        'project_name_public',
        'project_overview',
        'project_overview_public',
        'template_flg',
        'scheduled_start_date',
        'scheduled_end_date',
        'actual_start_date',
        'actual_end_date',
        'develop_scale',
        'user_num',
        'company_search_target_flg',
        'company_search_keyword',
        'project_status',
        'template_open_flg',
        'object_copy_num',
        'delete_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    protected $fillable = self::FIELDS;

    public const COMPANY = "company";

    public const MY_PROJECT_DISPLAY_ORDERS = "my_project_display_orders";
    public const TRASHS = "trashs";
    public const TASKS = "tasks";
    public const PROJECT_OWNED_ATTRIBUTES = "project_owned_attributes";
    public const PROJECT_PARTICIPANTS = "project_participants";
    public const PROJECT_LOGS = "project_logs";
    public const TASK_GROUPS = "task_groups";
    public const TASKS_COMPLETED = "tasks_completed";
    public const PROJECT_ATTRIBUTES = "project_attributes";
    public const USERS = "users";
    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope(new DeleteFlgNotDeleteScope());
    }


    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }


    /**
     * TODO
     */
//    public function my_project_display_orders()
//    {
//        return $this->hasMany(MyProjectDisplayOrder::class,'object_id', 'project_id');
//    }

    /**
     * TODO
     */
//    public function trashs()
//    {
//        return $this->hasMany(Trash::class, 'project_id', 'project_id');
//    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'project_id', 'project_id');
    }

    public function project_owned_attributes()
    {
        return $this->hasMany(ProjectOwnedAttribute::class, 'project_id', 'project_id');
    }

    public function project_participants()
    {
        return $this->hasMany(ProjectParticipant::class, 'project_id', 'project_id');
    }

    public function project_logs()
    {
        return $this->hasMany(ProjectLog::class, 'project_id', 'project_id');
    }

    public function task_groups()
    {
        return $this->hasMany(TaskGroup::class, 'project_id', 'project_id');
    }

    public function tasks_completed()
    {
        return $this->hasMany(Task::class, 'project_id', 'project_id')
            ->where('task_status_id', config('apps.task.status_key.complete'));
    }

    public function project_attributes()
    {
        return $this->belongsToMany(ProjectAttribute::class, 't_project_owned_attribute', 'project_id', 'project_attribute_id', 'project_id', 'project_attribute_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 't_project_participant', 'project_id', 'user_id', 'project_id', 'user_id')
            ->join('t_role_mst', 't_role_mst.role_id', '=', 't_project_participant.role_id');
    }

    /**
     * scope project not_started
     *
     * @param [type] $query
     * @return void
     */
    public function scopeNotStart($query)
    {
        return $query->where('project_status', config('apps.project.project_status.not_started'));
    }

    /**
     * scope project in_progress
     *
     * @param [type] $query
     * @return void
     */
    public function scopeInProgress($query)
    {
        return $query->where('project_status', config('apps.project.project_status.in_progress'));
    }

    /**
     * scope project delay_start
     *
     * @param [type] $query
     * @return void
     */
    public function scopeDelayStart($query)
    {
        return $query->where('project_status', config('apps.project.project_status.delay_start'));
    }

    /**
     * scope project delay_complete
     *
     * @param [type] $query
     * @return void
     */
    public function scopeDelayComplete($query)
    {
        return $query->where('project_status', config('apps.project.project_status.delay_complete'));
    }

    /**
     * scope project complete
     *
     * @param [type] $query
     * @return void
     */
    public function scopeComplete($query)
    {
        return $query->where('project_status', config('apps.project.project_status.complete'));
    }

    /**
     * scope project complete
     *
     * @param [type] $query
     * @return void
     */
    public function scopeNotComplete($query)
    {
        return $query->where('project_status', '!=', config('apps.project.project_status.complete'));
    }
}
