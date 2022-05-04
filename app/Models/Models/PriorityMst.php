<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriorityMst extends Model
{
    use HasFactory;

    public const TASK = "task";

    protected $table = 'm_priority_mst';

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    protected $primaryKey = 'priority_id';
    public $incrementing = false;

    protected $fillable = [
        'priority_id',
        'priority_name',
        'display_order',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    // public function task() {
    //     return $this->belongsTo(Task::class, 'priority_id', 'task_id');
    // }
}
