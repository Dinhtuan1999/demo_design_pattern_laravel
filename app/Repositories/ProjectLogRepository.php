<?php


namespace App\Repositories;


use App\Models\ProjectLog;

class ProjectLogRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(ProjectLog::class);

        $this->fields = ProjectLog::FIELDS;
    }
}
