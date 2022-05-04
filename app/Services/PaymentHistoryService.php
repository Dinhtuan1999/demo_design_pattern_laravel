<?php

namespace App\Services;

use App\Repositories\PaymentHistoryRepository;
use Illuminate\Support\Facades\Log;

class PaymentHistoryService extends BaseService
{
    private $paymentHistoryRepo;

    public function __construct(PaymentHistoryRepository $paymentHistoryRepo)
    {
        $this->paymentHistoryRepo = $paymentHistoryRepo;
    }

    /**
     * Get payment histories of company
     *
     * @param string $companyId
     * @return array
     */
    public function getPaymentHistories(string $companyId)
    {
        try {
            $paymentHistories = $this->paymentHistoryRepo->getPaymentHistories($companyId);

            return $this->sendResponse(
                trans('message.COMPLETE'),
                $paymentHistories->toArray()
            );
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.ERR_EXCEPTION')
            );
        }
    }
}
