<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\TaskRequest;
use App\Repositories\WatchListRepository;
use App\Services\BaseService;
use App\Services\TaskService;
use App\Services\WatchListService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WatchListController extends Controller
{
    protected $baseService;
    protected $watchListService;
    protected $taskService;
    private $watchListRepo;
    public function __construct(
        BaseService $baseService,
        WatchListService $watchListService,
        TaskService $taskService,
        WatchListRepository $watchListRepo
    ) {
        $this->baseService = $baseService;
        $this->watchListService = $watchListService;
        $this->taskService = $taskService;
        $this->watchListRepo = $watchListRepo;
    }

    public function deleteWatchList(TaskRequest $request)
    {
        $currentUser = auth('api')->user();

        // check exists task
        $task = $this->taskService->detail($request->input('task_id'));

        if ($task['status'] !== config('apps.general.success')) {
            return $this->baseService->sendError(
                [trans('message.ERR_COM_0011', ['attribute' => trans('label.general.task_id') ])],
                [],
                config('apps.general.error_code')
            );
        }

        $taskId = $request->input('task_id');
        $watchList = $this->watchListRepo->getByCols([
            'task_id' => $taskId,
            'user_id' => $currentUser->user_id,
            'delete_flg' => config('apps.general.not_deleted')
        ]);
        if (empty($watchList->task_id)) {
            return $this->baseService->sendError(
                [trans('validation.object_not_exist', ['object' => trans('label.watch_list.task')])],
                [],
                config('apps.general.error_code')
            );
        }

        $result = $this->watchListService->deleteWatchList($watchList, $currentUser->user_id);

        return response()->json($result);
    }
}
