<?php

namespace App\Services;

use App\Models\Contact;
use App\Repositories\ContactRepository;
use App\Repositories\ContactResponseRepository;
use App\Repositories\ContactSendRepository;
use Illuminate\Support\Facades\Log;

class ContactService extends BaseService
{
    private $contactResponseRepository;
    private $contactSendRepository;
    private $contactRepository;

    public function __construct(
        ContactResponseRepository $contactResponseRepository,
        ContactSendRepository     $contactSendRepository,
        ContactRepository         $contactRepository
    ) {
        $this->contactResponseRepository = $contactResponseRepository;
        $this->contactSendRepository = $contactSendRepository;
        $this->contactRepository = $contactRepository;
    }

    /**
     * Set contact is read
     *
     * @param string $contactId
     * @param string $type
     * @return array
     */
    public function setContactIsRead(string $contactId, string $type)
    {
        try {
            $repo = [];

            if ($type == Contact::TYPE_RESPONSE) {
                $repo = $this->contactSendRepository;
            } elseif ($type == Contact::TYPE_SEND) {
                $repo = $this->contactResponseRepository;
            }


            $contact = $repo->getByCol('contact_id', $contactId);

            if (empty($contact) || $contact->read_flg == config('apps.contact.read')) {
                return $this->sendError([
                    trans('message.INF_COM_0003')
                ]);
            }

            $repo->updateByField('contact_id', $contactId, [
                'read_flg' => config('apps.contact.read')
            ]);

            return $this->sendResponse(trans('message.COMPLETE'));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.INF_COM_0010')
            );
        }
    }

    /**
     * Delete contact send
     *
     * @param string $contactId
     * @return array
     */
    public function deleteContactSend(string $contactId)
    {
        try {
            $contactSend = $this->contactSendRepository->getByCol('contact_id', $contactId);

            if (empty($contactSend) || $contactSend->delete_flg == config('apps.general.is_deleted')) {
                return $this->sendError([
                    trans('message.INF_COM_0003')
                ]);
            }

            $this->contactSendRepository->updateByField('contact_id', $contactId, [
                'delete_flg' => config('apps.general.is_deleted')
            ]);
            $this->contactRepository->updateByField('contact_id', $contactId, [
                'delete_flg' => config('apps.general.is_deleted')
            ]);

            return $this->sendResponse(trans('message.COMPLETE'));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.INF_COM_0010')
            );
        }
    }

    /**
     * Delete contact response
     *
     * @param string $contactId
     * @return array
     */
    public function deleteContactResponse(string $contactId)
    {
        try {
            $contactResponse = $this->contactResponseRepository->getByCol('contact_id', $contactId);

            if (empty($contactResponse) || $contactResponse->delete_flg == config('apps.general.is_deleted')) {
                return $this->sendError([
                    trans('message.INF_COM_0003')
                ]);
            }

            $this->contactResponseRepository->updateByField('contact_id', $contactId, [
                'delete_flg' => config('apps.general.is_deleted')
            ]);
            $this->contactRepository->updateByField('contact_id', $contactId, [
                'delete_flg' => config('apps.general.is_deleted')
            ]);

            return $this->sendResponse(trans('message.COMPLETE'));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.INF_COM_0010')
            );
        }
    }

    /**
     * Get List Contact Send
     * @param array $params
     * @return array
     */
    public function getListContactSend(array $params = [], $filterWhereIns = [], array $sort = [
        'by' => 'create_datetime',
        'type' => 'desc'
    ])
    {
        try {
            $contactSends = $this->contactRepository->getListContactSend($params, $filterWhereIns, $sort);

            return $this->sendResponse(
                trans('message.COMPLETE'),
                $contactSends
            );
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.INF_COM_0010')
            );
        }
    }

    /**
     * Get List Contact Response
     * @param array $params
     * @return array
     */
    public function getListContactResponse(array $params = [], $filterWhereIns = [], array $sort = [
        'by' => 'create_datetime',
        'type' => 'desc'
    ])
    {
        try {
            $contactResponses = $this->contactRepository->getListContactResponse($params, $filterWhereIns, $sort);

            return $this->sendResponse(
                trans('message.COMPLETE'),
                $contactResponses
            );
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.INF_COM_0010')
            );
        }
    }
}
