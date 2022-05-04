<?php

namespace App\Http\Controllers\PC\TaskGroup;

use App\Events\Project\ProjectDetailChangeBroadcast;
use App\Events\TaskGroup\UpdateDisplayOrderTaskGroupEvent;
use App\Http\Controllers\PC\Controller;
use App\Http\Requests\TaskGroup\CopyGroupRequest;
use App\Http\Requests\TaskGroup\CreateTaskGroupRequest;
use App\Http\Requests\TaskGroup\EditSettingGroupRequest;
use App\Models\Project;
use App\Models\Task;
use App\Repositories\TaskGroupRepository;
use App\Repositories\ProjectRepository;
use App\Services\TaskGroupService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskGroupController extends Controller
{
    private $taskGroupService;
    private $taskGroupRepository;
    private $projectRepository;

    public function __construct(TaskGroupService $taskGroupService, TaskGroupRepository $taskGroupRepository, ProjectRepository $projectRepository)
    {
        $this->taskGroupService = $taskGroupService;
        $this->taskGroupRepository = $taskGroupRepository;
        $this->projectRepository = $projectRepository;
    }

    public function storeTaskGroupAjax(CreateTaskGroupRequest $request)
    {
        $data = $request->only(['project_id', 'group_name', 'disp_color_id']);
        $projectId = $data['project_id'];
        $result = $this->taskGroupService->createTaskGroup($projectId, $data);
        if (!$result) {
            return $this->respondWithError(trans('message.ERR_COM_0050'));
        }

        $result['group_view'] = view('project.response.list_task_by_group.single_group', [
            'item' => $result
        ])->render();

        return $this->respondSuccess(trans('message.INF_COM_0050'), $result);
    }

    public function updateTaskGroupAjax(EditSettingGroupRequest $request)
    {
        $data = $request->only(['task_group_id', 'group_name', 'disp_color_id']);
        $taskGroup = $this->taskGroupRepository->findByField('task_group_id', $data['task_group_id']);
        if (!$taskGroup) {
            return $this->respondWithError(trans('message.ERR_COM_0087', ['attribute' => trans('label.task_group.label')]));
        }
        $result = $taskGroup->update($data);
        if (!$result) {
            return $this->respondWithError(trans('message.ERR_COM_0052'));
        }
        return $this->respondSuccess(trans('message.INF_COM_0052'));
    }

    /**
     * update display order task groups multi
     *
     * @param Request $request
     * @return void
     */
    public function updateDisplayOrderTaskGroupMultiAjax(Request $request)
    {
        try {
            $currentUser = Auth::user();
            $taskGroupIdsUpdate = $request->get('task_group_ids') ?? [];
            $projectId = $request->get('project_id');
            $project = Project::where('project_id', $projectId)->first();
            if (!$project) {
                return $this->respondSuccess(trans('message.ERR_COM_0052'));
            }
            $taskGroupIdsUpdateImplode =  implode("','", $taskGroupIdsUpdate);

            $taskGroups = $this->taskGroupRepository->getInstance()
                                ->whereIn('task_group_id', $taskGroupIdsUpdate)
                                ->orderByRaw(DB::raw("FIELD(task_group_id, '$taskGroupIdsUpdateImplode')"))
                                ->get();
            DB::beginTransaction();
            $displayOrderMax = count($taskGroups);
            foreach ($taskGroups as $index => $taskGroup) {
                $taskGroup->display_order = $displayOrderMax--;
                $taskGroup->save();
            }
            DB::commit();
            // broadcast(new UpdateDisplayOrderTaskGroupEvent($currentUser));

            broadcast(new ProjectDetailChangeBroadcast($currentUser, $project));

            return $this->respondSuccess(trans('message.INF_COM_0052'));
        } catch (\Throwable $th) {
            DB::rollBack();
            set_log_error('Error updateDisplayOrderTaskGroupMultiAjax', $th->getMessage());
        }

        return $this->respondWithError(trans('message.ERR_COM_0052'));
    }

    public function updateTaskInTaskGroupAjax(Request $request)
    {
        try {
            $currentUser = Auth::user();
            $dataTask = $request->get('data_tasks') ?? [];
            $projectId = $request->get('project_id');
            $project = Project::where('project_id', $projectId)->first();
            if (!$project) {
                return $this->respondSuccess(trans('message.ERR_COM_0052'));
            }

            if (empty($dataTask['task_group_id']) || empty($dataTask['tasks'])) {
                return $this->respondWithError(trans('message.ERR_COM_0052'));
            }
            DB::beginTransaction();
            $count = count($dataTask['tasks']);
            foreach ($dataTask['tasks'] as $taskId) {
                Task::where('task_id', $taskId)->update([
                    'task_group_id' => $dataTask['task_group_id'],
                    'parent_task_display_order' => $count--
                ]);
            }
            DB::commit();
            // broadcast(new UpdateDisplayOrderTaskGroupEvent($currentUser));
            broadcast(new ProjectDetailChangeBroadcast($currentUser, $project));

            return $this->respondSuccess(trans('message.INF_COM_0052'));
        } catch (\Throwable $th) {
            DB::rollBack();
            set_log_error('Error updateDisplayOrderTaskGroupMultiAjax', $th->getMessage());
        }

        return $this->respondWithError(trans('message.ERR_COM_0052'));
    }

    /**
      * Copy Task Group
      * TODO: A.F011.2_Copy group
      * 2022-02-26
      *
      * @param Request $request
      * @return \Illuminate\Http\JsonResponse
      */
    public function copyTaskGroup(CopyGroupRequest $request)
    {
        // Check exist Task Group
        $taskGroup = $this->taskGroupRepository->getById($request->input('task_group_id'));
        if (!$taskGroup) {
            return redirect()->back()->with('error', trans('validation.object_not_exist', ['object' => trans('label.task_group.task_group')]));
        }
        // Check exist Project
        $project = $this->projectRepository->getById($request->input('project_id'));
        if (!$project) {
            return redirect()->back()->with('error', trans('validation.object_not_exist', ['object' => trans('label.project.project')]));
        }

        // Copy task group
        $dataTaskGroupFilter = [
              'priority' => $request->input('priority'),
              'disclosure_range' => $request->input('disclosure_range'),
              'task_memo' => $request->input('task_memo'),
              'start_end_plan_date' => $request->input('start_end_plan_date'),
              'sub_task' => $request->input('sub_task'),
              'check_list' => $request->input('check_list'),
              'attachment_file' => $request->input('attachment_file')
          ];

        $result = $this->taskGroupService->copyTaskGroup(
            $request->input('task_group_id'),
            $request->input('project_id'),
            $request->input('group_name'),
            $request->user()->user_id,
            $dataTaskGroupFilter
        );
        if ($result['status']==1) {
            return redirect()->back()->with('success', trans('message.INF_COM_0001'));
        }
        return redirect()->back()->with('error', trans('message.ERR_COM_0008'));
    }

    public function swapTaskGroup(Request $request)
    {
        $this->taskGroupService->swapGroup($request->all());
    }

    public function swapTaskInGroup(Request $request)
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

        $result = $this->taskGroupService->swapTaskInGroup($request->all(), $filter, $flagFilter);

        return response()->json($result);
    }
}
