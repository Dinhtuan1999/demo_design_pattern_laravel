<?php

namespace App\Http\Controllers\PC\Contact;

use App\Events\Contacts\ContactChangeStatusEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\AddContactResponseRequest;
use App\Services\ContactRejectionReasonService;
use App\Services\ContactResponseService;
use App\Services\ContactPurposeService;
use App\Models\Contact;
use App\Services\ContactSendService;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ContactController extends Controller
{
    private $contactService;
    private $contactRejectionReasonService;
    private $contactResponseService;
    private $contactPurposeService;
    private $contactSendService;

    public function __construct(
        ContactService                $contactService,
        ContactRejectionReasonService $contactRejectionReasonService,
        ContactResponseService        $contactResponseService,
        ContactPurposeService         $contactPurposeService,
        ContactSendService            $contactSendService
    ) {
        $this->contactService = $contactService;
        $this->contactRejectionReasonService = $contactRejectionReasonService;
        $this->contactResponseService = $contactResponseService;
        $this->contactSendService = $contactSendService;
        $this->contactPurposeService = $contactPurposeService;
    }

    /**
     * get list contact of company
     *
     * @param Request $request
     * @return View|JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if (Gate::denies('accountMember')) {
            abort(403);
        }

        $companyId = $user->company_id;
        $consentFilters = [];
        if (!empty($request->consents)) {
            $consentFilterConverts = array_map('intval_except_null', $request->consents);
            $consentFilters['consent_classification'] = $consentFilterConverts;
        }
        $contactSends = $this->contactSendService->getListContactSendByCompany($companyId, $consentFilters);
        if ($request->ajax() && $request->tab == Contact::TYPE_SEND) {
            return $this->respondSuccess(trans('message.COMPLETE'), $contactSends);
        }

        $contactResponses = $this->contactSendService->getListContactResponseByCompany($companyId, $consentFilters);
        if ($request->ajax() && $request->tab == Contact::TYPE_RESPONSE) {
            return $this->respondSuccess(trans('message.COMPLETE'), $contactResponses);
        }

        $dataContactRejectionReason = $this->contactRejectionReasonService->getContactRejectionReason();
        $listContacPurpose = $this->contactPurposeService->getContactPurpose();

        return view('contacts.index', [
            'contactSends' => $contactSends['data'] ?? [],
            'contactResponses' => $contactResponses['data'] ?? [],
            'listContacPurpose' => $listContacPurpose,
            'dataContactRejectionReason' => $dataContactRejectionReason['data'] ?? []
        ]);
    }

    public function createContactResponse(AddContactResponseRequest $request)
    {
        $user = Auth::user();
        $userId = $user->user_id;
        $addressCompanyId = $request->get('address_company_id');
        $companyId = $request->get('company_id');
        $contactMessage = $request->get('response_message');
        $contactAddress = $request->get('contact_address');
        $contactMessageSenderId = $request->get('contact_message_sender_id');
        $contactId = $request->get('contact_id');
        $consentFlg = $request->get('consent_classification');
        $readFlg = $request->get('read_flg');
        $contactRejectionReasonId = $request->get('contact_rejection_reason_id');
        $interestPoint = $request->get('interest_point');

        $contactResponse = $this->contactResponseService->create($companyId, $addressCompanyId, $contactMessage, $contactAddress, $contactMessageSenderId, $contactId, $consentFlg, $readFlg, $contactRejectionReasonId, $userId, $interestPoint);

        if (empty($contactResponse) || $contactResponse['status'] == config('apps.general.error')) {
            return $this->respondWithError(trans('message.NOT_COMPLETE'));
        }

        if (!empty($contactId) && $user) {
            broadcast(new ContactChangeStatusEvent($user, $contactId));
        }

        return $this->respondSuccess(trans('message.COMPLETE'));
    }

    public function destroy(Request $request)
    {
        $contactId = $request->contact_id;
        $tab = $request->tab;
        return $tab == Contact::TYPE_RESPONSE ? $this->contactService->deleteContactResponse($contactId) : $this->contactService->deleteContactSend($contactId);
    }

    public function setIsRead(Request $request)
    {
        $user = Auth::user();
        if ($request->is_read == config('apps.contact.unread')) {
            $this->contactService->setContactIsRead($request->contact_id, $request->tab);
            if (!empty($request->contact_id) && $user) {
                broadcast(new ContactChangeStatusEvent($user, $request->contact_id));
            }
        }
        $contact_id = $request->get('contact_id');
        $data = $this->contactResponseService->getContactResponseDetail($contact_id);

        if (empty($data) || isset($data['status']) == config('apps.general.error')) {
            return $this->respondWithError([trans('message.NOT_COMPLETE')]);
        }
        return $this->respondSuccess(trans('message.COMPLETE'), $data);
    }
}
