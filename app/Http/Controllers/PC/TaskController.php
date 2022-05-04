<?php

namespace App\Http\Controllers\PC;

use App\Exports\ExportTask;
use App\Http\Requests\Project\GetListLogProjectRequest;
use App\Http\Requests\Project\ProjectRequest;
use App\Services\ProjectService;
use App\Services\TaskService;
use App\Services\GoodService;
use App\Services\CommentService;
use App\Http\Controllers\Controller;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Repositories\TaskRepository;
use App\Services\TrashService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\UserTaskService;
use App\Services\TaskStatusService;
use App\Http\Requests\Task\TaskFormRequest;
use Illuminate\Support\Facades\Auth;
use App\Repositories\ProjectRepository;
use App\Repositories\CheckListRepository;
use App\Repositories\RemindRepository;
use App\Repositories\WatchListRepository;
use App\Repositories\DisclosureRangeRepository;
use App\Repositories\PriorityMstRepository;
use App\Services\TaskGroupService;
use App\Services\ProjectLogService;
use App\Services\WatchListService;
use App\Http\Requests\Task\DeleteWatchListRequest;
use App\Http\Requests\Task\DetailTaskRequest;
use App\Http\Requests\CheckList\CheckListFormRequest;
use App\Http\Requests\Remind\RemindFormRequest;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\Project\CopyTaskRequest;
use App\Events\UpdateTaskDetailEvent;
use App\Models\Project;
use App\Models\Task;

class TaskController extends Controller
{
    protected $taskStatusService;
    protected $taskService;
    protected $trashService;
    protected $taskRepo;
    protected $projectRepo;
    protected $disclosureRangeRepo;
    protected $priorityMstRepo;
    protected $taskGroupService;
    protected $watchListService;
    private $watchListRepo;
    private $checkListRepo;
    private $remindRepo;
    private $projectLogService;
    private $userService;
    private $projectService;
    private $commentService;

    public function __construct(
        TaskStatusService $taskStatusService,
        TrashService $trashService,
        TaskService $taskService,
        TaskRepository $taskRepo,
        GoodService $goodService,
        UserTaskService $userTaskService,
        ProjectRepository $projectRepo,
        DisclosureRangeRepository $disclosureRangeRepo,
        PriorityMstRepository $priorityMstRepo,
        TaskGroupService $taskGroupService,
        WatchListRepository $watchListRepo,
        WatchListService $watchListService,
        CheckListRepository $checkListRepo,
        RemindRepository $remindRepo,
        ProjectLogService $projectLogService,
        UserService $userService,
        ProjectService $projectService,
        CommentService $commentService
    ) {
        $this->taskStatusService = $taskStatusService;
        $this->trashService = $trashService;
        $this->userTaskService = $userTaskService;
        $this->goodService = $goodService;
        $this->taskService = $taskService;
        $this->taskRepo = $taskRepo;
        $this->projectRepo = $projectRepo;
        $this->disclosureRangeRepo = $disclosureRangeRepo;
        $this->priorityMstRepo = $priorityMstRepo;
        $this->taskGroupService = $taskGroupService;
        $this->watchListService = $watchListService;
        $this->watchListRepo = $watchListRepo;
        $this->checkListRepo = $checkListRepo;
        $this->remindRepo = $remindRepo;
        $this->projectLogService = $projectLogService;

        $this->userService = $userService;
        $this->projectService = $projectService;

        $this->commentService = $commentService;
    }

