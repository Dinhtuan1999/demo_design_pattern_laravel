<?php

namespace App\Http\Controllers\PC\UserTask;

use App\Http\Controllers\Controller;
use App\Services\UserWatchlistService;
use App\Services\ProjectNotificationService;
use App\Services\TaskStatusService;
use App\Services\UserTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserTaskController extends Controller
{
    public function __construct(
        UserTaskService            $userTaskService,
        TaskStatusService          $taskStatusService,
        UserWatchlistService       $userWatchlistService,
        ProjectNotificationService $projectNotificationService
    ) {
        $this->userTaskService = $userTaskService;
        $this->taskStatusService = $taskStatusService;
        $this->userWatchlistService = $userWatchlistService;
        $this->projectNotificationService = $projectNotificationService;
    }

    /**
     * C040 Get my task
     *
     * @param Request $request
     * @return View|JsonResponse
     */
    public function getMyTask(Request $request)
    {
        $currentUser = auth()->user();
        // get popup E020
        $noti_project_inprogress = null;
        if ($currentUser && isset($currentUser->company_id)) {
            // Get data notification
            $noti_project_inprogress = $this->projectNotificationService->getListNotificationProjectInProgressByUserId($currentUser->user_id);
        }
        //load more notify
        if ($request->ajax() && $request->notify) {
            return $this->respondSuccess(__('message.COMPLETE'), $noti_project_inprogress['data'] ?? []);
        }

        $isFilter = false;
        if (!empty($request->input('task_name')) || !empty($request->input('task_status_id'))) {
            $isFilter = true;
        }
        $task_status = $this->taskStatusService->getListTaskStatus();
        $my_tasks = $this->userTaskService->getTaskByUser($request, $currentUser->user_id);
        $currentUserId = $currentUser->user_id;
        //load more task
        if ($request->ajax()) {
            return $this->respondSuccess(__('message.COMPLETE'), ['data' => $my_tasks['data'] ?? []]);
        }

        return view('my-portal.my-task', compact('noti_project_inprogress', 'task_status', 'my_tasks', 'currentUserId', 'isFilter'));
    }

    /**
     * C040 Get my watch list
     *
     * @param Request $request
     * @return View|JsonResponse
     */
    public function getMyWatchList(Request $request)
    {
        $currentUser = auth()->user();
        $isFilter = false;
        if (!empty($request->input('task_name')) || !empty($request->input('task_status_id'))) {
            $isFilter = true;
        }
        $my_tasks = $this->userWatchlistService->listUserWatch($request, $currentUser->user_id);
        if ($request->ajax()) {
            return $this->respondSuccess(__('message.COMPLETE'), ['data' => $my_tasks['data'] ?? []]);
        }

        $task_status = $this->taskStatusService->getListTaskStatus();
        $currentUserId = $currentUser->user_id;

        return view('my-portal.my-watchlist', compact('task_status', 'my_tasks', 'currentUserId', 'isFilter'));
    }


    private $userTaskService;
    private $userWatchlistService;
    private $taskStatusService;
}
