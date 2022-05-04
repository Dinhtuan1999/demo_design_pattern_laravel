<?php

namespace App\Repositories;

use App\Models\Notification;
use Carbon\Carbon;

class NotificationRepository extends Repository
{
    private $breakdownRepos;
    private $taskRepos;

    public function __construct(
        BreakdownRepository $breakdownRepos,
        TaskRepository $taskRepos
    ) {
        $this->breakdownRepos = $breakdownRepos;
        $this->taskRepos = $taskRepos;
        parent::__construct(Notification::class);

        $this->fields = $this->getInstance()->getFillable();
    }

    public function formatAllRecord($records)
    {
        if (!empty($records)) {
            foreach ($records as &$record) {
                $record = $this->formatRecord($record);
            }
        }
        return $records;
    }

    public function formatRecord($record)
    {
        return $record;
    }

    public function getListUserNotification($userId)
    {
        $model = $this->getModel();
        $notifications = $model::avaiable()
            ->withWhereHas('user_notification', function ($query) use ($userId) {
                $query->where('user_id', $userId);
                $query->where('delete_flg', config('apps.general.not_deleted'));
            })
            ->join('m_notice_kinds', 't_notification.notice_kinds_id', '=', 'm_notice_kinds.notice_kinds_id')
            ->select('t_notification.*', 'm_notice_kinds.notice_kinds_class_code');
        $checkNoticeUnRead = $this->checkNoticeUnRead($notifications) ? true : false;
        $notifications = $notifications->orderBy('notice_start_datetime', 'DESC');
        $notifications = $notifications->paginate(config('apps.general.notices.per_page'));
        $notifications->getCollection()->transform(function ($item) {
            $item['user_icon_image_path'] = null;
            if (in_array($item->notice_kinds_class_code, config('apps.notification.NOTICE_KINDS_CLASS_CODE.BREAKDOWN_NOTICE'), false)) {
                $breakdown = $this->breakdownRepos->getByCol('breakdown_id', $item->link_id, ['task', 'user']);
                $item['breakdown_project_id'] = !is_null($breakdown->task) ? $breakdown->task->project_id : null;
                $item['user_icon_image_path'] = !is_null($breakdown->reportee_user_id)
                    ? $breakdown->user->getIconImageAttribute() : null;
            } elseif (in_array($item->notice_kinds_class_code, config('apps.notification.NOTICE_KINDS_CLASS_CODE.TASK_NOTICE'), false)) {
                $task = $this->taskRepos->getByCol('task_id', $item->link_id, ['user']);
                $item['user_icon_image_path'] = !is_null($task->user_id)
                    ? $task->user->getIconImageAttribute() : null;
            }

            return $item;
        });
        $data = ["checkNoticeUnRead" => $checkNoticeUnRead, "notifications" => $notifications];
        return $data;
    }
    // check to see if there are any unread messages?
    public function checkNoticeUnRead($notifications)
    {
        $check = false;
        $notifications = $notifications->get();
        foreach ($notifications as $notification) {
            if (!empty($notification->user_notification) && $notification->user_notification->read_flag == config('apps.general.not_read')) {
                $check = true;
                return $check;
            }
        }
        return $check;
    }
}
