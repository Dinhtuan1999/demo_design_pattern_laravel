<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Remind extends Model
{
    use HasFactory;

    protected $table = 't_remind';

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'remaind_id',
        'task_id',
        'remaind_datetime',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
        'delete_flg',
    ];

    protected $fillable = self::FIELDS;

    protected $primaryKey = 'remaind_id';
    protected $keyType    = 'string';

    public const TASK = "task";

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'task_id');
    }
}
