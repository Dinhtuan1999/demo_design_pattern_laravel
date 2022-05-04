<?php

namespace App\Services;

use App\Helpers\Transformer;
use App\Models\AttachmentFile;
use App\Models\Task;
use App\Repositories\TaskRepository;
use App\Transformers\Task\TaskDetailTransformer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Services\BaseService;
use App\Repositories\RemindRepository;
use App\Repositories\CheckListRepository;
use App\Repositories\AttachmentFileRepository;
use App\Http\Resources\TaskResource;
use App\Repositories\TaskGroupRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\BreakdownRepository;
use App\Repositories\TaskStatusRepository;
use App\Repositories\UserRepository;

class TaskService extends BaseService
{
    private $taskRepository;
    private $remindRepo;
    private $checkListRepo;
    private $attachmentFileRepo;
    private $taskGroupRepo;
    private $projectRepo;
    private $breakdownRepo;
    private $userRepo;
    private $taskStatusRepo;

    public function __construct(
        TaskRepository           $taskRepository,
        RemindRepository         $remindRepo,
        CheckListRepository      $checkListRepo,
        AttachmentFileRepository $attachmentFileRepo,
        TaskGroupRepository      $taskGroupRepo,
        ProjectRepository        $projectRepo,
        BreakdownRepository      $breakdownRepo,
        UserRepository           $userRepo,
        TaskStatusRepository     $taskStatusRepo
    ) {
        $this->taskRepository = $taskRepository;
        $this->remindRepo = $remindRepo;
        $this->checkListRepo = $checkListRepo;
        $this->attachmentFileRepo = $attachmentFileRepo;
        $this->taskGroupRepo = $taskGroupRepo;
        $this->projectRepo = $projectRepo;
        $this->breakdownRepo = $breakdownRepo;
        $this->userRepo = $userRepo;
        $this->taskStatusRepo = $taskStatusRepo;
    }

    /**
     * Switch status of task, complete or no.
     * By update end_date to switch status of task.
     *
     * @param string $id
     * @param string $userId
     * @param boolean $completeSubtask
     * @return array
     */
    public function updateCompleteTask($task, $userId, $completeSubtask = false)
    {
        $response = $this->initResponse();
        $parentId = null;

        try {
            DB::beginTransaction();

            $now = date('Y-m-d');
            switch ($task->task_status_id) {
                case config('apps.task.status_key.delay_start'):
                case config('apps.task.status_key.not_started'):
                    $task->start_date = $now;
                    $task->end_date = $now;
                    $task->task_status_id = config('apps.task.status_key.complete');
                    break;
                case config('apps.task.status_key.in_progress'):
                case config('apps.task.status_key.delay_complete'):
                    $task->end_date = $now;
                    $task->task_status_id = config('apps.task.status_key.complete');
                    break;
                case config('apps.task.status_key.complete'):
                    $task->end_date = null;
                    if (!is_null($task->end_plan_date) && $task->end_plan_date < $now) {
                        $task->task_status_id = config('apps.task.status_key.delay_complete');
                    } else {
                        $task->task_status_id = config('apps.task.status_key.in_progress');
                    }
                    break;
            }
            $task->update_user_id = $userId;
            if ($task->parent_task_id) {
                $parentId = $task->parent_task_id;
            }
            $task->save();

            // check & update subtask complete
            if ($completeSubtask && $task->task_status_id == config('apps.task.status_key.complete')) {
                $this->updateSubTaskComplete($task->task_id, true);
                $this->updateSubTaskComplete($task->task_id, false);
            }

            DB::commit();

            $task = $this->taskRepository->getByCol('task_id', $task->task_id, Task::TASK_RELATION);
            $task = $this->taskRepository->formatRecord($task);
            $subTasks = $task->sub_tasks;
            $task = $task->toArray();
            $task = $this->taskRepository->detailTask($task, $userId);

            $parentTask = null;
            if ($parentId != null) {
                $parentTask = $this->taskRepository->getSimpleTaskById($parentId);
            }

            $response['parent'] = $parentTask;
            $response['data'] = $task;
            $response['data']['sub_tasks'] = Transformer::collection(new TaskDetailTransformer(), $subTasks)['data'];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            $response = $this->exceptionError();
        }

        return $response;
    }

    /**
     * Update sub task complete
     *
     * @param  mixed $parentTaskId
     * @param  mixed $isNullStartDate
     * @return void
     */
    public function updateSubTaskComplete($parentTaskId, $isNullStartDate)
    {
        $now = Carbon::now();
        $query = $this->taskRepository->getModel()::where('parent_task_id', $parentTaskId)
            ->where('task_status_id', '<>', config('apps.task.status_key.complete'))
            ->where('delete_flg', config('apps.general.not_deleted'));
        if ($isNullStartDate) {
            $query->whereNull('start_date')
            ->update([
                'task_status_id' => config('apps.task.status_key.complete'),
                'start_date' => $now,
                'end_date' => $now
            ]);
        } else {
            $query->whereNotNull('start_date')
            ->update([
                'task_status_id' => config('apps.task.status_key.complete'),
                'end_date' => $now
            ]);
        }
    }


    public function updateStartTask($id, $userId)
    {
        $response = [
            'status' => config('apps.general.success'),
            'data' => null,
            'message' => []
        ];

        try {
            $task = $this->taskRepository->getByCol('task_id', $id);
            if (!$task) {
                return $this->sendError([trans('message.ERR_COM_0011', ['attribute' => trans('label.task.task')])]);
            }
            if ($task->delete_flg == config('apps.general.is_deleted')) {
                return $this->sendError([trans('message.ERR_COM_0011', ['attribute' => $task->task_name])]);
            }
            $currentDate = date('Y-m-d');
            switch ($task->task_status_id) {
                case config('apps.task.status_key.delay_start'):
                case config('apps.task.status_key.not_started'):
                    $task->start_date = Carbon::now();
                    $task->task_status_id = config('apps.task.status_key.in_progress');
                    $response['message'] = [trans('message.START')];
                    break;
                case config('apps.task.status_key.in_progress'):
                case config('apps.task.status_key.delay_complete'):
                    if (!is_null($task->start_plan_date) && $currentDate > $task->start_plan_date) {
                        $task->task_status_id = config('apps.task.status_key.delay_start');
                    } else {
                        $task->task_status_id = config('apps.task.status_key.not_started');
                    }
                    $task->start_date = null;
                    $task->end_date = null;
                    $response['message'] = [trans('message.NOT_START')];
                    break;
                case config('apps.task.status_key.complete'):
                    $task->task_status_id = config('apps.task.status_key.not_started');
                    $task->start_date = null;
                    $task->end_date = null;
                    $response['message'] = [trans('message.NOT_START')];
                    break;
            }

            $task->update_user_id = $userId;
            $result = $task->save();
            if ($result === 0) {
                return $this->sendError([trans('message.ERR_COM_0009')]);
            }
            $task = $this->taskRepository->getByCol('task_id', $task->task_id, Task::TASK_RELATION);
            $task = $this->taskRepository->formatRecord($task);
            $subTasks = $task->sub_tasks;
            $task = $task->toArray();
            $task = $this->taskRepository->detailTask($task, $userId);
            $response['data'] = $task;
            $response['data']['sub_tasks'] = Transformer::collection(new TaskDetailTransformer(), $subTasks)['data'];
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError([trans('message.ERR_EXCEPTION')]);
        }


        return $response;
    }

