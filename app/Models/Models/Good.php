<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use App\Traits\HasScopeExtend;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Good extends Model
{
    use HasFactory;
    use HasCompositePrimaryKey;
    use HasScopeExtend;

    public const USER = "user";
    public const TASK = "task";

    protected $table = 't_good';

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    protected $primaryKey = ['user_id', 'task_id'];
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'task_id',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
        'delete_flg'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    // public function task() {
    //     return $this->belongsTo(Task::class, 'task_id', 'task_id');
    // }
}
