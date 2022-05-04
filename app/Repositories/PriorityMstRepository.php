<?php

namespace App\Repositories;

use App\Models\PriorityMst;

class PriorityMstRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(PriorityMst::class);

        $this->fields = $this->getInstance()->getFillable();
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
