<?php

namespace App\Repositories;

use App\Models\ContactRejectionReason;

class ContactRejectionReasonRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(ContactRejectionReason::class);
        $this->fields = ContactRejectionReason::FIELDS;
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

    public function getContactRejectionReason($queryParam = [])
    {
        return  $this->getInstance()->where($queryParam)->orderBy('contact_rejection_reason', 'DESC')
                        ->get([
                            'contact_rejection_reason_id',
                            'contact_rejection_reason'
                        ]);
    }
}
