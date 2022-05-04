<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WatchList extends Model
{
    use HasFactory;
    use HasCompositePrimaryKey;

    public const TASK = "task";
    public const USER = "user";

    public const WATCHLIST = "watchlist";

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'user_id',
        'task_id',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
        'delete_flg'
    ];

    protected $table = 't_watch_list';
    protected $primaryKey = ['user_id','task_id'];
    public $incrementing = false;

    protected $fillable = self::FIELDS;

    public function watchlist()
    {
        return $this->belongsToMany(
            Task::class,
            't_watch_list',
            'user_id',
            'task_id',
            'user_id',
            'task_id'
        )->where('t_watch_list.delete_flg', config('apps.general.not_deleted'))
            ->orderBy('t_task.task_name', 'desc')
            ->with(Task::TASK_RELATION);
    }

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'task_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
