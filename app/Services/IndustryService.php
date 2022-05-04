<?php

namespace App\Services;

use App\Repositories\IndustryRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Log;

class IndustryService extends BaseService
{
    public function __construct(IndustryRepository $industryRepo)
    {
        $this->industryRepo = $industryRepo;
    }

    public function getIndustries()
    {
        try {
            $industries = $this->industryRepo->getIndustries();
            if ($industries) {
                return $this->sendResponse(trans('message.COMPLETE'), $industries->toArray());
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.NOT_COMPLETE')
            );
        }
    }
    private $industryRepo;
}
