<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    public const TASK = "task";

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'comment_id',
        'task_id',
        'contributor_id',
        'comment',
        'attachment_file_id',
        'delete_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
    ];

    public $incrementing = false;

    protected $fillable   = self::FIELDS;
    protected $keyType    = 'string';
    protected $primaryKey = 'comment_id';
    protected $table      = 't_comment';

    public function task()
    {
        return $this->belongsToMany(Task::class, 'task_id', 'task_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'contributor_id', 'user_id');
    }
}
