<?php

namespace App\Services;

use App\Repositories\BreakdownRepository;
use App\Repositories\TaskRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Services\BaseService;
use Illuminate\Validation\Rule;

class BreakdownService extends BaseService
{
    protected $taskRepository;
    protected $breakdownRepository;

    public function __construct(
        TaskRepository $taskRepository,
        BreakdownRepository $breakdownRepository
    ) {
        $this->taskRepository = $taskRepository;
        $this->breakdownRepository = $breakdownRepository;
    }

    /**
     * Get breakdown
     *
     * @param $projectId
     * @param $manager
     * @param $userId
     * @return array
     */
    public function getBreakdownByManager($projectId, $manager, $userId, $paginate = true)
    {
        $response = $this->initResponse();

        try {
            $response['data'] = $this->taskRepository->getBreakdownByManager($projectId, $manager, $userId, $paginate);
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
     * Check breakdown
     *
     * @param $breakdownId
     * @return array
     */
    public function checkRecord($breakdownId)
    {
        $response = [
            'status'        => config('apps.general.success'),
            'message'       => [trans('message.SUCCESS')],
        ];

        try {
            $record   = $this->breakdownRepository->getByCols(
                [
                    'breakdown_id'  => $breakdownId,
                    'delete_flg'    => config('apps.general.not_deleted')
                ]
            );

            if (!$record) {
                $response['status'] = config('apps.general.error');
                $response['message'] = [trans(
                    'message.ERR_COM_0011',
                    ['attribute' => trans('validation_attribute.t_breakdown')]
                )];
                $response['error_code'] = config('apps.general.error_code');
            } else {
                $response['data'] = $record;
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response = $this->exceptionError();
        }

        return $response;
    }

    /**
     * Update breakdown progress
     *
     * @param $progress
     * @param $breakdown
     * @param $userId
     * @return array
     */
    public function updateBreakdownProgress($progress, $breakdown, $userId)
    {
        $response = $this->initResponse();
        try {
            if ($breakdown->progress == $progress) {
                $breakdown->progress = null;
            } else {
                $breakdown->progress = $progress;
            }

            $breakdown->update_datetime = Carbon::now();
            $breakdown->update_user_id = $userId;

            $breakdown->save();
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response = $this->exceptionError();
        }
        return $response;
    }

    /**
     * Create or update breakdown
     *
     * @param $taskId
     * @param $workItem
     * @param $data
     * @param $userId
     * @return array
     */
    public function createOrUpdateBreakdown($taskId, $workItem, $data, $userId)
    {
        $response = [
            'status'        => config('apps.general.success'),
            'data'          => null,
            'message'       => [trans('message.SUCCESS')],
            'message_id'    => ['SUCCESS']
        ];

        try {
            if ($data['breakdown'] == null) {
                $model = $this->breakdownRepository->getInstance();

                $model->breakdown_id = AppService::generateUUID();
                $model->task_id = $taskId;
                $model->create_datetime = Carbon::now();
                $model->create_user_id = $userId;
            } else {
                $model = $data['breakdown'];
                $model->update_datetime = Carbon::now();
                $model->update_user_id = $userId;
            }

            $model->work_item = $workItem;

            if ($data['plan_date'] != null) {
                $model->plan_date = $data['plan_date'];
            }
            if ($data['progress'] != null) {
                $model->progress = $data['progress'];
            }
            if ($data['comment'] != null) {
                $model->comment = $data['comment'];
            }
            if ($data['reportee_user_id'] != null) {
                $model->reportee_user_id = $data['reportee_user_id'];
            }

            $model->save();

            $response['data'] = $model;
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response = $this->exceptionError();
        }

        return $response;
    }

    /**
     * Delete breakdown
     *
     * @param $breakdown
     * @param $userId
     * @return array
     */
    public function deleteBreakdown($breakdown, $userId)
    {
        $response = [
            'status'        => config('apps.general.success'),
            'data'          => null,
            'message'       => [trans('message.SUCCESS')],
            'message_id'    => ['SUCCESS']
        ];

        try {
            $model = $breakdown;
            $model->delete_flg = config('apps.general.is_deleted');
            $model->update_datetime = Carbon::now();
            $model->update_user_id = $userId;

            $model->save();

            $response['data'] = $model;
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response = $this->exceptionError();
        }

        return $response;
    }

    public function updateReporteeUserId($request, $userId)
    {
        $response = $this->initResponse();
        try {
            $this->breakdownRepository->updateReporteeUserIds($request, $userId);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $response = $this->exceptionError();
        }
        return $response;
    }
}
