<?php

namespace App\Http\Controllers\API\Project;

use App\Http\Controllers\API\Controller;
use App\Http\Requests\Project\CreateProjectRequest;
use App\Http\Requests\Project\GetDetailProjectRequest;
use App\Http\Requests\Project\GetListLogProjectRequest;
use App\Http\Requests\Project\UpdateProjectCompleteRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Repositories\ProjectRepository;
use App\Services\ProjectLogService;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProjectController extends Controller
{
    private $projectService;
    private $projectRepo;
    private $projectLogService;

    public function __construct(
        ProjectService    $projectService,
        ProjectRepository $projectRepo,
        ProjectLogService $projectLogService
    ) {
        $this->projectService = $projectService;
        $this->projectRepo = $projectRepo;
        $this->projectLogService = $projectLogService;
    }

    public function getDetailProject(GetDetailProjectRequest $request)
    {
        $result = $this->projectService->getDetailProject($request->project_id);

        return response($result);
    }

    public function detailProjectWithUser(Request $request)
    {
        $result = $this->projectService->detailProjectWithUser($request->id);
        return response()->json($result);
    }

    public function create(CreateProjectRequest $request)
    {
        $result = [];
        $result['status'] = -1;
        $result = $this->projectService->create($request, auth('api')->user());
        return response()->json($result);
    }

    public function update(CreateProjectRequest $request)
    {
        $result = [];
        $result['status'] = -1;
        $projectId = $request->project_id;
        $project = $this->projectRepo->getByCol('project_id', $projectId);
        if (!$project) {
            $result['status'] = -1;
            $result['error_code'] = config('apps.general.error_code');
            $result['message'] = [trans('message.ERR_COM_0011', ['attribute' => trans('validation_attribute.t_project')])];
            return $result;
        }
        $result = $this->projectService->update($projectId, $request, auth('api')->user());
        return response()->json($result);
    }

    public function getListProjectInProgress(Request $request)
    {
        $result = $this->projectService->getListProjectInProgress($request);

        return response()->json($result);
    }

    public function moveToTrash(Request $request)
    {
        $result = [];
        $result['status'] = config('apps.general.error');
        $result['error_code'] = config('apps.general.error_code');

        $projectId = $request->input('project_id');
        if ($this->projectRepo->isDeleted('t_project', 'project_id', $projectId, config('apps.trash.identyfying_code.project'))) {
            $result['message'] = [trans('validation.object_is_deleted', ['object' => trans('label.project.project')])];
            return $result;
        }
        if ($this->projectRepo->isMovedToTrash('t_project', 'project_id', $projectId, config('apps.trash.identyfying_code.project'))) {
            $result['message'] = [trans('validation.object_moved_to_trash', ['object' => trans('label.project.project')])];
            return $result;
        }

        $project = $this->projectRepo->getByCol('project_id', $projectId);
        if (empty($project->project_id)) {
            $result['message'] = [trans('validation.object_not_exist', ['object' => trans('label.project.project')])];
            return $result;
        }

        $user = auth('api')->user();
        return $this->projectService->moveToTrash($projectId, $user);
    }

    public function searchMember(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->respondWithError(trans('message.ERR_S.C_0001'));
        }
        $companyId = $user->company_id;
        $keyword = $request->keyword;
        $guestFlag = config('apps.general.not_guest');

        // check exist project
        $projectId = $request->project_id;
        if (!is_null($projectId)) {
            $project = $this->projectRepo->getByCols([
                'project_id' => $projectId,
                'delete_flg' => config('apps.general.not_deleted')
            ]);
            if (!$project) {
                return $this->respondWithError(trans('message.ERR_COM_0155', ['object' => trans('validation_attribute.t_project')]));
            }
        }
        $result = $this->projectService->searchMember($companyId, $keyword, $guestFlag, $projectId);

        return response()->json($result);
    }

    public function searchGuest(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->respondWithError(trans('message.ERR_S.C_0001'));
        }
        $companyId = $user->company_id;
        $keyword = $request->keyword;
        $guestFlag = config('apps.general.is_guest');
        $result = $this->projectService->searchMember($companyId, $keyword, $guestFlag);
        return response()->json($result);
    }

    public function updateStatusProject(UpdateProjectCompleteRequest $request)
    {
        $user = auth('api')->user();
        if (!$user) {
            return $this->respondWithError(trans('message.ERR_S.C_0001'));
        }
        $userId = $user->user_id;
        $projectId = $request->project_id;

        $project = $this->projectRepo->getByCols(['project_id' => $projectId]);
        if (empty($project)) {
            return self::sendError(
                [trans('message.ERR_COM_0011', ['attribute' => trans('validation_attribute.t_project')])],
                config('apps.general.error_code')
            );
        }
        $actualStartDate = $request->actual_start_date;
        $actualEndDate = $request->actual_end_date;
        switch ($request->project_status) {
            case config('apps.project.status_key.not_started'):
                $status = config('apps.project.status_key.not_started');
                break;
            case config('apps.project.status_key.delay_start'):
                $status = config('apps.project.status_key.delay_start');
                break;
            case config('apps.project.status_key.in_progress'):
                $status = config('apps.project.status_key.in_progress');
                break;
            case config('apps.project.status_key.delay_complete'):
                $status = config('apps.project.status_key.delay_complete');
                break;
            case config('apps.project.status_key.complete'):
                $status = config('apps.project.status_key.complete');
                break;
            default:
                $status = config('apps.project.status_key.not_started');
        }

        $result = $this->projectService->updateStatusProject($userId, $project, $status, $actualStartDate, $actualEndDate);
        return response()->json($result);
    }

    public function getListLog(GetListLogProjectRequest $request)
    {
        // 1. Get project_id from Parameter
        $projectId = $request->get('project_id');
        $taskId = $request->get('task_id');
        // 2.  Call to projectService with getListLog function
        $identifyCode = $request->input('identifying_code', []);
        $data = $this->projectLogService->getLog($projectId, $identifyCode, $taskId);
        // 3. Return response base on service's status
        if (empty($data) || $data['status'] == config('apps.general.error')) {
            return $this->respondWithError([trans('message.NOT_COMPLETE')]);
        }
        $data['data'] = $data['data']->toArray();
        //change user icon image path to url
        $data['data']['data'] = collect($data['data']['data'])->map(function ($item) {
            if (!is_null($item['user'])) {
                $item['user']['icon_image_path'] = isset($item['user']['icon_image_path'])
                    ? Storage::url($item['user']['icon_image_path'])
                    : null;
            }
            return $item;
        });
        return response()->json($data);
    }

    /**
     * get list project  by user id
     *
     * @param
     * @return array
     */
    public function getProjectsByUser()
    {
        $user = auth('api')->user();
        $result = $this->projectService->getProjectByUser($user->user_id);
        return response()->json($result);
    }

    public function searchProject(Request $request)
    {
        $currentUser = Auth::user();
        $params['key_word'] = $request->get('key_word');
        $params['project_attribute'] = $request->get('project_attribute');
        $params['other_message'] = $request->get('other_message');
        $params['template_flg'] = $request->get('template_flg');
        $params['project_status'] = $request->get('project_status');

        $result = $this->projectService->searchProjectByUser($currentUser, $params);
        return response()->json($result);
    }
    /**
     *  check user is guest or member
     *
     * @param #key
     * @return boolean
     */
    public function checkUserIsGuest(Request $request)
    {
        $email = $request->get('keyword');
        $user = auth('api')->user();
        $result = $this->projectService->checkUserIsGuest($email, $user);
        return response()->json($result);
    }
}
