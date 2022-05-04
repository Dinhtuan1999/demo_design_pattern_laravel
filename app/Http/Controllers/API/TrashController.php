<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\TaskExistsRequest;
use App\Http\Requests\TaskGroup\TaskGroupRequest;
use App\Http\Requests\Trash\RestoreFromTrashRequest;
use App\Http\Requests\Trash\DeleteTaskTrashRequest;
use App\Repositories\TrashRepository;
use App\Services\TrashService;
use Illuminate\Http\Request;
use App\Http\Requests\User\ListTrashTaskRequest;
use Illuminate\Support\Facades\Validator;
use App\Services\BaseService;
use Illuminate\Validation\Rule;
use App\Repositories\TaskRepository;

class TrashController extends Controller
{
    protected $trashService;
    protected $baseService;
    protected $taskRepo;
    private $trashRepo;

    public function __construct(
        TrashService $trashService,
        BaseService $baseService,
        TaskRepository $taskRepo,
        TrashRepository $trashRepo
    ) {
        $this->trashService = $trashService;
        $this->baseService = $baseService;
        $this->taskRepo = $taskRepo;
        $this->trashRepo = $trashRepo;
    }

    public function getListTrashTask(ListTrashTaskRequest $request)
    {
        $result = $this->trashService->getListTrashTask($request);

        return response($result);
    }

    public function moveTaskToTrash(TaskExistsRequest $request)
    {
        $currentUser = auth('api')->user();
        if (!$currentUser) {
            return $this->baseService->sendError([trans('message.FAIL')]);
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

        $result = $this->trashService->moveTaskToTrash($currentUser->user_id, $task);

        return response()->json($result);
    }

    public function moveTaskGroupToTrash(TaskGroupRequest $request)
    {
        $currentUser = auth('api')->user();

        $result = $this->trashService->moveTaskGroupToTrash($currentUser->user_id, $request->input('task_group_id'));

        return response()->json($result);
    }

    public function restoreFromTrash(Request $request)
    {
        $trash = $this->trashRepo->getByCol('trash_id', $request->input('trash_id'));
        if (!$trash) {
            return $this->baseService->sendError(trans('validation.object_not_exist', ['object' => trans('label.trash.title')]));
        }
        $currentUser = auth('api')->user();
        return $this->trashService->restoreFromTrash($request->trash_id, $currentUser->user_id);
    }

    public function permanentlyDelete(DeleteTaskTrashRequest $request)
    {
        $currentUser = auth('api')->user();
        $result = $this->trashService->permanentlyDelete($request->trash_id, $currentUser->user_id);

        return response($result);
    }


    public function getListTrash(Request $request)
    {
        $response = [];
        try {
            $currentUser = auth('api')->user();
            $result = $this->trashService->getListTrash($currentUser->user_id, $request);
            $response['status'] = config('apps.general.success');
            $response['message'] = [trans('message.SUCCESS')];
            $response['data'] = $result;
            return $response;
        } catch (\Exception $exception) {
            $response['status'] = config('apps.general.error');
            $response['error_code'] = config('apps.general.error_code');
            $response['message'] = [trans('message.FAIL')];
            return $response;
        }
    }
}
