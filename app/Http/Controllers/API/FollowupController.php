<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\BreakdownService;
use App\Services\FollowupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FollowupController extends Controller
{
    protected $followupService;
    protected $breakdownService;

    public function __construct(
        FollowupService $followupService,
        BreakdownService $breakdownService
    ) {
        $this->followupService = $followupService;
        $this->breakdownService = $breakdownService;
    }

    public function addOrDeleteFollowup(Request $request)
    {
        $currentUser = auth('api')->user();

        $breakdown = $this->breakdownService->checkRecord($request->input('breakdown_id'));
        if ($breakdown['status'] != config('apps.general.success')) {
            return response()->json($breakdown);
        }

        $result = $this->followupService->addOrDeleteFollowup($breakdown['data'], $currentUser->user_id);

        return response()->json($result);
    }
}
