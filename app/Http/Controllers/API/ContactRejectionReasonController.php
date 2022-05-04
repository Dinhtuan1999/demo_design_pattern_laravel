<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Controller;
use Illuminate\Http\Request;
use App\Services\ContactRejectionReasonService;

class ContactRejectionReasonController extends Controller
{
    private $contactRejectionReasonService;

    public function __construct(ContactRejectionReasonService $contactRejectionReasonService)
    {
        $this->contactRejectionReasonService = $contactRejectionReasonService;
    }

    public function getContactRejectionReason(Request $request)
    {

        // 1. Call to Contact Purpose service with getContactPurpose function
        $data = $this->contactRejectionReasonService->getContactRejectionReason();
        // 2. Return response base on service's status
        if (empty($data) || $data['status'] == config('apps.general.error')) {
            return $this->respondWithError([trans('message.NOT_COMPLETE')]);
        }

        return response()->json($data);
    }
}
