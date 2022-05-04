<?php

namespace App\Models;

use App\Traits\HasScopeExtend;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckList extends Model
{
    use HasFactory;
    use HasScopeExtend;

    public const TASK = "task";

    public const CREATED_AT = 'create_datetime';

    public const UPDATED_AT = 'update_datetime';

    public const FIELDS  =
    [
        'check_list_id',
        'task_id',
        'check_name',
        'complete_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
        'delete_flg',
    ];

    protected $fillable = self::FIELDS ;

    protected $primaryKey = 'check_list_id';
    protected $keyType    = 'string';

    protected $table = 't_check_list';

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'task_id');
    }

    public function scopeComplete($query)
    {
        return  $query->where('complete_flg', config('apps.checklist.is_completed'));
    }
    public function scopeNotComplete($query)
    {
        return  $query->where('complete_flg', '!=', config('apps.checklist.is_completed'));
    }
}
