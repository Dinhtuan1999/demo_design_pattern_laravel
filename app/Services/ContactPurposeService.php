<?php

namespace App\Services;

use App\Repositories\ContactPurposeRepository;
use Illuminate\Support\Facades\Log;
use App\Services\BaseService;

class ContactPurposeService extends BaseService
{
    private $contactPurposeRepo;

    public function __construct(
        ContactPurposeRepository $contactPurposeRepo
    ) {
        $this->contactPurposeRepo = $contactPurposeRepo;
    }

    public function getContactPurpose()
    {
        try {
            $contactPurpose = $this->contactPurposeRepo->getContactPurpose();
            if ($contactPurpose) {
                return $this->sendResponse([trans('message.SUCCESS')], $contactPurpose->toArray());
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                [trans('message.ERR_EXCEPTION')]
            );
        }
    }
}