    /**
     * Get detail task
     *
     * @param  DetailTaskRequest $request
     * @return json
     */
    public function detail(DetailTaskRequest $request, $id)
    {
        return response()->json($this->taskService->detailTask($id, $request->input('user_id')));
    }
    public function create(TaskFormRequest $request)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            return $this->baseService->sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
        }

        $result = $this->taskService->create($request, $currentUser);

        return $result;
    }
    public function edit(TaskFormRequest $request)
    {
        // check exists task

        $taskId=$request->input("task_id");
        // return $taskId;
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

        $currentUser = Auth::user();
        if (!$currentUser) {
            return $this->baseService->sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
        }

        $result = $this->taskService->edit($task, $request, $currentUser);

        // broadcast
        if ($result['status'] === config('apps.general.success')) {
            $dataBroadcast = [
                'task_id' => $taskId,
                'field' => $request->input('task_name') ? 'task_name' : '',
                'value' =>  $request->input('task_name') ?? '',
                'task_data' => $result['data'],
                'update_user_id' => $currentUser->user_id
            ];

            broadcast(new UpdateTaskDetailEvent($currentUser, UpdateTaskDetailEvent::MODE_UPDATE, $dataBroadcast));
        }

        return $result;
    }

    /**
     * Update Start task status
     *
     * @param  DetailTaskRequest $request
     * @return json
     */
    public function updateStartTask(DetailTaskRequest $request)
    {
        $currentUser = Auth::user();

        $result = $this->taskService->updateStartTask($request->input('task_id'), $currentUser->user_id);

        return response()->json($result);
    }


    /**
     * Update complete task status
     *
     * @param  DetailTaskRequest $request
     * @return JsonResponse
     */
    public function updateCompleteTask(DetailTaskRequest $request)
    {
        $currentUser = Auth::user();

        // check exists task
        $task = $this->taskRepo->getByCols([
            'task_id' => $request->input('task_id'),
            'delete_flg'        => config('apps.general.not_deleted')
        ]);
        if (!$task) {
            return $this->baseService->sendError(
                [trans('message.ERR_COM_0011', ['attribute' => 'label.general.task_id'])],
                [],
                config('apps.general.error_code', 600)
            );
        }

        $result = $this->taskService->updateCompleteTask($task, $currentUser->user_id, $request->complete_subtask);

        return response()->json($result);
    }

    /**
     * Add task to watch list
     *
     * @param  DetailTaskRequest $request
     * @return json
     */
    public function addTaskToWatchList(DetailTaskRequest $request)
    {
        $currentUser = Auth::user();

        $validator = Validator::make(request()->all(), [
            'task_id' => [
                'required',
                Rule::exists('t_task', 'task_id')->where(function ($query) {
                    return $query->where('delete_flg', config('apps.general.not_deleted'));
                })
            ],
        ], [
            'task_id.required' => trans('message.ERR_COM_0001', [ 'attribute' => trans('label.general.task_id') ]),
            'task_id.exists'   => trans('message.ERR_COM_0011', [ 'attribute' => trans('label.general.task_id') ]),
        ]);

        if ($validator->fails()) {
            return $this->baseService->sendError($validator->errors()->all(), [], config('apps.general.error_code', 600));
        }

        // check exists task
        $task = $this->taskRepo->getByCols([
            'task_id'       => $request->input('task_id'),
            'delete_flg'    => config('apps.general.not_deleted')
        ]);
        if (!$task) {
            return $this->baseService->sendError(
                [trans('message.ERR_COM_0011', ['attribute' => trans('label.general.task_id') ])],
                [],
                config('apps.general.error_code', 600)
            );
        }

        if ($currentUser) {
            $result = $this->userTaskService->addTaskToWatchList($task, $currentUser->user_id);
        } else {
            $result = $this->userTaskService->addTaskToWatchList($task, $request->input('user_id'));
        }

        return response()->json($result);
    }

    /**
     * Delete watch list
     *
     * @param  DeleteWatchListRequest $request
     * @return json
     */
    public function deleteWatchList(DeleteWatchListRequest $request)
    {
        $currentUser = Auth::user();
        // check exists task
        $user_id = $currentUser->user_id;

        $task = $this->taskService->detailTask($request->input('task_id'), $user_id);

        if ($task['status'] !== config('apps.general.success')) {
            return $this->sendError(
                [trans('message.ERR_COM_0011', ['attribute' => trans('label.general.task_id') ])]
            );
        }

        // check exists watchlist has task
        $record = $this->watchListService->checkRecord($request->input('task_id'));

        if ($record['status'] === config('apps.general.error')) {
            return response()->json($record);
        }
        $taskId = $request->input('task_id');
        $watchList = $this->watchListRepo->getByCols([
            'task_id' => $taskId,
            'user_id' => $user_id,
            'delete_flg' => config('apps.general.not_deleted')
        ]);
        if (empty($watchList->task_id)) {
            return $this->sendError(
                [trans('validation.object_not_exist', ['object' => trans('label.watch_list.task')])]
            );
        }
        $result = $this->watchListService->deleteWatchList($watchList, $user_id);

        return response()->json($result);
    }

    /**
     * Update user like task
     *
     * @param  DetailTaskRequest $request
     * @return json
     */
    public function updateUserLikeTask(DetailTaskRequest $request)
    {
        $currentUser = Auth::user();

        $is_like_key_list = array_keys(config('apps.general.is_like'));
        $validator = Validator::make(request()->all(), [
            'task_id' => [
                'required',
                Rule::exists('t_task', 'task_id')->where(function ($query) {
                    return $query->where('delete_flg', config('apps.general.not_deleted'));
                })
            ],
            'is_like' => ['required', 'integer', 'in:'.implode(',', $is_like_key_list)],
        ], [
            'task_id.required' => trans('message.ERR_COM_0001', [ 'attribute' => trans('label.general.task_id') ]),
            'task_id.exists'   => trans('message.ERR_COM_0011', [ 'attribute' => trans('label.general.task_id') ]),
            'is_like.required' => trans('message.ERR_COM_0001', [ 'attribute' => trans('label.general.is_like') ]),
            'is_like.integer'  => trans('message.INF_COM_0005', [ 'attribute' => trans('label.general.is_like') ]),
            'is_like.in'       => trans('validation.in', [ 'attribute' => trans('label.general.is_like') ]),
        ]);

        if ($validator->fails()) {
            return $this->baseService->sendError($validator->errors()->all(), [], config('apps.general.error_code', 600));
        }

        // check exists task
        $task = $this->taskRepo->getByCols([
            'task_id'       => $request->input('task_id'),
            'delete_flg'    => config('apps.general.not_deleted')
        ]);
        if (!$task) {
            return $this->baseService->sendError(
                [trans('message.ERR_COM_0011', ['attribute' => trans('label.general.task_id') ])],
                [],
                config('apps.general.error_code', 600)
            );
        }

        if ($currentUser) {
            $result = $this->goodService->userLikeTask($currentUser->user_id, $request);
        } else {
            $result = $this->goodService->userLikeTask($request->input('user_id'), $request);
        }

        return response()->json($result);
    }

    /**
     * Remove task
     *
     * @param  DetailTaskRequest $request
     * @return json
     */
    public function moveTaskToTrash(DetailTaskRequest $request)
    {
        $currentUser = Auth::user();

        // check exists task
        $task = $this->taskRepo->getByCols([
            'task_id'       => $request->input('task_id'),
            'delete_flg'    => config('apps.general.not_deleted')
        ]);
        if (!$task) {
            return $this->baseService->sendError(
                [trans('message.ERR_COM_0011', ['attribute' => trans('label.general.task_id') ])],
                [],
                config('apps.general.error_code', 600)
            );
        }

        if ($currentUser) {
            $result = $this->trashService->moveTaskToTrash($currentUser->user_id, $task);
        } else {
            $result = $this->trashService->moveTaskToTrash($request->input('user_id'), $task);
        }

        return response()->json($result);
    }

    public function searchMemberByName(Request $request)
    {
        $currentUser = Auth::user();
        $company_id = $currentUser->company_id;
        $key_word = $request->input('key_word');

        $result = $this->taskService->searchMemberByName($company_id, $key_word);

        return $result;
    }

    public function getGroupByProject(Request $request)
    {
        $project_id = $request->input('project_id');
        $project = $this->projectRepo->getByCols([
            'project_id' => $project_id,
            'delete_flg' => config('apps.general.not_deleted')
        ]);

        if (!$project) {
            return redirect()->back()->with('error', trans('message.INF_COM_0009', ['attribute' => trans('validation_attribute.t_project')]));
        }

        $getListDisclosureRange = $this->disclosureRangeRepo->all([], ['by' => 'display_order', 'type' => 'DESC'], [], ['disclosure_range_id', 'disclosure_range_name', 'display_order']);
        $getListPriority = $this->priorityMstRepo->all([], ['by' => 'display_order', 'type' => 'DESC'], [], ['priority_id','priority_name','display_order']);
        $taskGroups   = $this->taskGroupService->getTaskGroup($project_id);
        $data = [
            'disclosureRange' => $getListDisclosureRange,
            'priority' => $getListPriority,
            'taskGroups' => $taskGroups['data'],
        ];
        return $data;
    }

    public function copyTask(CopyTaskRequest $request)
    {
        $currentUserId = Auth::user()->user_id;
        $taskId = $request->get('task_id');
        $dataStatus = $request->only(['task_group_id', 'task_name', 'project_id', 'priority_id','disclosure_range_id', 'scheduled_date', 'sub_task', 'attachment_file', 'check_list', 'task_memo',]);
        $dataStatus['user_id'] = $currentUserId;
        $taskNew = $this->taskStatusService->copyTask($currentUserId, $taskId, $dataStatus);
        if (empty($taskNew) || isset($taskNew['status']) == config('apps.general.error')) {
            return $this->respondWithError([trans('message.NOT_COMPLETE')]);
        }

        return $this->respondSuccess([trans('message.COMPLETE')], $taskNew);
    }
    public function createOrUpdateCheckList(CheckListFormRequest $request)
    {
        $currentUser = Auth::user();
        $result = $this->taskService->createOrUpdateCheckList($request, $currentUser);
        return $result;
    }

    public function createOrUpdateRemind(RemindFormRequest $request)
    {
        $currentUser = Auth::user();
        $result = $this->taskService->createOrUpdateRemind($request, $currentUser);
        return $result;
    }

    public function removeCheckList(Request $request)
    {
        // check exists check list
        $checkList = $this->checkListRepo->getByCols([
            'check_list_id'     => $request->check_list_id,
            'delete_flg'        => config('apps.general.not_deleted')
        ]);
        if (!$checkList) {
            return $this->baseService->sendError(
                [trans('message.ERR_COM_0011', ['attribute' => trans('label.task.check_list')])],
                [],
                config('apps.general.error_code', 600)
            );
        }

        $currentUser = Auth::user();

        $result = $this->taskService->removeCheckList($checkList, $currentUser);

        return $result;
    }

    public function removeRemind(Request $request)
    {
        // check exists check list
        $remind = $this->remindRepo->getByCols([
            'remaind_id'     => $request->remaind_id,
            'delete_flg'        => config('apps.general.not_deleted')
        ]);
        if (!$remind) {
            return $this->baseService->sendError(
                [trans('message.ERR_COM_0011', ['attribute' => trans('label.task.remaind')])],
                [],
                config('apps.general.error_code', 600)
            );
        }

        $currentUser = Auth::user();

        $result = $this->taskService->removeRemind($remind, $currentUser);

        return $result;
    }

    public function getListLogTask(GetListLogProjectRequest $request)
    {
        $projectId = $request->get('project_id');
        $taskId = $request->get('task_id');
        $identifyCode = $request->input('identifying_code', []);
        $getListLogTasks = $this->projectLogService->getLog($projectId, $identifyCode, $taskId);

        $result['last_page'] = $getListLogTasks['data']->lastPage();
        $result['data'] = \View::make("project.response.task_list_logs")
            ->with("getListLogTasks", $getListLogTasks['data'])
            ->render();

        return $result;
    }

    public function subTaskDetail(DetailTaskRequest $request)
    {
        $result = $this->taskService->detail($request->input('task_id'));

        if ($result['status'] === config('apps.general.error')) {
            return $result;
        }

        $getListDisclosureRange = $this->disclosureRangeRepo->all([], ['by' => 'display_order', 'type' => 'DESC'], [], ['disclosure_range_id', 'disclosure_range_name', 'display_order']);
        $getListPriority = $this->priorityMstRepo->all([], ['by' => 'display_order', 'type' => 'DESC'], [], ['priority_id','priority_name','display_order']);
        $taskGroups['data'] = [];
        $getListLogTasks['data'] = [];
        $getListProjects['data'] = [];
        if ($result['data']->project_id) {
            $taskGroups = $this->taskGroupService->getTaskGroup($result['data']->project_id);
            $getListLogTasks = $this->projectLogService->getLog($result['data']->project_id, [4], $result['data']->task_id);
        }
        $taskComments = $this->commentService->getListComment($request->get('task_id'));
        if ($result['data']->user) {
            $getListProjects = $this->projectService->getProjectByUser($result['data']->user->user_id);
        }
        $project = $this->projectRepo->getInstance()::where('project_id', $result['data']->project_id)->first();
        $taskDetail = $this->taskService->getAdditionalInforTask($result['data']);
        $data = [
            "subTask" => $taskDetail,
            "taskGroups" => $taskGroups['data'],
            'disclosureRange' => $getListDisclosureRange,
            'priority' => $getListPriority,
            'taskComments' => $taskComments['data'],
            'listLogTasks' => $getListLogTasks['data'],
            'listProjects' => $getListProjects['data'],
            'project'=>$project,
        ];

        return $data;
    }

    /**
     * Get task by project
     *
     * @param ProjectRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
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

        $flagFilter = false;

        if ($request->has('display_mode')) {
            $filter['display_mode'] = $request->input('display_mode');
        }

        if ($request->has('status') && is_array($request->input('status')) && count($request->input('status')) > 0) {
            $filter['status'] = $request->input('status');
            $flagFilter = true;
        }

        if ($request->has('priority') && is_array($request->input('priority')) && count($request->input('priority')) > 0) {
            $filter['priority'] = $request->input('priority');
            $flagFilter = true;
        }

        if ($request->has('manager') && is_array($request->input('manager')) && count($request->input('manager')) > 0) {
            $filter['manager'] = $request->input('manager');
            $flagFilter = true;
        }

        if ($request->has('author') && is_array($request->input('author')) && count($request->input('author')) > 0) {
            $filter['author'] = $request->input('author');
            $flagFilter = true;
        }

        if ($request->has('watch_list')) {
            $watchList = $request->input('watch_list');
            if ($watchList == config('apps.general.watch_list')) {
                $filter['watch_list'] = true;
                $flagFilter = true;
            }
        }

        switch ($filter['display_mode']) {
            case config('apps.task.display_mode.group'):
                $result = $this->taskGroupService->fetchTaskGroupByProject($request->input('project_id'), $filter, $flagFilter);
                break;

            case config('apps.task.display_mode.manager'):
                $result = $this->userService->fetchTaskManagerByProject($request->input('project_id'), $filter, $flagFilter);
                break;

            default:
                $result = $this->taskService->fetchTaskByProject($request->input('project_id'), $filter, $flagFilter);
        }

        return response()->json($result);
    }

    /**
     * Export task of project
     *
     * @param ProjectRequest $request
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportTask(ProjectRequest $request)
    {
        $filter = [
            'group'  => null,
            'task'  => null,
        ];

        // check project
        $record = $this->projectService->checkRecord($request->input('project_id'));
        if ($record['status'] === config('apps.general.error')) {
            return response()->json($record);
        }

        // get input data
        if ($request->has('group')) {
            $group = $request->input('group');
            if (count(json_decode($group)) > 0) {
                $filter['group'] = json_decode($group);
            }
        }
        if ($request->has('task')) {
            $task = $request->input('task');
            if (count(json_decode($task)) > 0) {
                $filter['task'] = json_decode($task);
            }
        }

        // get data
        $result = $this->taskGroupService->getDataExport($request->input('project_id'), $filter);
        if ($result['status'] === config('apps.general.error')) {
            return response()->json($result);
        }

        return Excel::download(new ExportTask($result['data']), 'task.csv');
    }



    private $baseService;
    private $goodService;
}
