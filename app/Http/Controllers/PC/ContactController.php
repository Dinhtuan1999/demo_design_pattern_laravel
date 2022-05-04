<?php

namespace App\Http\Controllers\PC;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\AddContactMessageRequest;
use App\Models\Company;
use App\Services\ContactPurposeService;
use App\Services\ContactSendService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    private $ContactSendService;
    private $contactPurposeService;

    public function __construct(ContactSendService $ContactSendService, ContactPurposeService $contactPurposeService)
    {
        $this->ContactSendService = $ContactSendService;
        $this->contactPurposeService = $contactPurposeService;
    }

    public function createMessage(AddContactMessageRequest $request)
    {
        $userId = Auth::user()->user_id;
        $senderCompanyId = Auth::user()->company_id;

        $contactMessage = $this->ContactSendService->create($request, $userId, $senderCompanyId);

        if (empty($contactMessage) || $contactMessage['status'] == config('apps.general.error')) {
            return $this->respondWithError([trans('message.NOT_COMPLETE')]);
        }

        return $this->respondSuccess(trans('message.SUCCESS'));
    }
}
