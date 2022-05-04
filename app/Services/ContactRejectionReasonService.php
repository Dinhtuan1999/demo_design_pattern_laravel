<?php

namespace App\Services;

use App\Repositories\ContactRejectionReasonRepository;
use Illuminate\Support\Facades\Log;
use App\Services\BaseService;

class ContactRejectionReasonService extends BaseService
{
    private $contactRejectionReasonRepo;

    public function __construct(
        ContactRejectionReasonRepository $contactRejectionReasonRepo
    ) {
        $this->contactRejectionReasonRepo = $contactRejectionReasonRepo;
    }

    public function getContactRejectionReason()
    {
        try {
            $contactPurpose = $this->contactRejectionReasonRepo->getContactRejectionReason();
            if ($contactPurpose) {
                return $this->sendResponse([trans('message.COMPLETE')], $contactPurpose->toArray());
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                [trans('message.NOT_COMPLETE')]
            );
        }
    }
}