    /**
     * Get detail task by id
     *
     * @param  string $id
     * @return mix
     */
    public function detail($id)
    {
        $task = $this->taskRepository->getByCol('task_id', $id, Task::TASK_RELATION);
        if (empty($task)) {
            return self::sendError([
                trans('message.ERR_COM_0011', ['attribute' => trans('label.task.task')])
            ]);
        }
        $task = $this->taskRepository->formatRecord($task);

        // format sub_tasks
        $task['sub_tasks']->transform(function ($sub_task) {
            return $this->taskRepository->formatRecord($sub_task);
        });

        $currentUser = auth()->user();
        $task = $this->taskRepository->detailTask($task, $currentUser->user_id, false);

        return self::sendResponse([trans('message.SUCCESS')], $task);
    }

    public function detailTask($id, $userId)
    {
        $task = $this->taskRepository->getByCol('task_id', $id, Task::TASK_RELATION);
        if (empty($task)) {
            return self::sendError([
                trans('message.ERR_COM_0011', ['attribute' => trans('label.task.task')])
            ]);
        }
        $task = $this->taskRepository->formatRecord($task);

        $task = $this->taskRepository->detailTask($task, $userId, false);

        return self::sendResponse([trans('message.SUCCESS')], $task);
    }

    public function getManagers($projectId)
    {
        $response = [
            'status' => config('apps.general.success'),
            'data' => null,
            'message' => [trans('message.SUCCESS')],
            'message_id' => ['SUCCESS']
        ];

        try {
            $response['data'] = $this->taskRepository->getManagers($projectId);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response['status'] = config('apps.general.error');
            $response['message'] = [trans('message.ERR_EXCEPTION')];
            $response['message_id'] = ['ERR_EXCEPTION'];
        }

        return $response;
    }

    public function getAuthors($projectId)
    {
        $response = [
            'status' => config('apps.general.success'),
            'message' => [trans('message.SUCCESS')],
        ];

        try {
            $response['data'] = $this->taskRepository->getAuthors($projectId);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response['status'] = config('apps.general.error');
            $response['message'] = [trans('message.ERR_EXCEPTION')];
        }

        return $response;
    }

    public function updateTaskDate($taskId, $startDate, $endDate, $userId)
    {
        $response = [
            'status' => config('apps.general.success'),
            'data' => null,
            'message' => [trans('message.SUCCESS')],
            'message_id' => ['SUCCESS']
        ];

        try {
            $task = $this->taskRepository->getByCol('task_id', $taskId);

            if (!$task) {
                $response['status'] = config('apps.general.error');
                $response['message'] = [trans('message.ERR_COM_0011', ['attribute' => 't_task'])];
                $response['message_id'] = ['ERR_COM_0011'];
                $response['error_code'] = config('apps.general.error_code');
                return $response;
            }

            $task->start_plan_date = $startDate;
            $task->end_plan_date = $endDate;
            $task->update_user_id = $userId;
            $task->update_datetime = Carbon::now();

            $task->save();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $response['status'] = config('apps.general.error');
            $response['message'] = [trans('message.INF_COM_0010')];
            $response['message_id'] = ['INF_COM_0010'];
            $response['error_code'] = config('apps.general.error_code');
        }

        return $response;
    }

    public function getTaskByProject($projectId, $filter)
    {
        $response = [
            'status' => config('apps.general.success'),
            'data' => null,
            'message' => [trans('message.SUCCESS')],
            'message_id' => ['SUCCESS']
        ];

        try {
            $response['data'] = $this->taskRepository->getTaskByProject($projectId, $filter);
            $response['last_page'] = $response['data']->lastPage();
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response['status'] = config('apps.general.error');
            $response['message'] = [trans('message.ERR_EXCEPTION')];
            $response['message_id'] = ['ERR_EXCEPTION'];
            $response['error_code'] = config('apps.general.error_code');
        }

        return $response;
    }

    public function fetchTaskByProject($projectId, $filter, $flagFilter)
    {
        $response = $this->initResponse();

        try {
            $data = $this->taskRepository->getTaskByProject($projectId, $filter);
            $response['data'] = $data;
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response = $this->exceptionError();
        }

        return $response;
    }

    private function refactorDataTask($data)
    {
        if (count($data) == 0) {
            return null;
        }

        $responseItems = [];
        $parents = [];

        foreach ($data as $item) {
            // current record is sub task
            if (!empty($item->parent_task_id)) {
                // check parent task contain current record or no ?
                if (!in_array($item->parent_task_id, $parents, true)) {

                    // Create parent task
                    $newItem = clone $item;
                    $newTask = $this->taskRepository->getTaskInfo($item->parent_task_id);

                    if (empty($newTask)) {
                        continue;
                    }

                    $newItem->task_id = $newTask->task_id;
                    $newItem->task_name = $newTask->task_name;
                    $newItem->sub_task_info = $newTask->sub_tasks_complete_count . '/' .$newTask->sub_tasks_count;
                    $newItem->check_list_info = $newTask->check_lists_complete_count . '/' .$newTask->check_lists_count;
                    $newItem->scheduled_period = formatShowDate($newTask->start_plan_date) . ' - '.formatShowDate($newTask->end_plan_date);
                    $newItem->achievement_period = formatShowDate($newTask->start_date) . ' - '.formatShowDate($newTask->end_date);
                    $newItem->manager = $newTask->user ? $newTask->user->disp_name : '';
                    $newItem->task_status_name = $newTask->task_status ? $newTask->task_status->task_status_name : '';
                    $newItem->priority_name = $newTask->priority_mst ? $newTask->priority_mst->priority_name : '' ;
                    $newItem->group_name = $newTask->task_group ? $newTask->task_group->group_name : '' ;
                    $newItem->parent_name = '';

                    $newItem->parent_task_id = null;

                    $newItem->level = 2;
                    $newItem->child = 1;
                    $newItem->object_clone = true;

                    $responseItems[] = $newItem;

                    $parents[] = $item->parent_task_id;
                }

                $item->level = 3;
                $item->child = 0;
                $item->object_clone = false;
            } else {
                $item->level = 2;
                $item->child = 0;
                $item->object_clone = false;
            }

            $task = $this->taskRepository->getTaskInfo($item->task_id);

            $item->task_id = $task->task_id;
            $item->task_name = $task->task_name;
            $item->sub_task_info = $task->sub_tasks_complete_count . '/' .$task->sub_tasks_count;
            $item->check_list_info = $task->check_lists_complete_count . '/' .$task->check_lists_count;
            $item->scheduled_period = formatShowDate($task->start_plan_date) . ' - '.formatShowDate($task->end_plan_date);
            $item->achievement_period = formatShowDate($task->start_date) . ' - '.formatShowDate($task->end_date);
            $item->manager = $task->user ? $task->user->disp_name : '';
            $item->task_status_name = $task->task_status ? $task->task_status->task_status_name : '';
            $item->priority_name = $task->priority_mst ? $task->priority_mst->priority_name : '' ;
            $item->group_name = $task->task_group ? $task->task_group->group_name : '' ;
            $item->parent_name = $task->task_parent ? $task->task_parent->task_name : '';

            $responseItems[] = $item;
        }

        return new Collection($responseItems);
    }

