<?php

namespace App\Http\Controllers\API;

use App\Exports\ExportTask;
use App\Helpers\Transformer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\ProjectRequest;
use App\Http\Requests\TaskGroup\EditSettingGroupRequest;
use App\Http\Requests\TaskGroup\CopyGroupRequest;
use App\Http\Requests\TaskGroup\GetTaskGroupDetailByProjectIdRequest;
use App\Http\Requests\TaskGroup\GetTaskGroupDetailRequest;
use App\Models\Project;
use App\Repositories\ProjectRepository;
use App\Repositories\TaskGroupRepository;
use App\Scopes\DeleteFlgNotDeleteScope;
use App\Services\ProjectService;
use App\Services\TaskGroupService;
use App\Services\ValidationService;
use App\Transformers\TaskGroup\TaskGroupDetailTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class TaskGroupController extends Controller
{
    protected $taskGroupService;
    private $projectRepo;
    private $taskGroupRepo;
    private $validationService;
    private $projectService;

    public function __construct(
        TaskGroupService $taskGroupService,
        ProjectRepository $projectRepo,
        TaskGroupRepository $taskGroupRepo,
        ValidationService $validationService,
        ProjectService $projectService
    ) {
        $this->taskGroupService = $taskGroupService;
        $this->projectRepo = $projectRepo;
        $this->taskGroupRepo = $taskGroupRepo;
        $this->validationService = $validationService;
        $this->projectService = $projectService;
    }

    public function editSettingGroup(EditSettingGroupRequest $request)
    {
        $currentUser = auth('api')->user();

        $result = $this->taskGroupService->editSettingGroup(
            $currentUser->user_id,
            $request->input('task_group_id'),
            $request->input('group_name'),
            $request->input('disp_color_id')
        );

        return response()->json($result);
    }

    public function listGroupByProject(Request $request)
    {
        $result = [];
        $projectId = $request->input('project_id');
        $project = $this->projectRepo->getByCol('project_id', $projectId, [Project::TASK_GROUPS]);
        if (empty($project->project_id)) {
            $result['status'] = config('apps.general.error');
            $result['error_code'] = config('apps.general.error_code');
            $result['message'] = [trans('validation.object_not_exist', ['object' => trans('label.project.project')])];
            return $result;
        }
        $result['status'] = config('apps.general.success');
        $result['message'] = [trans('message.SUCCESS')];
        $result['data'] = $project->task_groups;
        return $result;
    }

    public function getGroupDetail(GetTaskGroupDetailRequest $request)
    {
        // 1. Get task_group_id from Parameter
        $taskGroupId = $request->get('task_group_id');
        // 2.  Call to taskGroupService with getGroupDetail function
        $data = $this->taskGroupService->getGroupDetail($taskGroupId);
        // 3. Return response base on service's status
        if (empty($data) || $data['status'] == config('apps.general.error')) {
            return $this->respondWithError([trans('message.NOT_COMPLETE')]);
        }

        return response()->json($data);
    }

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
        if ($request->has('group') && is_array($request->input('group'))) {
            $filter['group'] = $request->input('group');
        }
        if ($request->has('task') && is_array($request->input('task'))) {
            $filter['task'] = $request->input('task');
        }

        // get data
        $result = $this->taskGroupService->getDataExport($request->input('project_id'), $filter);
        if ($result['status'] === config('apps.general.error')) {
            return response()->json($result);
        }

        return Excel::download(new ExportTask($result['data']), 'task.csv');
    }

    public function getTaskGroupDetailByProjectId(GetTaskGroupDetailByProjectIdRequest $request)
    {
        $projectId = $request->project_id;
        $filter = $request->only([
            "display_mode",
            "status",
            "priority",
            "manager",
            "author",
            "watch_list",
        ]);

        $taskGroups = $this->taskGroupService->getTaskGroupDetailByProjectId($projectId, $filter, config('apps.general.paginate_default'));
        return $this->respondSuccess('', Transformer::pagination(new TaskGroupDetailTransformer(), $taskGroups));
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
        $taskGroup = $this->taskGroupRepo->getById($request->input('task_group_id'));
        if (!$taskGroup) {
            $response = $this->respondWithError(trans('validation.object_not_exist', ['object' => trans('label.task_group.task_group')]));
            return $response;
        }
        if ($taskGroup->delete_flg == config('apps.general.is_deleted')) {
            return $this->respondWithError(trans('message.ERR_COM_0011', ['attribute' => $taskGroup->group_name]));
        }

        // Check exist Project

        $project = $this->projectRepo->getModel()::where('project_id', $request->input('project_id'))
            ->withoutGlobalScope(new DeleteFlgNotDeleteScope())->first();
        if (!$project) {
            $response = $this->respondWithError(trans('validation.object_not_exist', ['object' => trans('label.project.project')]));
            return $response;
        }
        if ($project->delete_flg == config('apps.general.is_deleted')) {
            return $this->respondWithError(trans('message.ERR_COM_0011', ['attribute' => $project->project_name]));
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

        return response()->json($result);
    }
}
