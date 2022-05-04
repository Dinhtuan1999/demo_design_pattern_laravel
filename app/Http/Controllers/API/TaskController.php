<?php

namespace App\Http\Controllers\API;

use App\Helpers\Transformer;
use App\Http\Controllers\API\Controller;
use App\Http\Requests\Project\CopyTaskRequest;
use App\Http\Requests\Project\ProjectRequest;
use App\Http\Requests\Task\TaskDateRequest;
use App\Http\Requests\Task\TaskRequest;
use App\Services\TaskGroupService;
use App\Services\TaskService;
use App\Services\TaskStatusService;
use App\Services\UserService;
use App\Services\ValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Services\BaseService;
use App\Repositories\ProjectRepository;
use App\Repositories\TaskGroupRepository;
use App\Repositories\UserRepository;
use App\Repositories\PriorityMstRepository;
use App\Repositories\DisclosureRangeRepository;
use App\Repositories\TaskStatusRepository;
use App\Repositories\TaskRepository;
use App\Http\Requests\Project\GetListLogProjectRequest;
use App\Http\Requests\Task\TaskFormRequest;
use App\Repositories\CheckListRepository;
use App\Repositories\RemindRepository;
use App\Transformers\TaskGroup\TaskGroupDetailTransformer;

class TaskController extends Controller
{
    protected $taskService;
    protected $taskStatusService;
    protected $taskGroupService;
    protected $userService;
    protected $validationService;
    protected $baseService;
    protected $projectRepo;
    protected $taskGroupRepo;
    protected $userRepo;
    protected $priorityMstRepo;
    protected $disclosureRangeRepo;
    protected $taskStatusRepo;
    protected $taskRepo;
    protected $checkListRepo;
    protected $remindRepo;

    public function __construct(
        TaskService $taskService,
        TaskStatusService $taskStatusService,
        TaskGroupService $taskGroupService,
        UserService $userService,
        ValidationService $validationService,
        BaseService $baseService,
        ProjectRepository $projectRepo,
        TaskGroupRepository $taskGroupRepo,
        UserRepository $userRepo,
        PriorityMstRepository $priorityMstRepo,
        DisclosureRangeRepository $disclosureRangeRepo,
        TaskStatusRepository $taskStatusRepo,
        TaskRepository $taskRepo,
        CheckListRepository $checkListRepo,
        RemindRepository $remindRepo
    ) {
        $this->taskService = $taskService;
        $this->taskStatusService = $taskStatusService;
        $this->taskGroupService = $taskGroupService;
        $this->userService = $userService;
        $this->validationService = $validationService;
        $this->baseService = $baseService;
        $this->projectRepo = $projectRepo;
        $this->taskGroupRepo = $taskGroupRepo;
        $this->userRepo = $userRepo;
        $this->priorityMstRepo = $priorityMstRepo;
        $this->disclosureRangeRepo = $disclosureRangeRepo;
        $this->taskStatusRepo = $taskStatusRepo;
        $this->taskRepo = $taskRepo;
        $this->checkListRepo = $checkListRepo;
        $this->remindRepo = $remindRepo;
    }

    public function updateCompleteTask(TaskRequest $request)
    {
        $currentUser = auth('api')->user();
        if (!$currentUser) {
            return $this->baseService->sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
        }

        // check exists task
        $task = $this->taskRepo->getByCol('task_id', $request->input('task_id'), );
        if (!$task) {
            return $this->baseService->sendError(
                [trans('message.ERR_COM_0011', ['attribute' => trans('label.task.task_name')])],
                [],
                config('apps.general.error_code', 600)
            );
        }
        if ($task->delete_flg == config('apps.general.is_deleted')) {
            return $this->baseService->sendError(
                [trans('message.ERR_COM_0011', ['attribute' => $task->task_name])],
                [],
                config('apps.general.error_code', 600)
            );
        }

        $result = $this->taskService->updateCompleteTask($task, $currentUser->user_id, $request->complete_subtask);

        return response()->json($result);
    }

    public function updateStartTask(TaskRequest $request)
    {
        $currentUser = auth('api')->user();
        $result = $this->taskService->updateStartTask($request->input('task_id'), $currentUser->user_id);

        return response()->json($result);
    }

    public function detail(Request $request, $id)
    {
        return response()->json($this->taskService->detail($id));
    }

    public function getAllTaskStatus()
    {
        return response()->json($this->taskStatusService->getListTaskStatus());
    }

    public function updateTaskDate(TaskDateRequest $request)
    {
        $currentUser = auth('api')->user();

        $result = $this->taskService->updateTaskDate(
            $request->input('task_id'),
            $request->input('start_date'),
            $request->input('end_date'),
            $currentUser->user_id
        );

        return response()->json($result);
    }

