<?php

namespace App\Repositories;

use App\Models\ProjectAttribute;

class ProjectAttributeRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(ProjectAttribute::class);
        $this->fields = ProjectAttribute::FIELDS;
    }

    public function isExists($projectAttributeId)
    {
        return $this->getInstance()::where('project_attribute_id', $projectAttributeId)->exists();
    }
}