    public function create(Request $request, $currentUser)
    {
        try {
            DB::beginTransaction();

            $dataTask = [
                'task_id' => AppService::generateUUID(),
                'task_group_id' => $request->input('task_group_id') ?? null,
                'project_id' => $request->input('project_id') ?? null,
                'task_name' => $request->input('task_name') ?? null,
                'priority_id' => $request->input('priority_id') ?? null,
                'user_id' => $request->input('user_id') ?? null,
                'disclosure_range_id' => $request->input('disclosure_range_id') ?? null,
                'task_status_id' => $request->input('task_status_id') ?? config('apps.task.status_key.not_started'),
                'start_plan_date' => $request->input('start_plan_date') ?? null,
                'end_plan_date' => $request->input('end_plan_date') ?? null,
                'start_date' => $request->input('start_date') ?? null,
                'end_date' => $request->input('end_date') ?? null,
                'parent_task_id' => $request->input('parent_task_id') ?? null,
                'task_memo' => $request->input('task_memo') ?? null,
                'delete_flg' => config('apps.general.not_deleted'),
                'create_datetime' => date('Y-m-d H:i:s'),
                'update_datetime' => date('Y-m-d H:i:s'),
                'create_user_id' => $currentUser->user_id,
                'update_user_id' => $currentUser->user_id
            ];

            // create new task
            $newTask = $this->taskRepository->store($dataTask);
            $getNewTask = $this->taskRepository->getByCol('task_id', $dataTask['task_id']);
            if (!$newTask && !$getNewTask) {
                return self::sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
            }

            $newTaskId = $getNewTask->task_id;

            // create Remind
            $reminds = $request->input('remaind');
            if (!empty($reminds) && $newTaskId) {
                $this->createRemind($newTaskId, $reminds, $currentUser);
            }

            // create check List
            $checkLists = $request->input('check_list');
            if (!empty($checkLists) && $newTaskId) {
                $this->createCheckList($newTaskId, $checkLists, $currentUser);
            }

            // upload attachment file
            $attachmentFiles = $request->attachment_file;
            if (!empty($attachmentFiles) && $newTaskId) {
                $this->createAttachmentFiles($newTaskId, $attachmentFiles, $currentUser);
            }

            // create breakdown
            $breakdowns = $request->input('breakdown');
            if (!empty($breakdowns) && $newTaskId) {
                $this->createBreakdown($newTaskId, $breakdowns, $currentUser);
            }

            DB::commit();

            $taskDetail = $this->detail($newTaskId);

            return $taskDetail;
        } catch (\Exception $exception) {
            DB::rollBack();
            return self::sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
        }
    }

    public function createRemind($newTaskId, $reminds, $currentUser)
    {
        $dataRemind = [];
        foreach ($reminds as $remind) {
            $recordRemind = [];
            $recordRemind['remaind_id'] = AppService::generateUUID();
            $recordRemind['task_id'] = $newTaskId;
            $recordRemind['remaind_datetime'] = $remind['remaind_datetime'] ?? null;
            $recordRemind['create_datetime'] = date('Y-m-d H:i:s');
            $recordRemind['update_datetime'] = date('Y-m-d H:i:s');
            $recordRemind['create_user_id'] = $currentUser->user_id;
            $recordRemind['update_user_id'] = $currentUser->user_id;
            $dataRemind[] = $recordRemind;
        }

        // create Remind
        $this->remindRepo->insertMultiRecord($dataRemind);
    }

    public function createCheckList($newTaskId, $checkLists, $currentUser)
    {
        $dataCheckList = [];
        foreach ($checkLists as $checkList) {
            $recordCheckList = [];
            $recordCheckList['check_list_id'] = AppService::generateUUID();
            $recordCheckList['task_id'] = $newTaskId;
            $recordCheckList['check_name'] = $checkList['check_name'] ?? null;
            $recordCheckList['complete_flg'] = $checkList['complete_flg'] ?? config('apps.checklist.not_completed');
            $recordCheckList['create_datetime'] = date('Y-m-d H:i:s');
            $recordCheckList['update_datetime'] = date('Y-m-d H:i:s');
            $recordCheckList['create_user_id'] = $currentUser->user_id;
            $recordCheckList['update_user_id'] = $currentUser->user_id;
            $dataCheckList[] = $recordCheckList;
        }

        // create check List
        $this->checkListRepo->insertMultiRecord($dataCheckList);
    }

    public function createAttachmentFiles($newTaskId, $attachmentFiles, $currentUser)
    {
        $dataAttachmentFile = [];
        foreach ($attachmentFiles as $attachmentFile) {
            $file_size = $attachmentFile['attachment_file']->getSize();
            $attachment_file_path = Storage::put(AttachmentFile::PATH_STORAGE_FILE . $newTaskId, $attachmentFile['attachment_file'], 'public');
            if (!Storage::exists($attachment_file_path)) {
                continue;
            }
            $attachment_file_name = basename($attachment_file_path);
            $recordAttachmentFile = [];
            $recordAttachmentFile['attachment_file_id'] = AppService::generateUUID();
            $recordAttachmentFile['task_id'] = $newTaskId;
            $recordAttachmentFile['attachment_file_name'] = $attachmentFile['attachment_file_name'] ?? null;
            $recordAttachmentFile['attachment_file_path'] = $attachment_file_path;
            $recordAttachmentFile['file_size'] = $file_size;
            $recordAttachmentFile['delete_flg'] = config('apps.general.not_deleted');
            $recordAttachmentFile['create_datetime'] = date('Y-m-d H:i:s');
            $recordAttachmentFile['update_datetime'] = date('Y-m-d H:i:s');
            $recordAttachmentFile['create_user_id'] = $currentUser->user_id;
            $recordAttachmentFile['update_user_id'] = $currentUser->user_id;
            $dataAttachmentFile[] = $recordAttachmentFile;
        }

        // create check List
        $this->attachmentFileRepo->insertMultiRecord($dataAttachmentFile);
    }

