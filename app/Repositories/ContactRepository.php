<?php

namespace App\Repositories;

use App\Models\Contact;

class ContactRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(Contact::class);
        $this->fields = Contact::FIELDS;
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

    public function getListContactSend($params, $filterWhereIns, $sort)
    {
        $params['c_send.delete_flg'] = config('apps.general.not_deleted');
        return $this->getInstance()::where($params)
            ->where(function ($q) use ($filterWhereIns) {
                foreach ($filterWhereIns as $field => $arrIn) {
                    $q = $q->whereIn($field, $arrIn);
                }
            })
            ->select('t_contact.*', 'c_send.read_flg', 'u.disp_name')
            ->join('t_contact_send as c_send', 'c_send.contact_id', '=', 't_contact.contact_id')
            ->leftjoin('t_user as u', 'c_send.send_user_id', '=', 'u.user_id')
            ->with([
                Contact::SENDER_COMPANY,
                Contact::DESTINATION_COMPANY,
                Contact::CONTACT_PURPOSE,
                Contact::CONTACT_REJECTION_REASON
            ])
            ->orderBy('t_contact.' . $sort['by'], $sort['type'])
            ->paginate(config('apps.contact.record_per_page'));
    }

    public function getListContactResponse($params, $filterWhereIns, $sort)
    {
        $params['c_send.delete_flg'] = config('apps.general.not_deleted');
        return $this->getInstance()::where($params)
            ->where(function ($q) use ($filterWhereIns) {
                foreach ($filterWhereIns as $field => $arrIn) {
                    $q = $q->whereIn($field, $arrIn);
                }
            })
            ->select('t_contact.*', 'c_send.*', 'u.disp_name')
            ->join('t_contact_send as c_send', 'c_send.contact_id', '=', 't_contact.contact_id')
            ->leftjoin('t_user as u', 'c_send.send_user_id', '=', 'u.user_id')
            ->with([
                Contact::SENDER_COMPANY,
                Contact::DESTINATION_COMPANY,
                Contact::CONTACT_PURPOSE,
                Contact::CONTACT_REJECTION_REASON
            ])
            ->orderBy('t_contact.' . $sort['by'], $sort['type'])
            ->paginate(config('apps.contact.record_per_page'));
    }

    public function update($contact_id, $data)
    {
        $data = Contact::where('contact_id', $contact_id)->update($data);
        return $data;
    }

    public function isExists($contact_id)
    {
        return $this->getInstance()::where('contact_id', $contact_id)->exists();
    }
}
