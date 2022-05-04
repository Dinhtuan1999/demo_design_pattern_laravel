<?php

namespace App\Repositories;

use App\Models\CreditCardInfo;

class CreditCardInfoRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(CreditCardInfo::class);
        $this->fields = CreditCardInfo::FIELDS;
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
