<?php

namespace App\Repositories;

use App\Models\CompanySearchKeyword;

class CompanySearchKeywordRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(CompanySearchKeyword::class);
        $this->fields = CompanySearchKeyword::FIELDS ;     
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
