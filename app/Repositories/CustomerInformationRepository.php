<?php

namespace App\Repositories;

use App\Models\CustomerInformation;

class CustomerInformationRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(CustomerInformation::class);
        $this->fields = CustomerInformation::FIELDS;
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
