<?php

namespace App\Services;

use App\Models\Task;
use App\Repositories\ProjectRepository;
use App\Repositories\TaskGroupRepository;
use App\Repositories\TaskRepository;
use App\Repositories\TaskStatusRepository;
use Illuminate\Support\Facades\Log;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

class TaskStatusService extends BaseService
{
    protected $taskStatusRepository;
    private $taskRepository;
    private $taskGroupRepo;
    private $projectRepo;
    public function __construct(TaskStatusRepository $taskStatusRepository, TaskRepository $taskRepository, TaskGroupRepository $taskGroupRepo, ProjectRepository $projectRepo)
    {
        $this->taskStatusRepository = $taskStatusRepository;
        $this->taskRepository = $taskRepository;
        $this->taskGroupRepo = $taskGroupRepo;
        $this->projectRepo = $projectRepo;
    }

    public function getListTaskStatus()
    {
        $response = [
            'status'        => config('apps.general.success'),
            'data'          => null,
            'message'       => [],
            'message_id'    => []
        ];

        try {
            $response['data'] = $this->taskStatusRepository->all(
                [],
                ['by' => 'display_order', 'type' => 'ASC'],
                [],
                ['task_status_id','task_status_name','display_order']
            );
            $response['message']    = [trans('message.SUCCESS')];
            $response['message_id'] = ['SUCCESS'];
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response['status']     = config('apps.general.error');
            $response['message'] = [trans('message.ERR_EXCEPTION') ];
            $response['message_id'] = ['ERR_EXCEPTION'];
            $response['error_code'] = config('apps.general.error_code');
        }

        return $response;
    }

    public function copyTask($currentUserId, $taskId, $statusDataCopy)
    {
        $taskOld = $this->taskRepository->getById($taskId);

        if (!$taskOld) {
            return $this->sendError([trans('message.NOT_COMPLETE')]);
        }
        if ($taskOld->delete_flg == config('apps.general.is_deleted')) {
            return $this->sendError([trans('message.ERR_COM_0011', ['attribute' => $taskOld->task_name])]);
        }

        if (!$this->taskGroupRepo->isExists($statusDataCopy['task_group_id']) || !$this->projectRepo->isExists($statusDataCopy['project_id'])) {
            return $this->sendError([trans('message.NOT_COMPLETE')]);
        }
        try {
            DB::beginTransaction();
            $taskNew = $this->taskRepository->getInstance();
            $taskNew->task_id = AppService::generateUUID();
            $taskNew->user_id = $statusDataCopy['user_id'];
            $taskNew->task_name = $statusDataCopy['task_name'];
            $taskNew->task_group_id = $statusDataCopy['task_group_id'];
            $taskNew->task_status_id = config('apps.task.status_key.not_started');
            $taskNew->create_user_id = $currentUserId;
            $taskNew->project_id = $statusDataCopy['project_id'];
            $taskNew->update_user_id = $currentUserId;

            if (isset($statusDataCopy['task_memo']) && $statusDataCopy['task_memo'] == config('apps.general.check_box')) {
                $taskNew->task_memo = $taskOld->task_memo;
                ;
            }
            if (isset($statusDataCopy['priority_id']) && $statusDataCopy['priority_id'] == config('apps.general.check_box')) {
                $taskNew->priority_id = $taskOld->priority_id;
                ;
            }
            if (isset($statusDataCopy['disclosure_range_id']) && $statusDataCopy['disclosure_range_id'] == config('apps.general.check_box')) {
                $taskNew->disclosure_range_id = $taskOld->disclosure_range_id;
                ;
            }
            if (isset($statusDataCopy['scheduled_date']) && $statusDataCopy['scheduled_date'] == config('apps.general.check_box')) {
                $taskNew->start_plan_date = $taskOld->start_plan_date;
                $taskNew->end_plan_date = $taskOld->end_plan_date;
            }
            $taskNew->save();

            if (isset($statusDataCopy['check_list']) && $statusDataCopy['check_list'] == config('apps.general.check_box')) {
                $dataCheckList = $taskOld->check_lists->mapWithKeys(function ($task, $index) {
                    return [
                        $index => [
                            'check_list_id' => AppService::generateUUID(),
                            'check_name' => $task->check_name,
                            'complete_flg' => $task->complete_flg,
                        ]
                    ];
                });
                if (isset($taskNew)) {
                    $CheckListNew = $taskNew->check_lists()->createMany($dataCheckList);
                }
            }

            if (isset($statusDataCopy['attachment_file']) && $statusDataCopy['attachment_file'] == config('apps.general.check_box')) {
                $dataAttachmentFile = $taskOld->attachment_files->mapWithKeys(function ($task, $index) {
                    return [
                        $index => [
                            'attachment_file_id' => AppService::generateUUID(),
                            'attachment_file_name' => $task->attachment_file_name,
                            'attachment_file_path' => $task->attachment_file_path,
                            'file_size' => $task->file_size,
                        ]
                    ];
                });
                if (isset($taskNew)) {
                    $AttachmentNew = $taskNew->attachment_files()->createMany($dataAttachmentFile);
                }
            }

            //add data sub task
            if (isset($statusDataCopy['sub_task']) && $statusDataCopy['sub_task'] == config('apps.general.check_box')) {
                $dataSubTask = [
                    'task_id' => AppService::generateUUID(),
                    'task_group_id' => $taskNew->task_group_id,
                    'project_id' => $taskNew->project_id,
                    'task_name' => $taskNew->task_name,
                    'task_memo' => $taskNew->task_memo,
                    'disclosure_range_id' => $taskNew->disclosure_range_id,
                    'priority_id' => $taskNew->priority_id,
                    'task_status_id' => $taskNew->task_status_id,
                    'start_plan_date' => $taskNew->start_plan_date,
                    'end_plan_date' => $taskNew->end_plan_date,
                    'create_user_id' => $taskNew->create_user_id,
                    'update_user_id' => $taskNew->update_user_id,
                ];
                $newSubTaskId = $taskNew->sub_tasks()->create($dataSubTask);
                $newSubTaskId = $newSubTaskId->fresh();

                if (!empty($taskOld->check_lists->toArray())) {
                    $dataCheckList = $taskOld->check_lists->mapWithKeys(function ($task, $index) {
                        return [
                            $index => [
                                'check_list_id' => AppService::generateUUID(),
                                'check_name' => $task->check_name,
                                'complete_flg' => $task->complete_flg,
                            ]
                        ];
                    });
                    if (isset($newSubTaskId)) {
                        $newSubTaskId->check_lists()->createMany($dataCheckList);
                    }
                }
                if (!empty($taskOld->attachment_files->toArray())) {
                    $dataAttachmentFile = $taskOld->attachment_files->mapWithKeys(function ($task, $index) {
                        return [
                            $index => [
                                'attachment_file_id' => AppService::generateUUID(),
                                'attachment_file_name' => $task->attachment_file_name,
                                'attachment_file_path' => $task->attachment_file_path,
                                'file_size' => $task->file_size,
                            ]
                        ];
                    });
                    if (isset($newSubTaskId)) {
                        $newSubTaskId->attachment_files()->createMany($dataAttachmentFile);
                    }
                }
            }
            DB::commit();

            $task = $this->taskRepository->getByCol('task_id', $taskNew->task_id, Task::TASK_RELATION);
            $task = $this->taskRepository->formatRecord($task);
            $task = $task->toArray();
            $task = $this->taskRepository->detailTask($task, $currentUserId, false);
            return $task;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return $this->sendError(trans('message.NOT_COMPLETE'));
        }
    }
}
