<?php

namespace App\Http\Controllers\PC\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\NotificationRequest;
use App\Services\UserNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class UserNotificationController extends Controller
{
    protected $userNotificationService;

    public function __construct(UserNotificationService $userNotificationService)
    {
        $this->userNotificationService = $userNotificationService;
    }
    /**
     * update status notification by notice_id
     *
     * @param Request $request
     * @return
     */
    public function userReadNotification(NotificationRequest $request)
    {
        $currentUser = Auth::user();
        $result = $this->userNotificationService->readNotification($request->input('notice_id'), $currentUser->user_id);
        return $result;
    }
    /**
     * get all notification by user
     *
     * @param
     * @return
     */
    public function getUserNotification()
    {
        $currentUser = Auth::user();
        $result = $this->userNotificationService->getListUserNotification($currentUser->user_id);
        // format datetime
        if ($result['status'] == config('apps.general.success')) {
            if ($result['data']['notifications']) {
                foreach ($result['data']['notifications'] as $notice) {
                    $notice->notice_start_datetime = coverDateTimeToTimezoneLocal($notice->notice_start_datetime);
                }
            }
        }
        return $result;
    }
    /**
     * update all status notification
     *
     * @param
     * @return
     */
    public function changeFlagAllUserNotification()
    {
        $currentUser = Auth::user();
        $result = $this->userNotificationService->updateReadFlagAllNotification($currentUser->user_id);
        return $result;
    }

    /**
     * delete notification
     *
     * @param  NotificationRequest $request
     * @return bool
     */
    public function deleteUserNotification(NotificationRequest $request)
    {
        $currentUser = Auth::user();
        $result = $this->userNotificationService->deleteUserNotification($request->notice_id, $currentUser->user_id);
        return $result;
    }
}
