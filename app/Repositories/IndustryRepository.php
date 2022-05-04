<?php

namespace App\Repositories;

use App\Models\Industry;

class IndustryRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(Industry::class);
        $this->fields = Industry::FIELDS;
    }

    public function getIndustries($queryParam = [])
    {
        return  $this->getInstance()->where($queryParam)->orderBy('industry_name', 'DESC')
                        ->get();
    }
}
