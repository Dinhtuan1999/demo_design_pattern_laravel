<?php

namespace App\Repositories;

use App\Models\Contract;

class ContractRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(Contract::class);
        $this->fields = Contract::FIELDS;
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