    public function createBreakdown($newTaskId, $breakdowns, $currentUser)
    {
        $dataBreakdown = [];
        foreach ($breakdowns as $breakdown) {
            $recordBreakdown = [];
            $recordBreakdown['breakdown_id'] = AppService::generateUUID();
            $recordBreakdown['task_id'] = $newTaskId;
            $recordBreakdown['plan_date'] = $breakdown['plan_date'] ?? null;
            $recordBreakdown['work_item'] = $breakdown['work_item'] ?? null;
            $recordBreakdown['progress'] = $breakdown['progress'] ?? null;
            $recordBreakdown['comment'] = $breakdown['comment'] ?? null;
            $recordBreakdown['reportee_user_id'] = $breakdown['reportee_user_id'] ?? null;
            $recordBreakdown['create_datetime'] = date('Y-m-d H:i:s');
            $recordBreakdown['update_datetime'] = date('Y-m-d H:i:s');
            $recordBreakdown['create_user_id'] = $currentUser->user_id;
            $recordBreakdown['update_user_id'] = $currentUser->user_id;
            $recordBreakdown['delete_flg'] = config('apps.general.not_deleted');
            $dataBreakdown[] = $recordBreakdown;
        }

        // create Breakdown
        $this->breakdownRepo->insertMultiRecord($dataBreakdown);
    }

    public function edit($task, Request $request, $currentUser)
    {
        try {
            DB::beginTransaction();
            $task_status_id = $task->task_status_id;
            $currentDate = date('Y-m-d');
            $startDate = $task->start_date;
            $endDate = $task->end_date;
            $startDateInput = $request->input('start_date');
            $endDateInput = $request->input('end_date');
            $startPlanDate = $request->input('start_plan_date');
            $endPlanDate = $request->input('end_plan_date');

            if (!is_null($startPlanDate) && (is_null($startDate) || is_null($startDateInput))) {
                if ($startPlanDate < $currentDate) {
                    $task_status_id = config('apps.task.status_key.delay_start');
                } else {
                    $task_status_id = config('apps.task.status_key.not_started');
                }
            }
            if (!is_null($endPlanDate)
                && (!is_null($startDate) || !is_null($startDateInput))
                && (is_null($endDate) || is_null($endDateInput))) {
                if ($endPlanDate < $currentDate) {
                    $task_status_id = config('apps.task.status_key.delay_complete');
                } else {
                    $task_status_id = config('apps.task.status_key.in_progress');
                }
            }
            if (!is_null($startDateInput) && (is_null($endDate) || is_null($endDateInput)) && is_null($endPlanDate)) {
                $task_status_id = config('apps.task.status_key.in_progress');
            }
            if (!is_null($endDateInput)) {
                if (!is_null($startDate) || is_null($startDateInput)) {
                    $task->start_date = $endDateInput;
                }
                $task_status_id = config('apps.task.status_key.complete');
            }

            $dataTask = [
                'task_group_id' => $request->input('task_group_id') ?? $task->task_group_id,
                'project_id' => $request->input('project_id') ?? $task->project_id,
                'task_name' => $request->input('task_name') ?? $task->task_name,
                'priority_id' => $request->input('priority_id') ?? $task->priority_id,
                'user_id' => $request->input('user_id') ?? $task->user_id,
                'disclosure_range_id' => $request->input('disclosure_range_id') ?? $task->disclosure_range_id,
                'task_status_id' => $task_status_id,
                'start_plan_date' => $request->has('start_plan_date') ? $request->start_plan_date : $task->start_plan_date,
                'end_plan_date' => $request->has('end_plan_date') ? $request->end_plan_date : $task->end_plan_date,
                'start_date' => $request->has('start_date') ? $request->start_date : $task->start_date,
                'end_date' => $request->has('end_date') ? $request->end_date : $task->end_date,
                'parent_task_id' => $request->input('parent_task_id') ?? $task->parent_task_id,
                'task_memo' => $request->input('task_memo') ?? $task->task_memo,
                'update_datetime' => date('Y-m-d H:i:s'),
                'update_user_id' => $currentUser->user_id
            ];
            $this->taskRepository->updateByField('task_id', $task->task_id, $dataTask);

            // edit Remind
            $reminds = $request->input('remaind');
            if (!empty($reminds)) {
                $this->editRemind($task->task_id, $reminds, $currentUser);
            }

            // edit check List
            $checkLists = $request->input('check_list');
            if (!empty($checkLists)) {
                $this->editCheckList($task->task_id, $checkLists, $currentUser);
            }

            // upload attachment file
            $attachmentFiles = $request->attachment_file;
            if (!empty($attachmentFiles)) {
                $this->editAttachmentFiles($task->task_id, $attachmentFiles, $currentUser);
            }

            // edit breakdown
            $breakdowns = $request->input('breakdown');
            if (!empty($breakdowns)) {
                $this->editBreakdowns($task->task_id, $breakdowns, $currentUser);
            }

            DB::commit();

            $taskUpdate = $this->taskRepository->getByCol('task_id', $task->task_id);

            $taskDetail = $this->detail($taskUpdate->task_id);

            return $taskDetail;
        } catch (\Exception $exception) {
            DB::rollBack();
            return self::sendError([trans('message.ERR_EXCEPTION')], [], config('apps.general.error_code', 600));
        }
    }

    public function editRemind($taskId, $reminds, $currentUser)
    {
        $dataCreateRemind = [];
        foreach ($reminds as $remind) {
            if (!isset($remind['remaind_id'])) {
                $dataCreateRemind[] = $remind;
                continue;
            }

            $recordRemind = $this->remindRepo->getByCols([
                'remaind_id' => $remind['remaind_id'],
                'task_id' => $taskId
            ]);
            if (!$recordRemind) {
                continue;
            }

            $dataRemind = [
                'remaind_datetime' => $remind['remaind_datetime'] ?? $recordRemind->remaind_datetime,
                'update_datetime' => date('Y-m-d H:i:s'),
                'update_user_id' => $currentUser->user_id
            ];
            // edit Remind
            $this->remindRepo->updateByField('remaind_id', $remind['remaind_id'], $dataRemind);
        }
        // create Remind
        if (!empty($dataCreateRemind)) {
            $this->createRemind($taskId, $dataCreateRemind, $currentUser);
        }
    }

    public function editCheckList($taskId, $checkLists, $currentUser)
    {
        $dataCreateCheckList = [];
        foreach ($checkLists as $checkList) {
            if (!isset($checkList['check_list_id'])) {
                $dataCreateCheckList[] = $checkList;
                continue;
            }

            $recordCheckList = $this->checkListRepo->getByCols([
                'check_list_id' => $checkList['check_list_id'],
                'task_id' => $taskId,
                'delete_flg' => config('apps.general.not_deleted')
            ]);
            if (!$recordCheckList) {
                continue;
            }

            $dataCheckList = [
                'check_name' => $checkList['check_name'] ?? $recordCheckList->check_name,
                'complete_flg' => $checkList['complete_flg'] ?? $recordCheckList->complete_flg,
                'update_datetime' => date('Y-m-d H:i:s'),
                'update_user_id' => $currentUser->user_id
            ];
            // edit check List
            $this->checkListRepo->updateByField('check_list_id', $checkList['check_list_id'], $dataCheckList);
        }
        // create check List
        if (!empty($dataCreateCheckList)) {
            $this->createCheckList($taskId, $dataCreateCheckList, $currentUser);
        }
    }

