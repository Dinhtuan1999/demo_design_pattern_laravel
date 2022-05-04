<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Controller;
use Illuminate\Http\Request;
use App\Services\IndustryService;
use App\Http\Requests\API\ApiGetIndustryRequest;
use Illuminate\Support\Facades\Auth;

class IndustryController extends Controller
{
    protected $industryService;

    public function __construct(IndustryService $industryService)
    {
        $this->industryService = $industryService;
    }

    public function getIndustries(ApiGetIndustryRequest $request)
    {
        // 1. Call to Industry service with getIndustry function
        $data = $this->industryService->getIndustries($request);

        // 2. Return response base on service's status
        if ($data['status'] == config('apps.general.error')) {
            return $this->respondWithError(trans("message.NOT_COMPLETE"));
        }

        return $this->respondSuccess(trans("message.COMPLETE"), $data['data']);
    }
}
