<?php

namespace App\Repositories;

use App\Models\CompanyInfoReferenceNum;

class CompanyInfoReferenceNumRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(CompanyInfoReferenceNum::class);
        $this->fields = CompanyInfoReferenceNum::FIELDS ;     
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