    public function editAttachmentFiles($taskId, $attachmentFiles, $currentUser)
    {
        $dataCreateAttachmentFile = [];
        foreach ($attachmentFiles as $attachmentFile) {
            if (!isset($attachmentFile['attachment_file_id'])) {
                $dataCreateAttachmentFile[] = $attachmentFile;
                continue;
            }

            $recordAttachmentFile = $this->attachmentFileRepo->getByCols([
                'attachment_file_id' => $attachmentFile['attachment_file_id'],
                'task_id' => $taskId,
                'delete_flg' => config('apps.general.not_deleted')
            ]);
            if (!$recordAttachmentFile) {
                continue;
            }

            $file_size = $attachmentFile['attachment_file']->getSize();
            $attachment_file_path = Storage::put(AttachmentFile::PATH_STORAGE_FILE . $taskId, $attachmentFile['attachment_file'], 'public');
            if (!Storage::exists($attachment_file_path)) {
                continue;
            }
            $attachment_file_name = basename($attachment_file_path);

            $dataAttachmentFile = [
                'attachment_file_name' => $attachmentFile['attachment_file_name'] ?? $recordAttachmentFile->attachment_file_name,
                'attachment_file_path' => $attachment_file_path,
                'file_size' => $file_size,
                'update_datetime' => date('Y-m-d H:i:s'),
                'update_user_id' => $currentUser->user_id
            ];
            // edit attachment file
            $this->attachmentFileRepo->updateByField('attachment_file_id', $attachmentFile['attachment_file_id'], $dataAttachmentFile);
        }
        // create check List
        if (!empty($dataCreateAttachmentFile)) {
            $this->createAttachmentFiles($taskId, $dataCreateAttachmentFile, $currentUser);
        }
    }

    public function editBreakdowns($taskId, $breakdowns, $currentUser)
    {
        $dataCreateBreakdown = [];
        foreach ($breakdowns as $breakdown) {
            if (!isset($breakdown['breakdown_id'])) {
                $dataCreateBreakdown[] = $breakdown;
                continue;
            }

            $recordBreakdown = $this->breakdownRepo->getByCols([
                'breakdown_id' => $breakdown['breakdown_id'],
                'task_id' => $taskId,
                'delete_flg' => config('apps.general.not_deleted')
            ]);
            if (!$recordBreakdown) {
                continue;
            }

            $dataCheckList = [
                'plan_date' => $breakdown['plan_date'] ?? $recordBreakdown->plan_date,
                'work_item' => $breakdown['work_item'] ?? $recordBreakdown->work_item,
                'progress' => $breakdown['progress'] ?? $recordBreakdown->progress,
                'comment' => $breakdown['comment'] ?? $recordBreakdown->comment,
                'reportee_user_id' => $breakdown['reportee_user_id'] ?? $recordBreakdown->reportee_user_id,
                'update_datetime' => date('Y-m-d H:i:s'),
                'update_user_id' => $currentUser->user_id
            ];
            // edit breakdown
            $this->breakdownRepo->updateByField('breakdown_id', $breakdown['breakdown_id'], $dataCheckList);
        }
        // create Breakdown
        if (!empty($dataCreateBreakdown)) {
            $this->createBreakdown($taskId, $dataCreateBreakdown, $currentUser);
        }
    }

