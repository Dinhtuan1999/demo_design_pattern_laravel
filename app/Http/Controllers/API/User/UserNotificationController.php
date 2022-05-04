<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\NotificationRequest;
use App\Services\UserNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserNotificationController extends Controller
{
    protected $userNotificationService;

    public function __construct(UserNotificationService $userNotificationService)
    {
        $this->userNotificationService = $userNotificationService;
    }
    public function userReadNotification(NotificationRequest $request)
    {
        $currentUser = auth('api')->user();

        $result = $this->userNotificationService->readNotification($request->input('notice_id'), $currentUser->user_id);

        return response()->json($result);
    }

    public function getUserNotification(Request $request)
    {
        $currentUser = auth('api')->user();

        $result = $this->userNotificationService->getListUserNotification($currentUser->user_id);

        return response()->json($result);
    }
    public function deleteUserNotification(Request $request)
    {
        $response = [];
        $currentUser = auth('api')->user();
        $validator = Validator::make(request()->all(), [
            'notice_id' => 'required',
        ], [
            'notice_id.required' => trans('validation.required', ['attribute' => trans('validation_attribute.notice_id')]),
        ]);

        if ($validator->fails()) {
            $response['status'] = config('apps.general.error');
            $response['message'] = $validator->errors()->all();
            $response['error_code'] = config('apps.general.error_code', 600);
            return response()->json($response);
        }
        $result = $this->userNotificationService->deleteUserNotification($request->notice_id, $currentUser->user_id);

        return response()->json($result);
    }
    public function changeFlagAllUserNotification(Request $request)
    {
        $currentUser = auth('api')->user();

        $result = $this->userNotificationService->updateReadFlagAllNotification($currentUser->user_id);

        return response()->json($result);
    }
}
