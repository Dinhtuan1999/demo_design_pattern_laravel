<?php


namespace App\Repositories;


use App\Models\ProjectParticipant;

class ProjectParticipantRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(ProjectParticipant::class);
        $this->fields = ProjectParticipant::FIELDS;
    }
}
