<?php


namespace App\Repositories;


use App\Models\SaveCompanySearchCondition;

class SaveCompanySearchConditionRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(SaveCompanySearchCondition::class);
        $this->fields = SaveCompanySearchCondition::FIELDS;
    }
}
