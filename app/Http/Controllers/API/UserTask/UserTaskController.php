<?php

namespace App\Http\Controllers\API\UserTask;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\TaskExistsRequest;
use App\Http\Requests\Task\UserLikeTaskRequest;
use App\Services\GoodService;
use App\Services\UserTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\BaseService;
use Illuminate\Validation\Rule;
use App\Repositories\TaskRepository;

class UserTaskController extends Controller
{
    public function __construct(
        UserTaskService $userTaskService,
        GoodService $goodService,
        BaseService $baseService,
        TaskRepository $taskRepo
    ) {
        $this->userTaskService = $userTaskService;
        $this->goodService = $goodService;
        $this->baseService = $baseService;
        $this->taskRepo = $taskRepo;
    }

    public function getMyTask(Request $request)
    {
        $currentUser = auth('api')->user();
        $data = $this->userTaskService->getTaskByUser($request, $currentUser->user_id);
        $data['data'] = !empty($data['data']['data']) ? $data['data']['data'] : [];
        return response()->json($data);
    }

    public function addTaskToWatchList(TaskExistsRequest $request)
    {
        $currentUser = auth('api')->user();
        if (!$currentUser) {
            return $this->baseService->sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
        }

        // check exists task
        $task = $this->taskRepo->getByCol('task_id', $request->input('task_id'));
        if ($task->delete_flg == config('apps.general.is_deleted')) {
            return $this->baseService->sendError(
                [trans('message.ERR_COM_0011', ['attribute' => $task->task_name])],
                [],
                config('apps.general.error_code', 600)
            );
        }

        $result = $this->userTaskService->addTaskToWatchList($task, $currentUser->user_id);

        return response()->json($result);
    }

    public function updateUserLikeTask(UserLikeTaskRequest $request)
    {
        $currentUser = auth('api')->user();
        if (!$currentUser) {
            return $this->baseService->sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
        }

        // check exists task
        $task = $this->taskRepo->getByCol('task_id', $request->input('task_id'));
        if ($task->delete_flg == config('apps.general.is_deleted')) {
            return $this->baseService->sendError(
                [trans('message.ERR_COM_0011', ['attribute' => $task->task_name])],
                [],
                config('apps.general.error_code', 600)
            );
        }

        $result = $this->goodService->userLikeTask($currentUser->user_id, $request);

        return response()->json($result);
    }

    private $userTaskService;
    private $goodService;
    private $baseService;
    private $taskRepo;
}
