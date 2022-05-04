<?php

namespace App\Repositories;

use App\Models\County;

class CountyRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(County::class);
        $this->fields = County::FIELDS;
    }
}
