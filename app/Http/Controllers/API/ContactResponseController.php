<?php

namespace App\Http\Controllers\API;

use App\Helpers\Transformer;
use App\Http\Controllers\API\Controller;
use App\Http\Requests\Contact\AddContactResponseRequest;
use App\Http\Requests\Contact\GetContactResponseDetailRequest;
use App\Services\ContactResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactResponseController extends Controller
{
    private $contactResponseService;

    public function __construct(ContactResponseService $contactResponseService)
    {
        $this->contactResponseService = $contactResponseService;
    }

    public function getContactResponseDetail(GetContactResponseDetailRequest $request)
    {
        $contact_id = $request->get('contact_id');
        $contact_Response = $this->contactResponseService->getContactResponseDetail($contact_id);

        if (empty($contact_Response) || isset($contact_Response['status']) == config('apps.general.error')) {
            return $this->respondWithError([trans('message.NOT_COMPLETE')]);
        }

        return $this->respondSuccess([trans('message.COMPLETE')], $contact_Response);
    }

    public function createContactResponse(AddContactResponseRequest $request)
    {
        $user = Auth::user()->user_id;
        $companyId = $request->get('company_id');
        $addressCompanyId = $request->get('address_company_id');
        $contactMessage = $request->get('contact_message');
        $contactAddress = $request->get('contact_address');
        $contactMessageSenderId = $request->get('contact_message_sender_id');
        $contactId = $request->get('contact_id');
        $consentFlg = $request->get('consent_flg');
        $readFlg = $request->get('read_flg');
        $contactRejectionJeasonId = $request->get('contact_rejection_reason_id');
        $interestPoint = $request->get('interest_point');

        $contactResponse = $this->contactResponseService->create($companyId, $addressCompanyId, $contactMessage, $contactAddress, $contactMessageSenderId, $contactId, $consentFlg, $readFlg, $contactRejectionJeasonId, $user, $interestPoint);
        if (empty($contactResponse) || $contactResponse['status'] == config('apps.general.error')) {
            return $this->respondWithError(trans('message.NOT_COMPLETE'));
        }

        return $this->respondSuccess(trans('message.COMPLETE'));
    }
}
