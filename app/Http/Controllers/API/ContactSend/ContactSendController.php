<?php

namespace App\Http\Controllers\API\ContactSend;

use App\Http\Controllers\API\Controller;
use App\Http\Requests\Contact\AddContactMessageRequest;
use App\Http\Requests\ContactSend\GetContactSendDetailRequest;
use App\Services\ContactSendService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactSendController extends Controller
{
    private $ContactSendService;

    public function __construct(ContactSendService $ContactSendService)
    {
        $this->ContactSendService = $ContactSendService;
    }

    public function getContactSendDetail(Request $request)
    {
        $validator = $this->ContactSendService->validateContactSendDetail($request);
        if ($validator->fails()) {
            return $this->sendError($validator->errors()->all());
        }

        $result = $this->ContactSendService->getDetailMessage($request->get('contact_id'));

        return response()->json($result);
    }

    public function createMessage(AddContactMessageRequest $request)
    {
        $userId = Auth::user()->user_id;
        $senderCompanyId = Auth::user()->company_id;

        $contactMessage = $this->ContactSendService->create($request, $userId, $senderCompanyId);
        if (empty($contactMessage) || $contactMessage['status'] == config('apps.general.error')) {
            return $this->respondWithError([trans('message.NOT_COMPLETE')]);
        }

        return $this->respondSuccess([trans('message.COMPLETE')]);
    }
}
