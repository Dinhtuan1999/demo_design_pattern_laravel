<?php

namespace App\Services;

use App\Repositories\ProjectNotificationRepository;
use App\Repositories\ProjectRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Services\BaseService;

class ProjectNotificationService extends BaseService
{
    protected $projectNotificationRepository;
    protected $projectRepository;

    public function __construct(ProjectNotificationRepository $projectNotificationRepository, ProjectRepository $projectRepository)
    {
        $this->projectNotificationRepository = $projectNotificationRepository;
        $this->projectRepository = $projectRepository;
    }

    /**
     * Delete project notification
     *
     * @param $userId
     * @param $projectNotification
     * @return array
     */
    public function deleteProjectNotification($userId, $projectNotification)
    {
        $response = $this->initResponse();

        try {
            $projectNotification->delete_flg = config('apps.general.is_deleted');
            $projectNotification->update_user_id = $userId;
            $projectNotification->update_datetime = Carbon::now();
            $projectNotification->save();

            $response['data'] = $projectNotification;
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response = $this->exceptionError();
        }
        return $response;
    }

    /**
     * Get list project notification
     * @param $projectId
     * @return array
     */
    public function getProjectNotification($projectId, $paginate = true)
    {
        $response = $this->initResponse();

        try {
            $response['data'] = $this->projectNotificationRepository->getListProjectNotification($projectId, $paginate);
            if ($paginate) {
                $response['last_page'] = $response['data']->lastPage();
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response = $this->exceptionError();
        }

        return $response;
    }

    /**
     * Create project notification
     *
     * @param $userId
     * @param $projectId
     * @param $projectNoticeMessage
     * @param $messageNoticeStartDate
     * @param $messageNoticeEndDate
     * @return array
     */
    public function creteProjectNotification(
        $userId,
        $projectId,
        $projectNoticeMessage,
        $messageNoticeStartDate,
        $messageNoticeEndDate
    ) {
        $response = $this->initResponse();

        try {
            $model = $this->projectNotificationRepository->getInstance();

            $model->project_notice_id = AppService::generateUUID();
            $model->project_id = $projectId;
            $model->project_notice_message = $projectNoticeMessage;
            $model->message_notice_start_date = $messageNoticeStartDate;
            $model->message_notice_end_date = $messageNoticeEndDate;

            $model->create_datetime = Carbon::now();
            $model->create_user_id = $userId;

            $model->save();
            $response['data'] = $model;
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response = $this->exceptionError();
        }

        return $response;
    }

    /**
     * S.E020.1 Get All Notification of Project In Progress by company_id
     *
     * @param $companyId
     * @return array
     */
    public function getListNotificationProjectInProgress($companyId)
    {
        $response = $this->initResponse();

        try {
            $response['data'] = $this->projectNotificationRepository->getListNotificationProjectInProgress($companyId);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response = $this->exceptionError();
        }

        return $response;
    }

    /**
     * Check project notification
     *
     * @param $projectNoticeId
     * @return array
     */
    public function checkRecord($projectNoticeId)
    {
        $response = $this->initResponse();

        try {
            $projectNotification = $this->projectNotificationRepository->getByCols(
                [
                    'project_notice_id' => $projectNoticeId,
                    'delete_flg' => config('apps.general.not_deleted')
                ]
            );

            if (!$projectNotification) {
                $response['status'] = config('apps.general.error');
                $response['message'] = [trans(
                    'message.ERR_COM_0011',
                    ['attribute' => trans('validation_attribute.t_project_notification')]
                )
                ];
                $response['message_id'] = ['ERR_COM_0011'];
                $response['error_code'] = config('apps.general.error_code');
            } else {
                $response['data'] = $projectNotification;
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response = $this->exceptionError();
        }

        return $response;
    }

    /**
     * Edit project notification
     *
     * @param $userId
     * @param $record
     * @param $projectId
     * @param $projectNoticeMessage
     * @param $messageNoticeStartDate
     * @param $messageNoticeEndDate
     * @return array
     */
    public function editProjectNotification(
        $userId,
        $record,
        $projectId,
        $projectNoticeMessage,
        $messageNoticeStartDate,
        $messageNoticeEndDate
    ) {
        $response = $this->initResponse();

        try {
            $record->project_id = $projectId;
            $record->project_notice_message = $projectNoticeMessage;
            $record->message_notice_start_date = $messageNoticeStartDate;
            $record->message_notice_end_date = $messageNoticeEndDate;

            $record->update_datetime = Carbon::now();
            $record->update_user_id = $userId;

            $record->save();
            $response['data'] = $record;
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $response = $this->exceptionError();
        }
        return $response;
    }

    /**
     * Add Projects To Notification
     * TODO: S.E020.2
     * 2022-02-21
     *
     * @param $companyId
     * @param $userId
     * @param $projectNoticeMessage
     * @param $messageNoticeStartDate
     * @param $messageNoticeEndDate
     * @return array
     */
    public function addProjectToNotification(
        $companyId,
        $userId,
        $projectNoticeMessage,
        $messageNoticeStartDate,
        $messageNoticeEndDate
    ) {
        $response = $this->initResponse();

        try {
            $listProjectByCompanyId = $this->projectRepository->getListProjectsByCompanyId($companyId);

            // Not found project by company_id
            if (sizeof($listProjectByCompanyId) == 0) {
                $response['status'] = config('apps.general.error');
                $response['message'] = [trans('message.INF_COM_0003')];
                $response['message_id'] = ['INF_COM_0003'];
                $response['error_code'] = config('apps.general.error_code');
                return $response;
            }

            // Add notification project
            $dataProjectNotifications = [];
            foreach ($listProjectByCompanyId as $project) {
                $dataProjectNotifications[] = [
                    "project_notice_id" => AppService::generateUUID(),
                    "project_id" => $project->project_id,
                    "project_notice_message" => $projectNoticeMessage,
                    "message_notice_start_date" => $messageNoticeStartDate,
                    "message_notice_end_date" => $messageNoticeEndDate,
                    "create_datetime" => Carbon::now(),
                    "create_user_id" => $userId,
                ];
            }
            $this->projectNotificationRepository->insertMultiRecord($dataProjectNotifications);

            return $response;
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response = $this->exceptionError();
        }

        return $response;
    }

    /**
     * S.E020.1 Get All Notification of Project In Progress by user id
     *
     * @param string $userId
     * @return array
     */
    public function getListNotificationProjectInProgressByUserId($userId): array
    {
        $response = $this->initResponse();

        try {
            $response['data'] = $this->projectNotificationRepository->getListNotificationProjectInProgressByUserId($userId);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $response = $this->exceptionError();
        }

        return $response;
    }
}
