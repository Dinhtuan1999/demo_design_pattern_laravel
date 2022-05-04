<?php

namespace App\Models;

use App\Scopes\DeleteFlgNotDeleteScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trash extends Model
{
    use HasFactory;

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'trash_id',
        'identyfying_code',
        'project_id',
        'task_group_id',
        'task_id',
        'attachment_file_id',
        'delete_date',
        'delete_user_id',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    protected $table = 't_trash';
    protected $primaryKey = 'trash_id';
    protected $keyType    = 'string';
    public $incrementing = false;

    public const FILE = "attachment_file";
    public const TASK = "task";
    public const PROJECT = "project";
    public const TASK_GROUP = "task_group";

    public const RELATIONS = [self::FILE, self::TASK, self::PROJECT, self::TASK_GROUP];

    protected $fillable = self::FIELDS;

    public function attachment_file()
    {
        return $this->belongsTo(AttachmentFile::class, 'attachment_file_id', 'attachment_file_id');
    }

    public function task_group()
    {
        return $this->belongsTo(TaskGroup::class, 'task_group_id', 'task_group_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'task_id')->withoutGlobalScope(new DeleteFlgNotDeleteScope());
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id')
            ->withoutGlobalScope(new DeleteFlgNotDeleteScope());
    }
}
