<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Followup extends Model
{
    use HasFactory;
    use HasCompositePrimaryKey;

    public const USER = "user";
    public const BREAKDOWN = "breakdown";

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    protected $primaryKey = ['breakdown_id', 'task_id', 'followup_user_id'];
    public $incrementing = false;

    protected $table = 't_followup';

    protected $fillable = [
        'breakdown_id',
        'task_id',
        'followup_user_id',
        'followup_reason',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'followup_user_id', 'user_id');
    }

    // public function breakdown() {
    //     return $this->belongsTo(Breakdown::class, 'breakdown_id', 'breakdown_id');
    // }
}
