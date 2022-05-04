<?php

namespace App\Repositories;

use App\Models\ContactResponse;

class ContactResponseRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(ContactResponse::class);
        $this->fields = ContactResponse::FIELDS;
    }

    public function isExists($contact_id)
    {
        return $this->getInstance()::where('contact_id', $contact_id)->exists();
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