    public function validateTaskForm(Request $request)
    {
        $progress_key_list = array_keys(config('apps.general.progress'));
        return Validator::make(
            $request->all(),
            [
                'task_name' => ['required', 'max:50'],
                'task_group_id' => [
                    'required',
                    Rule::exists('t_task_group', 'task_group_id')->where(function ($query) {
                        return $query->where('delete_flg', config('apps.general.not_deleted'));
                    }),
                ],
                'priority_id' => [
                    'required',
                    'exists:m_priority_mst,priority_id'
                ],
                'user_id' => [
                    'nullable',
                    Rule::exists('t_user', 'user_id')->where(function ($query) {
                        return $query->where('delete_flg', config('apps.general.not_deleted'));
                    }),
                ],
                'disclosure_range_id' => [
                    'required',
                    'exists:m_disclosure_range_mst,disclosure_range_id'
                ],
                'start_plan_date' => ['required', 'date', 'date_format:Y-m-d'],
                'end_plan_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_plan_date'],
                'start_date' => ['nullable', 'date', 'date_format:Y-m-d'],
                'end_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
                'parent_task_id' => [
                    'nullable',
                    Rule::exists('t_task', 'task_id')->where(function ($query) {
                        return $query->where('delete_flg', config('apps.general.not_deleted'));
                    }),
                ],
                'remaind' => ['required', 'array'],
                'remaind.*.remaind_id' => [
                    'nullable',
                    'exists:t_remind,remaind_id'
                ],
                'remaind.*.remaind_datetime' => ['required', 'date_format:Y-m-d H:i'],
                'check_list' => ['required', 'array'],
                'check_list.*.check_list_id' => [
                    'nullable',
                    Rule::exists('t_check_list', 'check_list_id')->where(function ($query) {
                        return $query->where('delete_flg', config('apps.general.not_deleted'));
                    })
                ],
                'check_list.*.check_name' => ['required', 'max:50'],
                'check_list.*.complete_flg' => ['required', 'integer'],
                'attachment_file' => ['nullable', 'array'],
                'attachment_file.*.attachment_file_id' => [
                    'nullable',
                    Rule::exists('t_attachment_file', 'attachment_file_id')->where(function ($query) {
                        return $query->where('delete_flg', config('apps.general.not_deleted'));
                    })
                ],
                'attachment_file.*.attachment_file' => ['nullable', 'file', 'max:300000', 'mimes:pdf,jpeg,jpg,png,doc,docx,xlsx,xls,mp4,mov'],
                'attachment_file.*.attachment_file_name' => ['nullable'],
                'task_status_id' => [
                    'required',
                    'exists:m_task_status,task_status_id'
                ],
                'task_memo' => ['nullable', 'max:500'],
                'project_id' => [
                    'required',
                    Rule::exists('t_project', 'project_id')->where(function ($query) {
                        return $query->where('delete_flg', config('apps.general.not_deleted'));
                    }),
                ],
                'breakdown' => ['nullable', 'array'],
                'breakdown.*.reportee_user_id' => [
                    'nullable',
                    Rule::exists('t_user', 'project_id')->where(function ($query) {
                        return $query->where('delete_flg', config('apps.general.not_deleted'));
                    }),
                ],
                'breakdown.*.breakdown_id' => [
                    'nullable',
                    Rule::exists('t_breakdown', 'breakdown_id')->where(function ($query) {
                        return $query->where('delete_flg', config('apps.general.not_deleted'));
                    }),
                ],
                'breakdown.*.work_item' => ['nullable', 'max:100'],
                'breakdown.*.plan_date' => ['nullable', 'date', 'date_format:Y-m-d'],
                'breakdown.*.comment' => ['nullable', 'max:100'],
                'breakdown.*.progress' => ['nullable', 'in:' . implode(',', $progress_key_list)],
            ],
            [
                'task_name.required' => trans('message.ERR_COM_0001', ['attribute' => trans('label.task.task_name')]),
                'task_name.max' => trans('message.ERR_COM_0002', ['attribute' => trans('label.task.task_name'), 'max' => '50']),
                'task_group_id.required' => trans('message.ERR_COM_0001', ['attribute' => trans('label.task.task_group_id')]),
                'task_group_id.exists' => trans('message.ERR_COM_0011', ['attribute' => trans('label.task.task_group_id')]),
                'priority_id.required' => trans('message.ERR_COM_0001', ['attribute' => trans('label.task.priority_id')]),
                'priority_id.exists' => trans('message.ERR_COM_0011', ['attribute' => trans('label.task.priority_id')]),
                'disclosure_range_id.required' => trans('message.ERR_COM_0001', ['attribute' => trans('label.task.disclosure_range_id')]),
                'disclosure_range_id.exists' => trans('message.ERR_COM_0011', ['attribute' => trans('label.task.disclosure_range_id')]),
                'start_plan_date.required' => trans('message.ERR_COM_0001', ['attribute' => trans('label.task.start_plan_date')]),
                'start_plan_date.date' => trans('message.INF_COM_0006', ['attribute' => trans('label.task.start_plan_date')]),
                'start_plan_date.date_format' => trans('validation.date_format', ['attribute' => trans('label.task.start_plan_date'), 'format' => 'Y-m-d']),
                'end_plan_date.required' => trans('message.ERR_COM_0001', ['attribute' => trans('label.task.end_plan_date')]),
                'end_plan_date.date' => trans('message.INF_COM_0006', ['attribute' => trans('label.task.end_plan_date')]),
                'end_plan_date.date_format' => trans('validation.date_format', ['attribute' => trans('label.task.end_plan_date'), 'format' => 'Y-m-d']),
                'end_plan_date.after_or_equal' => trans('validation.after_or_equal', ['attribute' => trans('label.task.end_plan_date'), 'date' => trans('label.task.start_plan_date')]),
                'start_date.date' => trans('message.INF_COM_0006', ['attribute' => trans('label.task.start_date')]),
                'start_date.date_format' => trans('validation.date_format', ['attribute' => trans('label.task.start_date'), 'format' => 'Y-m-d']),
                'end_date.date' => trans('message.INF_COM_0006', ['attribute' => trans('label.task.end_date')]),
                'end_date.date_format' => trans('validation.date_format', ['attribute' => trans('label.task.end_date'), 'format' => 'Y-m-d']),
                'end_date.after_or_equal' => trans('validation.after_or_equal', ['attribute' => trans('label.task.end_date'), 'date' => trans('label.task.start_date')]),
                'parent_task_id.exists' => trans('message.ERR_COM_0011', ['attribute' => trans('label.task.parent_task_id')]),
                'remaind.required' => trans('message.ERR_COM_0001', ['attribute' => trans('label.task.remaind')]),
                'remaind.array' => trans('validation.array', ['attribute' => trans('label.task.remaind')]),
                'remaind.*.remaind_id.exists' => trans('message.ERR_COM_0011', ['attribute' => trans('label.task.remaind')]),
                'remaind.*.remaind_datetime.required' => trans('message.ERR_COM_0001', ['attribute' => trans('label.task.remaind_datetime')]),
                'remaind.*.remaind_datetime.date_format' => trans('validation.date_format', ['attribute' => trans('label.task.remaind_datetime'), 'format' => 'Y-m-d H:i']),
                'check_list.required' => trans('message.ERR_COM_0001', ['attribute' => trans('label.task.check_list')]),
                'check_list.array' => trans('validation.array', ['attribute' => trans('label.task.check_list')]),
                'check_list.*.check_list_id.exists' => trans('message.ERR_COM_0011', ['attribute' => trans('label.task.check_list')]),
                'check_list.*.check_name.required' => trans('message.ERR_COM_0001', ['attribute' => trans('label.task.check_name')]),
                'check_list.*.check_name.max' => trans('message.ERR_COM_0002', ['attribute' => trans('label.task.check_name'), 'max' => '50']),
                'check_list.*.complete_flg.required' => trans('message.ERR_COM_0001', ['attribute' => trans('label.task.complete_flg')]),
                'check_list.*.complete_flg.integer' => trans('message.INF_COM_0005', ['attribute' => trans('label.task.complete_flg')]),
                'attachment_file.array' => trans('validation.array', ['attribute' => trans('label.task.attachment_file')]),
                'attachment_file.*.attachment_file_id.exists' => trans('message.ERR_COM_0011', ['attribute' => trans('label.task.attachment_file')]),
                'attachment_file.*.attachment_file.file' => trans('validation.file', ['attribute' => trans('label.task.attachment_file')]),
                'attachment_file.*.attachment_file.max' => trans('validation.max', ['attribute' => trans('label.task.attachment_file'), 'max' => '300000']),
                'attachment_file.*.attachment_file.mimes' => trans('validation.mimes', ['attribute' => trans('label.task.attachment_file'), 'values' => 'pdf,jpeg,jpg,png,doc,docx,xlsx,xls,mp4,mov']),
                'task_status_id.required' => trans('message.ERR_COM_0001', ['attribute' => trans('label.task.task_status_id')]),
                'task_status_id.exists' => trans('message.ERR_COM_0011', ['attribute' => trans('label.task.task_status_id')]),
                'task_memo.max' => trans('message.ERR_COM_0002', ['attribute' => trans('label.task.task_memo'), 'max' => '100']),
                'project_id.required' => trans('message.ERR_COM_0001', ['attribute' => trans('label.task.project_id')]),
                'project_id.exists' => trans('message.ERR_COM_0011', ['attribute' => trans('label.task.project_id')]),
                'breakdown.array' => trans('validation.array', ['attribute' => trans('validation_attribute.breakdown_id')]),
                'breakdown.*.reportee_user_id.exists' => trans('message.ERR_COM_0011', ['attribute' => trans('label.user.user')]),
                'breakdown.*.breakdown_id.exists' => trans('message.ERR_COM_0011', ['attribute' => trans('validation_attribute.breakdown_id')]),
            ]
        );
    }

    public function getListTaskByGroup(Request $request, $currentUser)
    {
        try {
            $taskGroupId = $request->input('task_group_id');
            $perPage = $request->input('page') ?? config('apps.notification.record_per_page');
            $taskGroup = $this->taskGroupRepo->getByCols([
                'task_group_id' => $taskGroupId,
                'delete_flg' => config('apps.general.not_deleted')
            ]);

            if (!$taskGroup) {
                return $this->sendError([trans('message.ERR_COM_0011', ['attribute' => trans('label.task.task_group_id')])]);
            }

            $data = $this->taskRepository->getListTaskByGroup($currentUser->user_id, $taskGroupId, $perPage);

            return self::sendResponse([trans('message.SUCCESS')], $data);
        } catch (\Exception $exception) {
            return self::sendError([trans('message.ERR_EXCEPTION')], [], config('apps.general.error_code', 600));
        }
    }

    public function getGraphDetail($projectId, $taskStatusId, $managerId = null, $taskGroupId = null)
    {
        try {
            // Check empty projectId is exists
            if (empty($projectId) && !$this->projectRepo->isExists($projectId)) {
                return $this->sendError(trans('message.NOT_COMPLETE'));
            }
            // Check empty taskStatusId is exists
            $taskStatus = null;
            if (!is_null($taskStatusId)) {
                $taskStatus = $this->taskStatusRepo->getByCol('task_status_id', $taskStatusId);
                if (!$taskStatus) {
                    return $this->sendError(trans('message.NOT_COMPLETE'));
                }
            }
            if (!is_null($managerId) && $managerId != config('apps.task.task_detail.others')) {
                $manager = $this->userRepo->getByCol('user_id', $managerId);
                if (!$manager) {
                    return $this->sendError(trans('message.NOT_COMPLETE'));
                }
            }
            if (!is_null($taskGroupId) && $taskGroupId != config('apps.task.task_detail.others')) {
                $taskGroup = $this->taskGroupRepo->getByCol('task_group_id', $taskGroupId);
                if (!$taskGroup) {
                    return $this->sendError(trans('message.NOT_COMPLETE'));
                }
            }
            //  Call getGraphDetail function in Project Repository to get get Graph Detail
            $data = $this->taskRepository->getGraphDetail($projectId, $taskStatusId, $managerId, $taskGroupId);
            $data->getCollection()->transform(function ($item) {
                $item->disp_name = null;
                $item->icon_image_path = null;
                if (!is_null($item->user_id)) {
                    $user = $this->userRepo->getByCols([
                        'user_id' => $item->user_id,
                        'delete_flg' => config('apps.general.not_deleted')
                    ]);
                    if ($user) {
                        $item->disp_name = $user->disp_name;
                        $item->icon_image_path = $user->getIconImageAttribute();
                    }
                }

                return $item;
            });

            return $this->sendResponse(trans('message.COMPLETE'), [
                'title_name' => !is_null($taskStatus) ? $taskStatus->task_status_name : trans('label.task_status_name.all'),
                'task' => $data
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.NOT_COMPLETE')
            );
        }
    }

    public function removeCheckList($checkList, $currentUser)
    {
        try {
            $checkList->delete_flg = config('apps.general.is_deleted');
            $checkList->update_user_id = $currentUser->user_id;
            $checkList->save();
            return self::sendResponse([trans('message.SUCCESS')]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return self::sendError([trans('message.ERR_EXCEPTION')], [], config('apps.general.error_code', 600));
        }
    }

    public function removeRemind($remind, $currentUser)
    {
        try {
            $remind->delete_flg = config('apps.general.is_deleted');
            $remind->update_user_id = $currentUser->user_id;
            $remind->save();
            return self::sendResponse([trans('message.SUCCESS')]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return self::sendError([trans('message.ERR_EXCEPTION')], [], config('apps.general.error_code', 600));
        }
    }

    public function searchMemberByName($company_id, $key_word)
    {
        try {
            $result = $this->userRepo->searchMemberByName($company_id, $key_word);
            return self::sendResponse([trans('message.SUCCESS')], $result);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return self::sendError([trans('message.ERR_EXCEPTION')], [], config('apps.general.error_code', 600));
        }
    }

    public function createOrUpdateCheckList(Request $request, $currentUser)
    {
        try {
            if ($request->check_list_id) {
                $recordCheckList = $this->checkListRepo->getByCols([
                    'check_list_id'     => $request->check_list_id,
                    'task_id'           => $request->task_id,
                    'delete_flg'        => config('apps.general.not_deleted')
                ]);

                $data = [
                    'check_name'        => $request->check_name ?? $recordCheckList->check_name,
                    'complete_flg'      => $request->complete_flg ?? $recordCheckList->complete_flg,
                    'update_datetime'   => date('Y-m-d H:i:s'),
                    'update_user_id'    => $currentUser->user_id,
                ];

                $this->checkListRepo->updateByField('check_list_id', $recordCheckList->check_list_id, $data);

                $updateRecord = $this->checkListRepo->getByCol('check_list_id', $recordCheckList->check_list_id);
                return self::sendResponse([trans('message.SUCCESS')], $updateRecord);
            } else {
                $check_list_id = AppService::generateUUID();
                $data = [
                    'check_list_id'     => $check_list_id,
                    'task_id'           => $request->task_id ?? null,
                    'check_name'        => $request->check_name ?? null,
                    'complete_flg'      => $request->complete_flg ?? config('apps.checklist.not_completed'),
                    'delete_flg'        => config('apps.general.not_deleted'),
                    'create_datetime'   => date('Y-m-d H:i:s'),
                    'update_datetime'   => date('Y-m-d H:i:s'),
                    'create_user_id'    => $currentUser->user_id,
                    'update_user_id'    => $currentUser->user_id,
                ];
                $this->checkListRepo->store($data);

                $newRecord = $this->checkListRepo->getByCol('check_list_id', $check_list_id);
                return self::sendResponse([trans('message.SUCCESS')], $newRecord);
            }
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            return self::sendError([trans('message.ERR_EXCEPTION')], [], config('apps.general.error_code', 600));
        }
    }

    public function createOrUpdateRemind(Request $request, $currentUser)
    {
        try {
            if ($request->remaind_id) {
                $recordRemind = $this->remindRepo->getByCols([
                    'remaind_id'        => $request->remaind_id,
                    'task_id'           => $request->task_id,
                    'delete_flg'        => config('apps.general.not_deleted')
                ]);

                $data = [
                    'remaind_datetime'  => $request->remaind_datetime ?? $recordRemind->remaind_datetime,
                    'update_datetime'   => date('Y-m-d H:i:s'),
                    'update_user_id'    => $currentUser->user_id,
                ];

                $this->remindRepo->updateByField('remaind_id', $recordRemind->remaind_id, $data);

                $updateRecord = $this->remindRepo->getByCol('remaind_id', $recordRemind->remaind_id);
                return self::sendResponse([trans('message.SUCCESS')], $updateRecord);
            } else {
                $remaind_id = AppService::generateUUID();
                $data = [
                    'remaind_id'        => $remaind_id,
                    'task_id'           => $request->task_id ?? null,
                    'remaind_datetime'  => $request->remaind_datetime ?? null,
                    'delete_flg'        => config('apps.general.not_deleted'),
                    'create_datetime'   => date('Y-m-d H:i:s'),
                    'update_datetime'   => date('Y-m-d H:i:s'),
                    'create_user_id'    => $currentUser->user_id,
                    'update_user_id'    => $currentUser->user_id,
                ];
                $this->remindRepo->store($data);

                $newRecord = $this->remindRepo->getByCol('remaind_id', $remaind_id);
                return self::sendResponse([trans('message.SUCCESS')], $newRecord);
            }
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            return self::sendError([trans('message.ERR_EXCEPTION')], [], config('apps.general.error_code', 600));
        }
    }

    /**
     * Get additional task information: task status name, is started,  is completed
     *
     * @param  mixed $taskDetail
     * @return mixed
     */
    public function getAdditionalInforTask($taskDetail)
    {
        $taskDetail->parent_task_name = $taskDetail->task_parent ? $taskDetail->task_parent->task_name : '';
        $taskDetail->task_status_name = $taskDetail->task_status ? $taskDetail->task_status->task_status_name : "";
        $taskDetail->is_started = ($taskDetail->task_status_id == config('apps.task.status_key.not_started') || $taskDetail->task_status_id == config('apps.task.status_key.delay_start')) ? 0 : 1;
        $taskDetail->is_completed = $taskDetail->task_status_id == config('apps.task.status_key.complete') ? 1 : 0;

        return $taskDetail;
    }

    /**
     * Get list task of project by group
     *
     * @param $projectId
     * @param $groupTaskId
     * @return mixed
     */
    public function getTaskByProjectTaskGroups($projectId, $groupTaskId)
    {
        try {
            // Check empty projectId is exists
            if (empty($projectId) && !$this->projectRepo->isExists($projectId)) {
                return $this->sendError(trans('message.NOT_COMPLETE'));
            }
            // Check empty taskStatusId is exists
            $taskGroup = $this->taskGroupRepo->getById($groupTaskId);
            if (!$taskGroup) {
                return $this->sendError(trans('message.NOT_COMPLETE'));
            }
            //  Call getGraphDetail function in Project Repository to get get Graph Detail
            $data = $this->taskRepository->getTaskByProjectTaskGroups($projectId, $groupTaskId);

            return $this->sendResponse(trans('message.COMPLETE'), [
                'title_name' => $taskGroup->group_name,
                'task' => $data
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.NOT_COMPLETE')
            );
        }
    }

    public function getProjectMembers($projectId)
    {
        $response = [
            'status' => config('apps.general.success'),
            'data' => null,
            'message' => [trans('message.SUCCESS')],
            'message_id' => ['SUCCESS']
        ];

        try {
            $response['data'] = $this->userRepo->getProjectMembers($projectId);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response['status'] = config('apps.general.error');
            $response['message'] = [trans('message.ERR_EXCEPTION')];
            $response['message_id'] = ['ERR_EXCEPTION'];
        }

        return $response;
    }

    /**
     * screen G030 service
     * @param $currentUser
     * @param $filters
     * @return array
     */
    public function searchTasksByUser($currentUser, $filters)
    {
        try {
            if ($currentUser->guest_flg == config('apps.user.is_guest')) {
                return $this->sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
            }
            $tasks = $this->taskRepository->getModel()::join('t_project', 't_project.project_id', '=', 't_task.project_id')
                ->join('t_task_group', 't_task_group.task_group_id', '=', 't_task.task_group_id')
                ->with(['user' => function ($query) {
                    $query->where('delete_flg', config('apps.general.not_deleted'))
                        ->select(['user_id', 'disp_name', 'icon_image_path']);
                }])
                ->select(
                    't_project.project_id',
                    't_project.project_name',
                    't_task_group.group_name',
                    't_task.task_id',
                    't_task.task_name',
                    't_task.user_id'
                )
                ->where([
                    't_project.delete_flg' => config('apps.general.not_deleted'),
                    't_project.template_flg' => config('apps.project.template_open_flg.off'),
                    't_task_group.delete_flg' => config('apps.general.not_deleted'),
                    't_task.delete_flg' => config('apps.general.not_deleted'),
                ]);
            if ($currentUser->super_user_auth_flg == config('apps.user.is_super_user')) {
                $tasks = $tasks->where('t_project.company_id', $currentUser->company_id);
            } else {
                $tasks = $tasks->join('t_project_participant', 't_project.project_id', '=', 't_project_participant.project_id')
                    ->where([
                        't_project_participant.delete_flg' => config('apps.general.not_deleted'),
                        't_project_participant.user_id' => $currentUser->user_id,
                    ]);
            }
            if (!is_null($filters['key_word'])) {
                $tasks->where(function ($query) use ($filters) {
                    $query->where('t_task.task_name', 'LIKE', '%' . $filters['key_word'] . '%')
                        ->orWhere('t_task.task_memo', 'LIKE', '%' . $filters['key_word'] . '%')
                        ->orWhere('t_task_group.group_name', 'LIKE', '%' . $filters['key_word'] . '%')
                        ->orWhereHas('breakdowns', function ($q) use ($filters) {
                            $q->where('work_item', 'LIKE', '%' . $filters['key_word'] . '%')
                                ->orWhere('comment', 'LIKE', '%' . $filters['key_word'] . '%');
                        })
                        ->orWhereHas('attachment_files', function ($q) use ($filters) {
                            $q->where('attachment_file_name', 'LIKE', '%' . $filters['key_word'] . '%');
                        })
                        ->orWhereHas('check_lists', function ($q) use ($filters) {
                            $q->where('check_name', 'LIKE', '%' . $filters['key_word'] . '%');
                        })
                        ->orWhereHas('comments', function ($q) use ($filters) {
                            $q->where('comment', 'LIKE', '%' . $filters['key_word'] . '%');
                        });
                });
            }
            if (is_array($filters['project_ids']) && count($filters['project_ids']) > 0) {
                $tasks = $tasks->whereIn('t_task.project_id', $filters['project_ids']);
            }
            if (is_array($filters['user_ids']) && count($filters['user_ids']) > 0) {
                $tasks = $tasks->whereIn('t_task.user_id', $filters['user_ids']);
            }

            $orderByProject = !is_null($filters['order_by_project_name']) ? $filters['order_by_project_name'] : "asc";
            $orderByGroupName = !is_null($filters['order_by_group_name']) ? $filters['order_by_group_name'] : "asc";
            $orderByTaskName = !is_null($filters['order_by_task_name']) ? $filters['order_by_task_name'] : "asc";
            $tasks = $tasks->orderBy('t_project.project_name', $orderByProject)
                ->orderBy('t_task_group.group_name', $orderByGroupName)
                ->orderBy('t_task.task_name', $orderByTaskName)
                ->paginate(config('apps.notification.record_per_page'));

            return $this->sendResponse(trans('message.COMPLETE'), $tasks);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.ERR_EXCEPTION')
            );
        }
    }
}
