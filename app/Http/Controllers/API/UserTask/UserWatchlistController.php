<?php

namespace App\Http\Controllers\API\UserTask;

use App\Http\Controllers\Controller;
use App\Services\UserWatchlistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserWatchlistController extends Controller
{
    public function __construct(UserWatchlistService $userWatchlistService)
    {
        $this->userWatchlistService = $userWatchlistService;
    }

    public function myWatchlist(Request $request)
    {
        try {
            $currentUser = auth('api')->user();
            $data = $this->userWatchlistService->listUserWatch($request, $currentUser->user_id);
            $data['data'] = !empty($data['data']['data']) ? $data['data']['data'] : [];
            return response()->json($data);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return [
                'status' => config('apps.general.error'),
                'message' => [trans('message.ERR_EXCEPTION')],
                'message_id' => ['ERR_EXCEPTION'],
                'error_code' => config('apps.general.error_code')
            ];
        }
    }


    private $userWatchlistService;
}
