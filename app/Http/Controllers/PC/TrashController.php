<?php

namespace App\Http\Controllers\PC;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trash\RestoreFromTrashRequest;
use App\Http\Requests\Trash\DeleteTaskTrashRequest;
use App\Repositories\TrashRepository;
use App\Services\TrashService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\BaseService;
use Illuminate\Validation\Rule;
use App\Repositories\TaskRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

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

    public function getListTrash(Request $request)
    {
        $response = [];

        $validator = Validator::make(request()->all(), [
            'task_id' => 'required',
        ], [
            'task_id.required' => trans('validation.required', ['attribute' => 'label.general.task_id']),
        ]);

        if ($validator->fails()) {
            $response['status'] = config('apps.general.error');
            $response['message'] = $validator->errors()->all();
            return response()->json($response);
        }

        $result = $this->trashService->getListTrashTask($request);

        return response()->json($result);
    }

    public function restoreFromTrash(Request $request)
    {
        $trash = $this->trashRepo->getByCol('trash_id', $request->input('trash_id'));
        if (!$trash) {
            return $this->baseService->sendError(trans('validation.object_not_exist', ['object' => trans('label.trash.title')]));
        }
        Session::flash('success', trans('message.INF_COM_0059'));
        $currentUser = Auth::user();
        return $this->trashService->restoreFromTrash($request->trash_id, $currentUser->user_id);
    }
}
