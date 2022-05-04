<?php

namespace App\Repositories;

use App\Models\Contact;
use App\Models\ContactResponse;
use App\Models\ContactSend;
use Illuminate\Pagination\LengthAwarePaginator;

class ContactSendRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(ContactSend::class);
        $this->fields = ContactSend::FIELDS;
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

    /**
     * Query Get List Contact Send
     * @param string $companyId
     * @param array $filterWhereIns
     * @param array $sort
     * @return LengthAwarePaginator
     */
    public function getListContactSendByCompany(string $companyId, array $filterWhereIns, array $sort): LengthAwarePaginator
    {
        return $this->getInstance()::with([
            ContactSend::CONTACT,
            ContactSend::CONTACT . '.' . Contact::SENDER_COMPANY => function ($q) {
                $q->select('company_id', 'company_name');
            },
            ContactSend::CONTACT . '.' . Contact::DESTINATION_COMPANY => function ($q) {
                $q->select('company_id', 'company_name');
            },
            ContactSend::CONTACT . '.' . Contact::CONTACT_PURPOSE => function ($q) {
                $q->select('contact_purpose_id', 'contact_purpose');
            },
            ContactSend::CONTACT . '.' . Contact::CONTACT_REJECTION_REASON,
            ContactSend::SEND_USER => function ($q) {
                $q->select('user_id', 'disp_name');
            },
            ContactSend::CONTACT_RESPONSE,
            ContactSend::CONTACT_RESPONSE . '.' . ContactResponse::RESPONSE_USER => function ($q) {
                $q->select('user_id', 'disp_name');
            }
        ])
            ->whereHas(ContactSend::CONTACT, function ($q) use ($filterWhereIns, $companyId) {
                foreach ($filterWhereIns as $field => $arrIn) {
                    $q->whereIn($field, $arrIn);
                }
                $q->where('sender_company_id', $companyId);
                $q->avaiable();
            })
            ->orderBy($sort['by'], $sort['type'])
            ->paginate(config('apps.contact.record_per_page'));
    }

    /**
     * Query Get List Contact Response
     * @param string $companyId
     * @param array $filterWhereIns
     * @param array $sort
     * @return LengthAwarePaginator
     */
    public function getListContactResponseByCompany(string $companyId, array $filterWhereIns, array $sort): LengthAwarePaginator
    {
        return $this->getInstance()::with([
            ContactSend::CONTACT,
            ContactSend::CONTACT . '.' . Contact::SENDER_COMPANY => function ($q) {
                $q->select('company_id', 'company_name');
            },
            ContactSend::CONTACT . '.' . Contact::DESTINATION_COMPANY => function ($q) {
                $q->select('company_id', 'company_name');
            },
            ContactSend::CONTACT . '.' . Contact::CONTACT_PURPOSE => function ($q) {
                $q->select('contact_purpose_id', 'contact_purpose');
            },
            ContactSend::CONTACT . '.' . Contact::CONTACT_REJECTION_REASON,
            ContactSend::SEND_USER => function ($q) {
                $q->select('user_id', 'disp_name');
            },
            ContactSend::CONTACT_RESPONSE,
            ContactSend::CONTACT_RESPONSE . '.' . ContactResponse::RESPONSE_USER => function ($q) {
                $q->select('user_id', 'disp_name');
            }
        ])
            ->whereHas(ContactSend::CONTACT, function ($q) use ($filterWhereIns, $companyId) {
                foreach ($filterWhereIns as $field => $arrIn) {
                    $q->whereIn($field, $arrIn);
                }
                $q->where('destination_company_id', $companyId);
                $q->avaiable();
            })
            ->orderBy($sort['by'], $sort['type'])
            ->paginate(config('apps.contact.record_per_page'));
    }
}
