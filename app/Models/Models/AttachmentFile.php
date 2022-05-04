<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttachmentFile extends Model
{
    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';
    public const TASK = "task";
    public const TRASH = "trash";
    public const FIELDS = [
        'attachment_file_id',
        'task_id',
        'attachment_file_name',
        'attachment_file_path',
        'file_size',
        'delete_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];
    public const PATH_STORAGE_FILE = "/attachment_file/";

    public $incrementing = false;

    protected $table = "t_attachment_file";
    protected $primaryKey = 'attachment_file_id';

    protected $fillable = self::FIELDS;

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'task_id');
    }

    /**
     * TODO
     */
    public function trash()
    {
        return $this->hasOne(Trask::class, 'attachment_file_id', 'attachment_file_id');
    }
}
