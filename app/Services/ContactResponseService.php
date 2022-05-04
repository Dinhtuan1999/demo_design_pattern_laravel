<?php

namespace App\Services;

use App\Repositories\CompanyRepository;
use App\Repositories\ContactRepository;
use App\Repositories\ContactResponseRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContactResponseService extends BaseService
{
    private $contactResponseRepo;
    private $companyRepo;
    private $contactRepo;
    private $userRepo;

    public function __construct(ContactResponseRepository $contactResponseRepo, CompanyRepository $companyRepo, ContactRepository $contactRepo, UserRepository $userRepo)
    {
        $this->contactResponseRepo = $contactResponseRepo;
        $this->companyRepo = $companyRepo;
        $this->contactRepo = $contactRepo;
        $this->userRepo = $userRepo;
    }

    /**
     * Set contact is read
     *
     * @param string $contactId
     * @return array
     */
    public function getContactResponseDetail($contact_id)
    {
        $response = [];

        if (!$this->contactRepo->isExists($contact_id)) {
            return $this->sendError([trans('message.NOT_COMPLETE')]);
        }

        $contactResponse = $this->contactRepo->getModel()::with(['sender_company', 'destination_company', 'contact_purpose', 'contact_rejection_reason', 'contact_send', 'contact_response'])
            ->where('t_contact.contact_id', $contact_id)->first();
        if ($contactResponse) {
            return [
                'contact_id' => $contactResponse->contact_id,
                'contact_message' => $contactResponse->contact_message,
                'response_message' => $contactResponse->response_message,
                'contact_address' => $contactResponse->contact_address,
                'sender_company_id' => $contactResponse->sender_company_id,
                'consent_flg' => $contactResponse->consent_classification,
                'read_flg' => $contactResponse->read_flg,
                'contact_rejection_reason_id' => $contactResponse->contact_rejection_reason_id,
                'sender_company' => $contactResponse->destination_company->company_name,
                'contact_purpose' => $contactResponse->contact_purpose->contact_purpose,
                'interest_point' => $contactResponse->interest_point,
                'destination_company' => $contactResponse->sender_company->company_name,
                'contact_rejection_reason' => $contactResponse->contact_rejection_reason->contact_rejection_reason ?? null,
                'destination_company_id' => $contactResponse->destination_company_id,
            ];
        }

        return $response;
    }

    public function create($companyId, $addressCompanyId, $contactMessage, $contactAddress, $contactMessageSenderId, $contactId, $consentFlg, $readFlg, $contactRejectionReasonId, $user, $interestPoint)
    {
        try {
            if (!$this->companyRepo->isExists($companyId) || !$this->companyRepo->isExists($addressCompanyId)) {
                return $this->sendError([trans('message.NOT_COMPLETE')]);
            }

            if (!$this->userRepo->isExists($contactMessageSenderId)) {
                return $this->sendError([trans('message.NOT_COMPLETE')]);
            }

            if (empty($contactId)) {
                $contactData = [
                    'sender_company_id' => $companyId,
                    'contact_id' => AppService::generateUUID(),
                    'destination_company_id' => $addressCompanyId,
                    'response_message' => $contactMessage,
                    'consent_classification' => $consentFlg,
                    'contact_address' => $contactAddress,
                    'contact_rejection_reason_id' => $contactRejectionReasonId,
                ];

                DB::beginTransaction();
                $this->contactRepo->store($contactData);

                $contactResponseData = [
                    'contact_id' => $contactData['contact_id'],
                    'read_flg' => $readFlg,
                    'response_user_id' => $contactMessageSenderId,
                    'create_user_id' => $user,
                    'update_user_id' => $user,
                ];

                $this->contactResponseRepo->store($contactResponseData);
                DB::commit();

                return $this->sendResponse(trans('message.COMPLETE'));
            } else {
                $contactData = [
                    'sender_company_id' => $companyId,
                    'contact_id' => $contactId,
                    'destination_company_id' => $addressCompanyId,
                    'response_message' => $contactMessage,
                    'consent_classification' => $consentFlg,
                    'contact_address' => $contactAddress,
                    'contact_rejection_reason_id' => $contactRejectionReasonId,
                ];

                DB::beginTransaction();
                $this->contactRepo->update($contactId, $contactData);

                $contactResponseData = [
                    'contact_id' => $contactId,
                    'response_user_id' => $contactMessageSenderId,
                    'create_user_id' => $user,
                    'update_user_id' => $user,
                    'read_flg' => config('apps.contact.unread'),
                ];

                $this->contactResponseRepo->store($contactResponseData);
                DB::commit();

                return $this->sendResponse(trans('message.COMPLETE'));
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return $this->sendError(trans('message.NOT_COMPLETE'));
        }
    }
}
