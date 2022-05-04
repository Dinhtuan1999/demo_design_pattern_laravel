<?php

namespace App\Repositories;

use App\Models\ContactPurpose;

class ContactPurposeRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(ContactPurpose::class);
        $this->fields = ContactPurpose::FIELDS;
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

    public function getContactPurpose($queryParam = [])
    {
        return  $this->getInstance()->where($queryParam)->orderBy('display_order', 'ASC')
                        ->get([
                            'contact_purpose',
                            'contact_purpose_id'
                        ]);
    }

    public function isExists($contact_id)
    {
        return $this->getInstance()::where('contact_purpose_id', $contact_id)->exists();
    }
}
