<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Breakdown extends Model
{
    use HasCompositePrimaryKey;

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'breakdown_id',
        'task_id',
        'plan_date',
        'work_item',
        'progress',
        'comment',
        'reportee_user_id',
        'display_order',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
        'delete_flg'
    ];
    public $incrementing = false;

    protected $table = "t_breakdown";
    protected $primaryKey = ['breakdown_id', 'task_id'];

    protected $fillable = self::FIELDS;

    public const FOLLOWUPS = "followups";
    public const USER = "user";

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'task_id');
    }

    public function followups()
    {
        return $this->hasMany(Followup::class, 'breakdown_id', 'breakdown_id')
        ->where('delete_flg', config('apps.general.not_deleted'))->with(Followup::USER);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'reportee_user_id', 'user_id');
    }
}
