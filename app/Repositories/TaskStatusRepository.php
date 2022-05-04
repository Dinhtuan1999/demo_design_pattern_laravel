<?php

namespace App\Repositories;

use App\Models\TaskStatus;

class TaskStatusRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(TaskStatus::class);
        $this->fields = TaskStatus::FIELDS;
    }

    public function isExists($taskStatusId)
    {
        return $this->getInstance()::where('task_status_id', $taskStatusId)->exists();
    }
}
