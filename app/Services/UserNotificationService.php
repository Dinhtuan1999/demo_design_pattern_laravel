<?php

namespace App\Services;

use App\Repositories\NotificationRepository;
use App\Repositories\UserNotificationRepository;
use Illuminate\Support\Facades\Log;

class UserNotificationService extends BaseService
{
    protected $notificationRepository;
    protected $userNotificationRepository;

    public function __construct(NotificationRepository $notificationRepository, UserNotificationRepository $userNotificationRepository)
    {
        $this->notificationRepository   = $notificationRepository;
        $this->userNotificationRepository  = $userNotificationRepository;
    }

    public function readNotification($noticeId, $userId)
    {
        return $this->updateReadFlagNotification($noticeId, $userId, config('apps.notification.read'));
    }

    public function updateReadFlagNotification($noticeId, $userId, $readFlag)
    {
        $response = [
            'status'        => config('apps.general.success'),
            'data'          => null,
            'message'       => [],
            'message_id'    => []
        ];

        try {
            $notification   = $this->notificationRepository->getByCol('notice_id', $noticeId);
            if (!$notification) {
                $response['status']     = config('apps.general.error');
                $response['message']    = [trans('message.ERR_COM_0011', ['attribute' => 't_notification'])];
                $response['message_id'] = ['ERR_COM_0011'];
                $response['error_code'] = config('apps.general.error_code');
                return $response;
            }

            $userNotification = $this->userNotificationRepository->getByCols(['notice_id' => $noticeId, 'user_id' => $userId]);
            if (!$userNotification) {
                $response['status']     = config('apps.general.error');
                $response['message']    = [trans('message.ERR_COM_0011', ['attribute' => 't_user_notification'])];
                $response['message_id'] = ['ERR_COM_0011'];
                $response['error_code'] = config('apps.general.error_code');
                return $response;
            }

            $userNotification->read_flag = $readFlag;
            $userNotification->update_user_id = $userId;
            $userNotification->save();

            $response['message']    = [trans('message.SUCCESS')];
            $response['message_id'] = ['SUCCESS'];
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response['status']     = config('apps.general.error');
            $response['message']    = [trans('message.INF_COM_0010')];
            $response['message_id'] = ['INF_COM_0010'];
            $response['error_code'] = config('apps.general.error_code');
        }

        return $response;
    }

    public function getListUserNotification($userId)
    {
        $response = [
            'status'        => config('apps.general.success'),
            'data'          => null,
            'message'       => [],
            'message_id'    => []
        ];

        try {
            $response['data'] = $this->notificationRepository->getListUserNotification($userId);
            $response['message']    = [trans('message.SUCCESS')];
            $response['message_id'] = ['SUCCESS'];
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response['status']     = config('apps.general.error');
            $response['message'] = [trans('message.ERR_EXCEPTION')];
            $response['message_id'] = ['ERR_EXCEPTION'];
            $response['error_code'] = config('apps.general.error_code');
        }

        return $response;
    }
    public function deleteUserNotification($noticeId, $userId)
    {
        try {
            $notification   = $this->notificationRepository->getByCol('notice_id', $noticeId);
            if (!$notification) {
                return $this->sendError([
                    trans('message.ERR_COM_0011', ['attribute' => 't_notification'])
                ]);
            }
            $notification->delete_flg = config('apps.general.is_deleted');
            $notification->save();
            $userNotification = $this->userNotificationRepository->getByCols(['notice_id' => $noticeId, 'user_id' => $userId]);
            if (!$userNotification) {
                return $this->sendError([
                    trans('message.ERR_COM_0011', ['attribute' => 't_user_notification'])
                ]);
            }
            $userNotification->delete_flg = config('apps.general.is_deleted');
            $userNotification->save();
            return $this->sendResponse([
                [trans('message.SUCCESS')]
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError([
                [trans('message.ERR_EXCEPTION')]
            ]);
        }
    }
    public function updateReadFlagAllNotification($userId)
    {
        try {
            $userNotification   = $this->userNotificationRepository->getByCol('user_id', $userId);
            if (!$userNotification) {
                return $this->sendError([
                    trans('message.ERR_COM_0011', ['attribute' => 't_user_notification'])
                ]);
            }
            // update all notification
            $userNotification = $this->userNotificationRepository->getModel()::where(['user_id' => $userId])->update(['read_flag' => config('apps.notification.read'), 'update_user_id' => $userId]);
            return $this->sendResponse([
                [trans('message.SUCCESS')]
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError([
                [trans('message.ERR_EXCEPTION')]
            ]);
        }
    }
}
