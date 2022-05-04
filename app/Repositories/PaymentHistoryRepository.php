<?php

namespace App\Repositories;

use App\Models\PaymentHistory;

class PaymentHistoryRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(PaymentHistory::class);

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

    public function getPaymentHistories($companyId)
    {
        return $this->getInstance()::where('company_id', $companyId)
            ->select(['payment_history_id', 'billing_date', 'billing_amount'])
            ->orderBy('billing_date', 'asc')->get();
    }
}
