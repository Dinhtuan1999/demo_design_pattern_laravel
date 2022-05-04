<?php

namespace App\Repositories;

use App\Models\TaskGroupDispColor;

class TaskGroupDispColorRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(TaskGroupDispColor::class);

        $this->fields = $this->getInstance()->getFillable();
    }
}