    public function getTaskByProject(ProjectRequest $request)
    {
        $filter = [
            'display_mode'  => null,
            'status'  => null,
            'priority'  => null,
            'manager'  => null,
            'author'  => null,
            'watch_list'  => null,
        ];

        if ($request->has('display_mode')) {
            $filter['display_mode'] = $request->input('display_mode');
        }

        if ($request->has('status') && is_array($request->input('status'))) {
            if (count($request->input('status')) > 0) {
                $filter['status'] = $request->input('status');
            }
        }

        if ($request->has('priority') && is_array($request->input('priority'))) {
            if (count($request->input('priority')) > 0) {
                $filter['priority'] = $request->input('priority');
            }
        }

        if ($request->has('manager') && is_array($request->input('manager'))) {
            if (count($request->input('manager')) > 0) {
                $filter['manager'] = $request->input('manager');
            }
        }

        if ($request->has('author') && is_array($request->input('author'))) {
            if (count($request->input('author')) > 0) {
                $filter['author'] = $request->input('author');
            }
        }

        if ($request->has('watch_list')) {
            $watchList = $request->input('watch_list');
            if ($watchList == config('apps.general.watch_list')) {
                $filter['watch_list'] = true;
            }
        }

        switch ($filter['display_mode']) {
            case config('apps.task.display_mode.group'):
                $taskGroups = $this->taskGroupService->getTaskGroupDetailByProjectIdV2($request->project_id, $filter);
                return $this->respondSuccess(trans('message.SUCCESS'), Transformer::collection(new TaskGroupDetailTransformer(), $taskGroups));
            case config('apps.task.display_mode.manager'):
                $result = $this->userService->getUserByProject($request->input('project_id'), $filter);
                break;
            default:
                $result = $this->taskService->getTaskByProject($request->input('project_id'), $filter);
        }
        return response()->json($result);
    }

    public function getListTaskByGroup(Request $request)
    {
        $currentUser = Auth::guard('api')->user();
        if (!$currentUser) {
            return $this->baseService->sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
        }

        $result = $this->taskService->getListTaskByGroup($request, $currentUser);

        return response()->json($result);
    }

    public function create(TaskFormRequest $request)
    {
        $currentUser = Auth::guard('api')->user();
        if (!$currentUser) {
            return $this->baseService->sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
        }

        $result = $this->taskService->create($request, $currentUser);

        return response()->json($result);
    }

    public function edit($taskId, TaskFormRequest $request)
    {
        // check exists task
        $task = $this->taskRepo->getByCols([
            'task_id'       => $taskId,
            'delete_flg'    => config('apps.general.not_deleted')
        ]);
        if (!$task) {
            return $this->baseService->sendError(
                [trans('message.ERR_COM_0011', ['attribute' => trans('label.general.task_id') ])],
                [],
                config('apps.general.error_code', 600)
            );
        }

        $currentUser = Auth::guard('api')->user();
        if (!$currentUser) {
            return $this->baseService->sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
        }

        $result = $this->taskService->edit($task, $request, $currentUser);

        return response()->json($result);
    }

    public function getGraphDetail(GetListLogProjectRequest $request)
    {
        // 1. Get project_id from Parameter
        $projectId = $request->get('project_id');
        // 2. Get task_status_id from Parameter
        $taskStatusId = $request->get('task_status_id');
        // 3. Call to taskService with getGraphDetail function
        $result = $this->taskService->getGraphDetail($projectId, $taskStatusId);

        return response()->json($result);
    }

    public function copyTask(CopyTaskRequest $request)
    {
        $currentUserId = Auth::user()->user_id;
        $taskId = $request->get('task_id');
        $dataStatus = $request->only(['user_id', 'task_group_id', 'task_name', 'project_id', 'priority_id','disclosure_range_id', 'scheduled_date', 'sub_task', 'attachment_file', 'check_list', 'task_memo',]);

        $taskNew = $this->taskStatusService->copyTask($currentUserId, $taskId, $dataStatus);

        if (empty($taskNew)) {
            return $this->respondWithError([trans('message.NOT_COMPLETE')]);
        }
        if (isset($taskNew['status']) == config('apps.general.error')) {
            return response()->json($taskNew);
        }

        return $this->respondSuccess([trans('message.COMPLETE')], $taskNew);
    }

    public function removeCheckList($checkListId, Request $request)
    {
        // check exists check list
        $checkList = $this->checkListRepo->getByCols([
            'check_list_id'     => $checkListId,
            'delete_flg'        => config('apps.general.not_deleted')
        ]);
        if (!$checkList) {
            return $this->baseService->sendError(
                [trans('message.ERR_COM_0011', ['attribute' => trans('label.task.check_list')])],
                [],
                config('apps.general.error_code', 600)
            );
        }

        $currentUser = Auth::guard('api')->user();
        if (!$currentUser) {
            return $this->baseService->sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
        }

        $result = $this->taskService->removeCheckList($checkList, $currentUser);

        return response()->json($result);
    }

    public function removeRemind($remindId, Request $request)
    {
        // check exists Remind
        $remind = $this->remindRepo->getByCols([
            'remaind_id'        => $remindId,
            'delete_flg'        => config('apps.general.not_deleted')
        ]);
        if (!$remind) {
            return $this->baseService->sendError(
                [trans('message.ERR_COM_0011', ['attribute' => trans('label.task.remaind')])],
                [],
                config('apps.general.error_code', 600)
            );
        }

        $currentUser = Auth::guard('api')->user();
        if (!$currentUser) {
            return $this->baseService->sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
        }

        $result = $this->taskService->removeRemind($remind, $currentUser);

        return response()->json($result);
    }
}
