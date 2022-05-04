<?php

namespace App\Repositories;

use App\Models\ProjectNotification;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectNotificationRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(ProjectNotification::class);
        $this->fields = ProjectNotification::FIELDS;
    }

    /**
     * Get list project notification
     * @param $projectId
     * @return mixed
     */
    public function getListProjectNotification($projectId, $paginate = true)
    {
        $model = $this->getModel();

        $model = $model::where('project_id', $projectId);
        $model = $model->where('delete_flg', config('apps.general.not_deleted'));
        $model = $model->orderBy('create_datetime', 'DESC');
        if ($paginate) {
            $model = $model->paginate(config('apps.notification.record_per_page'));
        } else {
            $model = $model->get();
        }

        return $model;
    }

    /**
     * S.E020.1 Get All Notification of Project In Progress by company_id
     *
     * @param string $companyId
     * @return paginate
     */
    public function getListNotificationProjectInProgress($companyId)
    {
        $currentDate = Carbon::now()->toDateString();
        $model = $this->getModel();

        $model = $model::join('t_project', 't_project.project_id', '=', 't_project_notification.project_id')
            ->where('t_project.company_id', $companyId)
            ->where('t_project.project_status', config('apps.project.status_key.in_progress'))
            ->where('t_project_notification.message_notice_start_date', '<=', $currentDate)
            ->where('t_project_notification.message_notice_end_date', '>=', $currentDate)
            ->where('t_project.delete_flg', config('apps.general.not_deleted'))
            ->select(
                't_project.project_id',
                't_project_notification.project_notice_id',
                't_project.project_name',
                't_project_notification.project_notice_message'
            )
            ->orderBy('t_project.project_name', 'ASC')
            ->paginate(config('apps.notification.record_per_page'));

        return $model;
    }

    /**
     * S.E020.1 Get All Notification of Project In Progress by user_id
     *
     * @param string $userId
     * @return paginate
     */
    public function getListNotificationProjectInProgressByUserId($userId)
    {
        $currentDate = Carbon::now()->toDateString();
        $model = $this->getModel();

        return $model::join('t_project', 't_project.project_id', '=', 't_project_notification.project_id')
            ->join('t_project_participant', 't_project.project_id', '=', 't_project_participant.project_id')
            ->where('t_project_participant.user_id', $userId)
            ->where('t_project.project_status', config('apps.project.status_key.in_progress'))
            ->where('t_project_notification.message_notice_start_date', '<=', $currentDate)
            ->where('t_project_notification.message_notice_end_date', '>=', $currentDate)
            ->where('t_project.delete_flg', config('apps.general.not_deleted'))
            ->where('t_project_notification.delete_flg', config('apps.general.not_deleted'))
            ->where('t_project_participant.delete_flg', config('apps.general.not_deleted'))
            ->select(
                't_project.project_id',
                't_project_notification.project_notice_id',
                't_project.project_name',
                't_project_notification.project_notice_message'
            )
            ->orderBy('t_project.project_name', 'ASC')
            ->paginate(config('apps.notification.record_per_page'));
    }
}
