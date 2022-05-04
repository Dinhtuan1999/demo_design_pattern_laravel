<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MyProjectDisplayOrder extends Model
{
    use HasFactory;
    use HasCompositePrimaryKey;

    public const USER = "user";
    public const TASK = "task";
    public const PROJECT = "project";

    protected $table = 't_my_project_display_order';

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    protected $primaryKey = ['user_id', 'function_class', 'object_id'];
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'function_class',
        'object_id',
        'display_parent_id',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    // public function task() {
    //     return $this->belongsTo(Task::class, 'object_id', 'task_id');
    // }

    // public function project() {
    //     return $this->belongsTo(Project::class, 'object_id', 'project_id');
    // }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
