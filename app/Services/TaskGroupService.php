<?php

namespace App\Services;

use App\Models\TaskGroup;
use App\Repositories\AttachmentFileRepository;
use App\Repositories\CheckListRepository;
use App\Repositories\CommentRepository;
use App\Repositories\TaskGroupRepository;
use App\Repositories\TaskRepository;
use App\Transformers\TaskGroup\TaskGroupDetailTransformer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskGroupService extends BaseService
{
    protected $taskGroupRepository;
    protected $taskRepository;
    protected $commentRepository;
    protected $checkListRepository;
    protected $attachmentFileRepository;
    public function __construct(
        TaskGroupRepository $taskGroupRepository,
        TaskRepository $taskRepository,
        CommentRepository  $commentRepository,
        CheckListRepository      $checkListRepository,
        AttachmentFileRepository $attachmentFileRepository
    ) {
        $this->taskGroupRepository = $taskGroupRepository;
        $this->taskRepository = $taskRepository;
        $this->commentRepository = $commentRepository;
        $this->checkListRepository = $checkListRepository;
        $this->attachmentFileRepository = $attachmentFileRepository;
    }

    public function editSettingGroup($userId, $taskGroupId, $groupName, $dispColorId)
    {
        $response = [
            'status'        => config('apps.general.success'),
            'data'          => null,
            'message'       => [trans('message.SUCCESS')],
            'message_id'    => ['SUCCESS']
        ];

        try {
            $taskGroup   = $this->taskGroupRepository->getByCol(
                'task_group_id',
                $taskGroupId
            );

            if (!$taskGroup) {
                $response['status'] = config('apps.general.error');
                $response['message'] = [trans('message.ERR_COM_0011', ['attribute' => 't_task_group'])];
                $response['message_id'] = ['ERR_COM_0011'];
                $response['error_code'] = config('apps.general.error_code');
                return $response;
            }

            $taskGroup->group_name = $groupName;
            $taskGroup->disp_color_id = $dispColorId;
            $taskGroup->update_user_id = $userId;
            $taskGroup->update_datetime = Carbon::now();

            $taskGroup->save();
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response['status']     = config('apps.general.error');
            $response['message']    = [trans('message.INF_COM_0010')];
            $response['message_id'] = ['INF_COM_0010'];
            $response['error_code'] = config('apps.general.error_code');
        }

        return $response;
    }

    public function getTaskGroupByProject($projectId, $filter)
    {
        $response = [
            'status'        => config('apps.general.success'),
            'data'          => null,
            'message'       => [trans('message.SUCCESS')],
            'message_id'    => ['SUCCESS']
        ];

        try {
            $response['data'] = $this->taskGroupRepository->getTaskGroupByProjectV2($projectId, $filter);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response = $this->exceptionError();
        }

        return $response;
    }

    public function fetchTaskGroupByProject($projectId, $filter, $flagFilter)
    {
        $response = $this->initResponse();
        try {
            $data = $this->taskGroupRepository->getTaskGroupByProjectV5($projectId, $filter, $flagFilter);
            $response['data'] = $data;
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response = $this->exceptionError();
        }

        return $response;
    }

    private function refactorDataGroupV2($data)
    {
        if (count($data) == 0) {
            return null;
        }

        $responseItems = [];
        $groups = [];
        $parents = [];

        foreach ($data as $item) {

            // Check group record
            if (empty($item->task_id) && empty($item->parent_task_id)) {
                // Create flag check
                $item->level = 1;
                $item->child = 0;
                $item->object_clone = false;
            } else {
                if (!in_array($item->task_group_id, $groups, true)) {

                    // Create group record
                    $newItem = clone $item;
                    $newItem->level = 1;
                    $newItem->child = 1;
                    $newItem->object_clone = true;

                    // Clear clone field
                    $newItem->task_id = null;
                    $newItem->task_name = null;
                    $newItem->parent_task_id = null;

                    $responseItems[] = $newItem;
                    $groups[] = $item->task_group_id;
                }

                // Current record is sub task
                if (!empty($item->parent_task_id)) {
                    // Create parent record

                    $item->level = 3;
                    $item->child = 0;
                    $item->object_clone = false;
                } else {
                    $item->level = 2;
                    $item->child = 0;
                    $item->object_clone = false;
                }

                $item->sub_task_info = $item->sub_tasks_complete_count . '/' .$item->sub_tasks_count;
                $item->check_list_info = $item->check_lists_complete_count . '/' .$item->check_lists_count;
                $item->scheduled_period = formatShowDate($item->start_plan_date) . ' - '.formatShowDate($item->end_plan_date);
                $item->achievement_period = formatShowDate($item->start_date) . ' - '.formatShowDate($item->end_date);
                $item->manager = $item->user ? $item->user->disp_name : '';
                $item->task_status_name = $item->task_status ? $item->task_status->task_status_name : '';
                $item->priority_name = $item->priority_mst ? $item->priority_mst->priority_name : '' ;
            }

            $responseItems[] = $item;
        }

        return new Collection($responseItems);
    }

    private function refactorDataGroup($data)
    {
        if (count($data) == 0) {
            return null;
        }
        $responseItems = [];
        $groups = [];
        $parents = [];

        foreach ($data as $item) {

            // Check group record
            if (empty($item->task_id) && empty($item->parent_task_id)) {
                // Create flag check
                $item->level = 1;
                $item->child = 0;
                $item->object_clone = false;
            } else {
                if (!in_array($item->task_group_id, $groups, true)) {

                    // Create group record
                    $newItem = clone $item;
                    $newItem->level = 1;
                    $newItem->child = 1;
                    $newItem->object_clone = true;

                    // Clear clone field
                    $newItem->task_id = null;
                    $newItem->task_name = null;
                    $newItem->parent_task_id = null;

                    $responseItems[] = $newItem;
                    $groups[] = $item->task_group_id;
                }

                // Current record is sub task
                if (!empty($item->parent_task_id)) {
                    // Create parent record
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
            }

            $responseItems[] = $item;
        }

        return new Collection($responseItems);
    }

    public function getDataExport($projectID, $filter)
    {
        $response = [
            'status'        => config('apps.general.success'),
            'data'          => null,
            'message'       => [trans('message.SUCCESS')],
            'message_id'    => ['SUCCESS']
        ];

        try {
            $data = $this->taskGroupRepository->getDataExport($projectID, $filter);
            if (count($data) > 0) {
                $response['data']['header'] = $this->getHeader($filter);
                $response['data']['content'] = $this->prepareDataExport($data, $filter);
            } else {
                $response['data']['header'] = [];
                $response['data']['content'] = [];
            }
            Log::info(response()->json($response['data']));
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response = $this->exceptionError();
        }

        return $response;
    }

    public function getGroupDetail($taskGroupId)
    {
        try {
            // Check empty taskGroupId is exists
            if (empty($taskGroupId) || !$this->taskGroupRepository->isExists($taskGroupId)) {
                return $this->sendError(trans('message.NOT_COMPLETE'));
            }
            //  Call getGroupDetail function in Task Group Repository to get Get Group Detail
            $data =  $this->taskGroupRepository->getGroupDetail($taskGroupId);

            return $this->sendResponse(trans('message.COMPLETE'), $data);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.ERR_EXCEPTION')
            );
        }
    }

    public function prepareDataExport($taskGroups, $filter)
    {
        $rows = [];
        foreach ($taskGroups as $data) {
            $groupName = $data->group_name;

            // parent_task
            $tasks = $data->tasks;
            if (count($tasks) > 0) {
                foreach ($tasks as $task) {
                    $taskRow = $this->getTaskRow($task, $groupName, true, $filter);
                    array_push($rows, $taskRow);

                    // sub_task
                    $subTasks = $task->sub_tasks;
                    if (count($subTasks) > 0) {
                        foreach ($subTasks as $subTask) {
                            $subTaskRow = $this->getTaskRow($subTask, $groupName, false, $filter);
                            array_push($rows, $subTaskRow);
                        }
                    }
                }
            }
        }
        return $rows;
    }

    public function getHeader($filter)
    {
        $exports = config('apps.task.export');
        $header = [];
        if ($filter['task'] !== null && count($filter['task']) > 0) {
            foreach ($exports as $export) {
                if (in_array($export, $filter['task'], true)) {
                    array_push($header, trans('label.export.'.$export));
                }
            }
        } else {
            foreach ($exports as $export) {
                array_push($header, trans('label.export.'.$export));
            }
        }

        return $header;
    }

    public function getTaskRow($task, $groupName, $parentTask, $filter)
    {
        $exports = config('apps.task.export');
        $content = [];

        $taskRow['group_name'] = $groupName;

        $taskRow['task_name'] = $task->task_name;
        $taskRow['sub_task'] = null;
        if ($parentTask === true) {
            $taskRow['sub_task'] = $task->sub_tasks_complete_count .' / '. $task->sub_tasks_count;
        }
        $taskRow['check_list'] = $task->check_lists_complete_count .' / '. $task->check_lists_count;
        $taskRow['scheduled_period'] = $task->start_plan_date .' - '. $task->end_plan_date;
        $taskRow['achievement_period'] = $task->start_date .' - '. $task->end_date;

        $taskRow['manager'] = null;
        $user = $task->user;
        if ($user) {
            $taskRow['manager'] = $user->disp_name;
        }

        $taskRow['priority'] = null;
        $priorityMst = $task->priority_mst;
        if ($priorityMst) {
            $taskRow['priority'] = $priorityMst->priority_name;
        }

        $taskRow['status'] = null;
        $taskStatus = $task->task_status;
        if ($taskStatus) {
            $taskRow['status']  = $taskStatus->task_status_name;
        }

        $taskRow['comment'] = count($task->comments) > 0 ? count($task->comments) : 0; // ++

        $taskRow['breakdown'] = count($task->breakdowns) > 0 ? count($task->breakdowns) : 0; // ++

        if ($filter['task'] !== null && count($filter['task']) > 0) {
            foreach ($exports as $export) {
                if (in_array($export, $filter['task'], true)) {
                    $content[$export] = $taskRow[$export];
                }
            }
        } else {
            $content = $taskRow;
        }

        return $content;
    }

    public function getTaskGroup($projectId)
    {
        $response = [
            'status'        => config('apps.general.success'),
            'data'          => null,
            'message'       => [trans('message.SUCCESS')],
        ];

        try {
            $response['data'] = $this->taskGroupRepository->getTaskGroup($projectId);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

        return $response;
    }

    public function createTaskGroup($projectId, $data)
    {
        $countTaskGroupByProject = TaskGroup::where('project_id', $projectId)->count();
        $data['task_group_id'] = AppService::generateUUID();
        $data['display_order'] = $countTaskGroupByProject+1;

        try {
            return $this->taskGroupRepository->getModel()::create($data);
        } catch (\Throwable $th) {
            set_log_error("createTaskGroup", $th->getMessage());
        }
        return false;
    }

    /**
     * get task group detail by project id F012
     *
     * @param [type] $projectId
     * @param array $filters
     * @param integer $paginate
     * @return collection
     */
    public function getTaskGroupDetailByProjectId($projectId, $filters = [], $paginate = null)
    {
        $model = $this->taskGroupRepository->getInstance();
        $query = $model->query()->avaiable()->with([
            'project',
            'disp_color',
            'tasks_parent' => function ($q) {
                $q->orderBy('t_task.parent_task_display_order', 'desc');
            },
            'tasks_parent.task_group',
            'tasks_parent.project',
            'tasks_parent.check_lists',
            'tasks_parent.user_likes',
            'tasks_parent.user_watch_lists',
            'tasks_parent.watch_lists',
            'tasks_parent.manager',
            'tasks_parent.author',
            'tasks_parent.sub_tasks' => function ($q) {
                $q->orderBy('t_task.sub_task_display_order', 'desc');
            },
            'tasks_parent.sub_tasks.task_group',
            'tasks_parent.sub_tasks.project',
            'tasks_parent.sub_tasks.check_lists',
            'tasks_parent.sub_tasks.user_likes',
            'tasks_parent.sub_tasks.user_watch_lists',
            'tasks_parent.sub_tasks.watch_lists',
            'tasks_parent.sub_tasks.manager',
            'tasks_parent.sub_tasks.author',
            'tasks_parent_not_complete',
        ])
            ->where('project_id', $projectId);

        if (!empty($filters['status']) || !empty($filters['priority']) || !empty($filters['manager']) || !empty($filters['author']) || !empty($filters['watch_list'])) {
            $query->whereHas('tasks_parent', function ($q) use ($filters) {
                if (!empty($filters['status']) && is_array($filters['status'])) {
                    $q->whereIn('task_status_id', $filters['status']);
                }

                if (!empty($filters['priority']) && is_array($filters['priority'])) {
                    $q->whereIn('priority_id', $filters['priority']);
                }

                if (!empty($filters['manager']) && is_array($filters['manager'])) {
                    $q->whereHas('manager', function ($k) use ($filters) {
                        $k->whereIn('user_id', $filters['manager']);
                    });
                }
                if (!empty($filters['author']) && is_array($filters['author'])) {
                    $q->whereHas('author', function ($k) use ($filters) {
                        $k->whereIn('user_id', $filters['author']);
                    });
                }
                if (!empty($filters['watch_list']) && is_array($filters['watch_list'])) {
                    $q->has('watch_list');
                }
            });
        }
        $query->orderBy('t_task_group.display_order', 'desc');

        if ($paginate !== null) {
            return $query->paginate($paginate);
        }

        return $query->get();
    }

    public function getTaskGroupDetailByProjectIdV2($projectId, $filters = [])
    {
        $model = $this->taskGroupRepository->getInstance();
        $query = $model->query()->avaiable()->with([
            'project',
            'disp_color',
            'tasks_parent' => function ($q) use ($filters) {
                if (!empty($filters['status'])) {
                    $q->whereIn('task_status_id', $filters['status']);
                }
                if (!empty($filters['priority'])) {
                    $q->whereIn('priority_id', $filters['priority']);
                }
                if (!empty($filters['manager'])) {
                    $q->whereIn('user_id', $filters['manager']);
                }
                if (!empty($filters['author'])) {
                    $q->whereIn('create_user_id', $filters['author']);
                }
                if ($filters['watch_list']) {
                    $q->has('watch_lists_by_current_user');
                }
            },
            'tasks_parent.task_group',
            'tasks_parent.project',
            'tasks_parent.check_lists',
            'tasks_parent.user_likes',
            'tasks_parent.user_watch_lists',
            'tasks_parent.manager',
            'tasks_parent.author',
            'tasks_parent.sub_tasks' => function ($q) use ($filters) {
                if (!empty($filters['status'])) {
                    $q->whereIn('task_status_id', $filters['status']);
                }
                if (!empty($filters['priority'])) {
                    $q->whereIn('priority_id', $filters['priority']);
                }
                if ($filters['watch_list']) {
                    $q->has('watch_lists_by_current_user');
                }
                if (!empty($filters['manager'])) {
                    $q->whereIn('user_id', $filters['manager']);
                }
                if (!empty($filters['author'])) {
                    $q->whereIn('create_user_id', $filters['author']);
                }
            },
            'tasks_parent.sub_tasks.task_group',
            'tasks_parent.sub_tasks.project',
            'tasks_parent.sub_tasks.check_lists',
            'tasks_parent.sub_tasks.user_likes',
            'tasks_parent.sub_tasks.user_watch_lists',
            'tasks_parent.sub_tasks.manager',
            'tasks_parent.sub_tasks.author',
            'tasks_parent_not_complete',
        ])
            ->where('t_task_group.project_id', $projectId);

        return $query->orderBy('t_task_group.display_parent_id', 'desc')
            ->orderBy('t_task_group.create_datetime', 'desc')
            ->get();
    }

    /**
     * Copy Task Group
     * TODO: S.F011.2_Copy_group
     * 2022-02-26
     *
     * @param $taskGroupId
     * @param $projectId
     * @param $groupName
     * @param $currentUserId
     * @param array $dataTaskGroupFilter
     * @return array
     */
    public function copyTaskGroup($taskGroupId, $projectId, $groupName, $currentUserId, array $dataTaskGroupFilter = [])
    {
        try {
            $oldTaskGroup = $this->taskGroupRepository->getByCol('task_group_id', $taskGroupId);
            // Copy task group
            $newTaskGroupData = [
                'task_group_id' => AppService::generateUUID(),
                'project_id' => $projectId,
                'group_name' => $groupName,
                'disp_color_id' => $oldTaskGroup->disp_color_id,
                'create_datetime' => date('Y-m-d H:i:s'),
                'create_user_id' => $currentUserId
            ];
            DB::beginTransaction();
            $newTaskGroup = $this->taskGroupRepository->insertOrUpdate(null, $newTaskGroupData);
            if (!$newTaskGroup) {
                return $this->sendError([trans('message.FAIL')]);
            }

            $model = $this->taskRepository->getModel()::where(['task_group_id' => $taskGroupId, 'delete_flg' => config('apps.general.not_deleted')]);
            $tasks = clone $model;

            // Get all tasks in a group
            $tasks = $tasks->whereNull('parent_task_id')->get();
            $taskIds=[];
            if ($tasks) {
                foreach ($tasks as $task) {
                    $taskId = $this->insertTask($newTaskGroup, $projectId, $task, $currentUserId, $dataTaskGroupFilter, $taskIds);
                    // key is old id, value is  new id
                    $taskIds[$task->task_id] = $taskId;
                }
            }
            // insert sub task when checked
            if ($dataTaskGroupFilter['sub_task'] == config('apps.general.check_box')) {
                $subTasks = clone $model;
                // Get all sub tasks in a group
                $subTasks = $subTasks->whereNotNull('parent_task_id')->get();
                if ($subTasks) {
                    foreach ($subTasks as $subTask) {
                        $taskId = $this->insertTask($newTaskGroup, $projectId, $subTask, $currentUserId, $dataTaskGroupFilter, $taskIds);
                    }
                }
            }
            DB::commit();
            $queryRelation = [
                'project',
                'disp_color',
                'tasks_parent',
                'tasks_parent.task_group',
                'tasks_parent.project',
                'tasks_parent.check_lists',
                'tasks_parent.user_likes',
                'tasks_parent.user_watch_lists',
                'tasks_parent.watch_lists',
                'tasks_parent.manager',
                'tasks_parent.author',
                'tasks_parent.sub_tasks',
                'tasks_parent.sub_tasks.task_group',
                'tasks_parent.sub_tasks.project',
                'tasks_parent.sub_tasks.check_lists',
                'tasks_parent.sub_tasks.user_likes',
                'tasks_parent.sub_tasks.user_watch_lists',
                'tasks_parent.sub_tasks.watch_lists',
                'tasks_parent.sub_tasks.manager',
                'tasks_parent.sub_tasks.author',
                'tasks_parent_not_complete',
            ];
            $newTaskGroup = $this->taskGroupRepository->getByCol('task_group_id', $newTaskGroup->task_group_id, $queryRelation);

            return  $this->sendResponse(trans('message.INF_COM_0001'), (new TaskGroupDetailTransformer())->transform($newTaskGroup));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            return $this->sendError([trans('message.ERR_EXCEPTION')]);
        }
    }

    public function insertTask($newTaskGroup, $projectId, $taskItem, $currentUserId, $dataTaskGroupFilter, $taskIds)
    {
        $newTaskData = [
            'task_id' => AppService::generateUUID(),
            'task_group_id' => $newTaskGroup->task_group_id,
            'project_id' => $projectId,
            'task_name' => $taskItem->task_name,
            'user_id' => $taskItem->user_id,
            'task_status_id' => $taskItem->task_status_id,
            'create_user_id' => $currentUserId,
            'create_datetime' => date('Y-m-d H:i:s')
        ];

        if ($dataTaskGroupFilter['priority'] == config('apps.general.check_box')) {
            $newTaskData['priority_id'] = $taskItem->priority_id;
        }

        if ($dataTaskGroupFilter['disclosure_range'] == config('apps.general.check_box')) {
            $newTaskData['disclosure_range_id'] = $taskItem->disclosure_range_id;
        }

        if ($dataTaskGroupFilter['start_end_plan_date'] == config('apps.general.check_box')) {
            $newTaskData['start_plan_date'] = $taskItem->start_plan_date;
            $newTaskData['end_plan_date'] = $taskItem->end_plan_date;
        }
        if ($dataTaskGroupFilter['task_memo'] == config('apps.general.checkbox')) {
            $newTaskData['task_memo'] = $taskItem->task_memo;
        }
        // Insert new task
        $newTask = $this->taskRepository->insertOrUpdate(null, $newTaskData);

        // Can't create new task, break this loop and return error
        if (!$newTask) {
            return $this->sendError([trans('message.FAIL')]);
        }
        // set parent id for new sub task
        if ($taskItem->parent_task_id != null && $taskIds) {
            foreach ($taskIds as $oldId => $newId) {
                if ($taskItem->parent_task_id == $oldId) {
                    $newTask->parent_task_id = $newId;
                    $newTask->save();
                }
            }
        }
        // Copy check_list
        if ($dataTaskGroupFilter['check_list'] == config('apps.general.check_box')) {
            $checkLists = $this->checkListRepository->all(['task_id' => $taskItem->task_id]);
            $checkListDataForNewTask = [];
            foreach ($checkLists as $checkList) {
                $checkListDataForNewTask[] = [
                    'check_list_id' => AppService::generateUUID(),
                    'task_id' => $newTaskData['task_id'],
                    'check_name' => $checkList->check_name,
                    'complete_flg' => $checkList->complete_flg,
                    'delete_flg' => $checkList->delete_flg,
                    'create_user_id' => $currentUserId,
                    'create_datetime' => date('Y-m-d H:i:s')
                ];
            }
            if (!empty($checkListDataForNewTask)) {
                $this->checkListRepository->insertMultiRecord($checkListDataForNewTask);
            }
        }

        // Copy attachment_file
        if ($dataTaskGroupFilter['attachment_file'] == config('apps.general.check_box')) {
            $attachmentFiles = $this->attachmentFileRepository->all(['task_id' => $taskItem->task_id]);
            $attachmentFilesDataForNewTask = [];
            foreach ($attachmentFiles as $attachmentFile) {
                $attachmentFilesDataForNewTask[] = [
                    'attachment_file_id' => AppService::generateUUID(),
                    'task_id' => $newTaskData['task_id'],
                    'attachment_file_name' => $attachmentFile->attachment_file_name,
                    'attachment_file_path' => $attachmentFile->attachment_file_path,
                    'file_size' => $attachmentFile->file_size,
                    'delete_flg' => $attachmentFile->delete_flg,
                    'create_user_id' => $currentUserId,
                    'create_datetime' => date('Y-m-d')
                ];
            }
            if (!empty($attachmentFilesDataForNewTask)) {
                $this->attachmentFileRepository->insertMultiRecord($attachmentFilesDataForNewTask);
            }
        }
        return $newTask->task_id;
    }

    public function swapGroup($input)
    {
        try {
            DB::beginTransaction();

            $currentElement   = $this->taskGroupRepository->getByCol('task_group_id', $input['current']);
            $currentDisplayOrder = $currentElement->display_order;
            $model = $this->taskGroupRepository->getInstance();

            if ($input['previous'] != null && $input['next'] != null) {
                $previousElement   = $this->taskGroupRepository->getByCol('task_group_id', $input['previous']);
                $nextElement   = $this->taskGroupRepository->getByCol('task_group_id', $input['next']);

                $previousDisplayOrder = $previousElement->display_order;
                $nextDisplayOrder = $nextElement->display_order;

                if ($currentDisplayOrder > $nextDisplayOrder) { // down

                    $model->fresh();
                    $model->where('project_id', $currentElement->project_id)
                        ->where('display_order', '>', $nextDisplayOrder)
                        ->where('display_order', '<', $currentDisplayOrder)
                        ->increment('display_order');

                    $currentElement->display_order = $nextDisplayOrder + 1;
                    $currentElement->save();
                } else { // up

                    $model->fresh();
                    $model->where('project_id', $currentElement->project_id)
                        ->where('display_order', '>', $currentDisplayOrder)
                        ->where('display_order', '<', $previousDisplayOrder)
                        ->decrement('display_order');

                    $currentElement->display_order = $previousDisplayOrder - 1;
                    $currentElement->save();
                }
            } else {
                if ($input['next'] == null) { // down
                    // move to bottom screen
                    $model->fresh();
                    $min = $model->where('project_id', $currentElement->project_id)->min('display_order');

                    $model->fresh();
                    $model->where('project_id', $currentElement->project_id)
                        ->where('display_order', '>=', $min)
                        ->where('display_order', '<', $currentDisplayOrder)
                        ->increment('display_order');

                    $currentElement->display_order = $min;
                    $currentElement->save();
                }

                if ($input['previous'] == null) { // up
                    // move to top screen
                    $model->fresh();
                    $max = $model->where('project_id', $currentElement->project_id)->max('display_order');

                    $model->fresh();
                    $model->where('project_id', $currentElement->project_id)
                        ->where('display_order', '>', $currentDisplayOrder)
                        ->where('display_order', '<=', $max)
                        ->decrement('display_order');

                    $currentElement->display_order = $max;
                    $currentElement->save();
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
        }
    }

    public function swapTaskInGroup($input, $filter, $flagFilter)
    {
        $response = $this->initResponse();

        try {
            DB::beginTransaction();

            $currentElement   = $this->taskRepository->getByCol('task_id', $input['current_task_id']);
            $position = null;
            $whereGroup[] = $input['group_current'];
            $sortField = null;

            if ($input['parent_level_to'] == 2) {
                $sortField = 'sub_task_display_order';
            } else {
                $sortField = 'parent_task_display_order';
            }

            $currentDisplayOrder = $currentElement->$sortField;

            if ($input['group_current'] !== $input['group_change']) {
                $whereGroup[] = $input['group_change'];
                $currentElement->task_group_id = $input['group_change'];
            }

            if ($input['previous_task_id'] == null && $input['next_task_id'] == null) {
                $position = 1;
            } else {
                if ($input['previous_task_id'] != null && $input['next_task_id'] != null) {

                    // Move element to new position, middle of two element
                    $previousElement   = $this->taskRepository->getByCol('task_id', $input['previous_task_id']);
                    $nextElement   = $this->taskRepository->getByCol('task_id', $input['next_task_id']);

                    $previousDisplayOrder = $previousElement->$sortField;
                    $nextDisplayOrder = $nextElement->$sortField;

                    $flag = false;

                    if ($input['current_level'] -1 !== $input['parent_level_to']) {
                        $flag = true;
                    } else {
                        if ($input['current_level'] == 3) {
                            if ($currentElement->parent_task_id !== $nextElement->parent_task_id) {
                                $flag = true;
                            }
                        } else {
                            if ($currentElement->task_group_id !== $nextElement->task_group_id) {
                                $flag = true;
                            }
                        }
                    }

                    $model = $this->taskRepository->getInstance();

                    if (($input['group_current'] !== $input['group_change']) || ($flag === true)) {
                        // assign to up position
                        $model->fresh();
                        if ($input['parent_level_to'] == 2) {
                            $model->where('parent_task_id', $input['parent_task_id'])
                                ->where($sortField, '<', $previousDisplayOrder)
                                ->decrement($sortField);
                        } else {
                            $model->where('task_group_id', $nextElement->task_group_id)
                                ->where($sortField, '<', $previousDisplayOrder)
                                ->decrement($sortField);
                        }

                        $position = $previousDisplayOrder - 1;
                    } else { // in one group
                        if ($currentDisplayOrder > $nextDisplayOrder) { // down
                            $model->fresh();
                            if ($input['parent_level_to'] == 2) {
                                $model->where('parent_task_id', $input['parent_task_id'])
                                    ->where($sortField, '>', $nextDisplayOrder)
                                    ->where($sortField, '<', $currentDisplayOrder)
                                    ->increment($sortField);
                            } else {
                                $model->where('task_group_id', $nextElement->task_group_id)
                                    ->where($sortField, '>', $nextDisplayOrder)
                                    ->where($sortField, '<', $currentDisplayOrder)
                                    ->increment($sortField);
                            }

                            $position = $nextDisplayOrder + 1;
                        } else { // up

                            $model->fresh();
                            if ($input['parent_level_to'] == 2) {
                                $model->where('parent_task_id', $input['parent_task_id'])
                                    ->where($sortField, '>', $currentDisplayOrder)
                                    ->where($sortField, '<', $previousDisplayOrder)
                                    ->decrement($sortField);
                            } else {
                                $model->where('task_group_id', $nextElement->task_group_id)
                                    ->where($sortField, '>', $currentDisplayOrder)
                                    ->where($sortField, '<', $previousDisplayOrder)
                                    ->decrement($sortField);
                            }


                            $position = $previousDisplayOrder - 1;
                        }
                    }
                } else {
                    if ($input['previous_task_id'] == null) {

                        // move to top list
                        $model = $this->taskRepository->getInstance();
                        $max = $model->where('task_id', $input['next_task_id'])->max($sortField);
                        $nextElement   = $this->taskRepository->getByCol('task_id', $input['next_task_id']);

                        $flag = false;
                        if ($input['current_level'] -1 !== $input['parent_level_to']) {
                            $flag = true;
                        } else {
                            if ($input['current_level'] == 3) {
                                if ($currentElement->parent_task_id !== $nextElement->parent_task_id) {
                                    $flag = true;
                                }
                            } else {
                                if ($currentElement->task_group_id !== $nextElement->task_group_id) {
                                    $flag = true;
                                }
                            }
                        }


                        if (($input['group_current'] !== $input['group_change']) || ($flag === true)) {
                            $position = $max + 1;
                        } else {
                            if ($input['parent_level_to'] == 2) {
                                $model->where('parent_task_id', $input['parent_task_id'])
                                    ->where($sortField, '>', $currentDisplayOrder)
                                    ->where($sortField, '<=', $max)
                                    ->decrement($sortField);
                            } else {
                                $model->where('task_group_id', $currentElement->task_group_id)
                                    ->where($sortField, '>', $currentDisplayOrder)
                                    ->where($sortField, '<=', $max)
                                    ->decrement($sortField);
                            }

                            $position = $max;
                        }
                    }

                    if ($input['next_task_id'] == null) {
                        // move to bottom of list
                        $model = $this->taskRepository->getInstance();
                        $min = $model->where('task_id', $input['previous_task_id'])->min($sortField);
                        $previousElement   = $this->taskRepository->getByCol('task_id', $input['previous_task_id']);

                        $flag = false;
                        if ($input['current_level'] -1 !== $input['parent_level_to']) {
                            $flag = true;
                        } else {
                            if ($input['current_level'] == 3) {
                                if ($currentElement->parent_task_id !== $previousElement->parent_task_id) {
                                    $flag = true;
                                }
                            } else {
                                if ($currentElement->task_group_id !== $previousElement->task_group_id) {
                                    $flag = true;
                                }
                            }
                        }

                        if (($input['group_current'] !== $input['group_change']) || ($flag === true)) {
                            $position = $min - 1;
                        } else {
                            if ($input['parent_level_to'] == 2) {
                                $model->where('parent_task_id', $input['parent_task_id'])
                                    ->where($sortField, '>=', $min)
                                    ->where($sortField, '<', $currentDisplayOrder)
                                    ->increment($sortField);
                            } else {
                                $model->where('task_group_id', $currentElement->task_group_id)
                                    ->where($sortField, '>=', $min)
                                    ->where($sortField, '<', $currentDisplayOrder)
                                    ->increment($sortField);
                            }

                            $position = $min;
                        }
                    }
                }
            }

            if ($input['parent_level_to'] == 2) { // sub task
                $currentElement->parent_task_id = $input['parent_task_id'];
                $currentElement->parent_task_display_order = null;
                $currentElement->sub_task_display_order = $position;
            } else {
                $currentElement->parent_task_id = null;
                $currentElement->parent_task_display_order = $position;
                $currentElement->sub_task_display_order = null;
            }

            $currentElement->save();

            $data = $this->taskGroupRepository->getTaskGroupByProjectV5($currentElement->project_id, $filter, $flagFilter, $whereGroup);
            $response['data'] = $data;

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            $response = $this->exceptionError();
        }

        return $response;
    }
}
