<?php

namespace App\Repositories;

use App\Models\CheckList;

class CheckListRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(CheckList::class);
        $this->fields = CheckList::FIELDS ;     
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
