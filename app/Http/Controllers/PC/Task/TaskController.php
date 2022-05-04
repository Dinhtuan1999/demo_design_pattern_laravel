<?php

namespace App\Http\Controllers\PC\Task;

use App\Events\Project\ProjectDetailChangeBroadcast;
use App\Http\Controllers\PC\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Repositories\TaskRepository;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    private $taskRepository;

    public function __construct(TaskRepository $taskRepository)
    {
        $this->taskRepository = $taskRepository;
    }

    public function updateSubTaskInTaskAjax(Request $request)
    {
        try {
            $currentUser = Auth::user();
            $dataSubTask = $request->get('data_sub_tasks') ?? [];
            $projectId = $request->get('project_id');
            $project = Project::where('project_id', $projectId)->first();

            if (!$project) {
                return $this->respondSuccess(trans('message.ERR_COM_0052'));
            }
            if (empty($dataSubTask)) {
                return $this->respondWithError(trans('message.ERR_COM_0052'));
            }

            DB::beginTransaction();
            foreach ($dataSubTask as $task) {
                if (isset($task['task_id']) && isset($task['parent_task_id']) && isset($task['sub_task_display_order'])) {
                    Task::where('task_id', $task['task_id'])->update($task);
                }
            }
            DB::commit();
            broadcast(new ProjectDetailChangeBroadcast($currentUser, $project));

            return $this->respondSuccess(trans('message.INF_COM_0052'));
        } catch (\Throwable $th) {
            DB::rollBack();
            set_log_error('Error updateDisplayOrderTaskGroupMultiAjax', $th->getMessage());
        }

        return $this->respondWithError(trans('message.ERR_COM_0052'));
    }
}
