<?php

namespace App\Http\Controllers\API\ContactPurpose;

use App\Http\Controllers\API\Controller;
use Illuminate\Http\Request;
use App\Services\ContactPurposeService;

class ContactPurposeController extends Controller
{
    private $contactPurposeService;

    public function __construct(ContactPurposeService $contactPurposeService)
    {
        $this->contactPurposeService = $contactPurposeService;
    }

    public function getContactPurpose(Request $request)
    {
        // 1. Call to Contact Purpose service with getContactPurpose function
        $data = $this->contactPurposeService->getContactPurpose();
        // 2. Return response base on service's status
        if (empty($data) || $data['status'] == config('apps.general.error')) {
            return $this->respondWithError([trans('message.NOT_COMPLETE')]);
        }

        return response()->json($data);
    }
}
