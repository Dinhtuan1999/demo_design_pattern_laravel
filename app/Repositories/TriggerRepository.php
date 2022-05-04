<?php

namespace App\Repositories;

use App\Models\Trigger;

class TriggerRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(Trigger::class);
    }
    
}
