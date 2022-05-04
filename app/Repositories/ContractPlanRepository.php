<?php

namespace App\Repositories;

use App\Models\ContractPlan;

class ContractPlanRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(ContractPlan::class);
        $this->fields = ContractPlan::FIELDS;
    }

    public function formatAllRecord($records)
    {
        if (!empty($records)) {
            foreach ($records as &$record) {
                $record = $this->formatRecord($record);
            }
        }
        return $records;
    }

    public function formatRecord($record)
    {
        return $record;
    }
}
