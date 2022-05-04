<?php

namespace App\Services;

use App\Repositories\CompanyRepository;
use App\Repositories\ContactPurposeRepository;
use App\Repositories\ContactRepository;
use App\Repositories\ContactSendRepository;
use App\Repositories\MessageRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class ContactSendService extends BaseService
{
    private $contactSendRepo;
    private $contactRepo;
    private $contactPurposeRepo;
    private $companyRepo;
    private $userRepo;

    public function __construct(ContactSendRepository $contactSendRepo, ContactRepository $contactRepo, ContactPurposeRepository $contactPurposeRepo, CompanyRepository $companyRepo, UserRepository $userRepo)
    {
        $this->contactSendRepo = $contactSendRepo;
        $this->contactRepo = $contactRepo;
        $this->contactPurposeRepo = $contactPurposeRepo;
        $this->companyRepo = $companyRepo;
        $this->userRepo = $userRepo;
    }

    public function getDetailMessage($contact_id)
    {
        // check exists contact
        $contact = $this->contactRepo->getByCols([
            'contact_id' => $contact_id,
            'delete_flg' => config('apps.general.not_deleted')
        ]);
        if (!$contact) {
            return self::sendError([trans('message.ERR_COM_0011', ['attribute' => trans('label.contact.contact')])]);
        }

        $data = [
            'interest_point' => $contact->interest_point,
            'contact_message' => $contact->contact_message,
            'contact_purpose' => !empty($contact->contact_purpose) ? $contact->contact_purpose->contact_purpose : null,
        ];

        return self::sendResponse([trans('message.SUCCESS')], $data);
    }

    public function create($request, $userId, $senderCompanyId)
    {
        if (!$this->companyRepo->isExists($request->destination_company_id)) {
            return $this->sendError([trans('message.NOT_COMPLETE')]);
        }
        try {
            $contactMessageData = [
                'contact_id' => AppService::generateUUID(),
                'sender_company_id' => $senderCompanyId,
                'interest_point' => $request->interest_point,
                'contact_message' => $request->contact_message,
                'contact_purpose_id' => $request->contact_purpose_id,
                'destination_company_id' => $request->destination_company_id,
                'consent_classification' => config('apps.contact.contact_not_answer'),
                'delete_flg' => config('apps.general.not_deleted'),
                'create_user_id' => $userId,
            ];
            $newContact = $this->contactRepo->store($contactMessageData);

            if ($newContact->wasRecentlyCreated) {
                $contactSendData = [
                    'contact_id' => $contactMessageData['contact_id'],
                    'create_user_id' => $userId,
                    'update_user_id' => $userId,
                    'send_user_id' => $userId
                ];
                $this->contactSendRepo->store($contactSendData);
            }
            return $this->sendResponse([trans('message.COMPLETE')]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError([trans('message.NOT_COMPLETE')]);
        }
    }

    public function validateContactSendDetail(Request $request)
    {
        return Validator::make(
            $request->all(),
            [
                'contact_id' => [
                    'required',
                    Rule::exists('t_contact', 'contact_id')->where(function ($query) {
                        return $query->where('delete_flg', config('apps.general.not_deleted'));
                    }),
                ],
            ],
            [
                'contact_id.required' => trans('message.ERR_COM_0001', ['attribute' => trans('label.contact.contact')]),
                'contact_id.exists' => trans('message.ERR_COM_0011', ['attribute' => trans('label.contact.contact')]),
            ]
        );
    }

    /**
     * Get List Contact Send
     * @param string $companyId
     * @param array $filterWhereIns
     * @param array $sort
     * @return array
     */
    public function getListContactSendByCompany(string $companyId, array $filterWhereIns = [], array $sort = [
        'by' => 'create_datetime',
        'type' => 'desc'
    ]): array
    {
        try {
            $contactSends = $this->contactSendRepo->getListContactSendByCompany($companyId, $filterWhereIns, $sort);

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
     * @param string $companyId
     * @param array $filterWhereIns
     * @param array $sort
     * @return array
     */
    public function getListContactResponseByCompany(string $companyId, array $filterWhereIns = [], array $sort = [
        'by' => 'create_datetime',
        'type' => 'desc'
    ]): array
    {
        try {
            $contactResponses = $this->contactSendRepo->getListContactResponseByCompany($companyId, $filterWhereIns, $sort);
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
