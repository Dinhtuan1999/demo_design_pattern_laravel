<?php

namespace App\Http\Controllers\PC\UserProject;

use App\Http\Controllers\Controller;
use App\Services\GoodService;
use App\Services\ProjectNotificationService;
use App\Services\UserProjectService;
use Illuminate\Http\Request;
use App\Services\ProjectAttributeService;

class UserProjectController extends Controller
{
    public function __construct(
        UserProjectService $userProjectService,
        GoodService $goodService,
        ProjectNotificationService $projectNotificationService,
        ProjectAttributeService $projectAttributeService
    ) {
        $this->userProjectService = $userProjectService;
        $this->goodService = $goodService;
        $this->projectNotificationService   = $projectNotificationService;
        $this->projectAttributeService = $projectAttributeService;
    }

    public function getMyProject(Request $request)
    {
        $currentUser = auth()->user();
        $project_status = 0;
        $project_name = '';

        $isFilter = false;
        if (!empty($request->input('project_name')) || !empty($request->input('project_status'))) {
            $isFilter = true;
        }
        if (!is_null($request->input('project_status'))) {
            $project_status = $request->input('project_status');
        }
        // get project list
        $my_projects = $this->userProjectService->listProjectByUser($currentUser->user_id, $request);
        if (!is_null($request->input('project_name'))) {
            $project_name = $request->input('project_name');
        }

        if ($request->ajax()) {
            return $this->respondSuccess(__('message.COMPLETE'), ['data' => $my_projects['data'] ?? []]);
        }
        $company_id = $currentUser->company_id;
        $roles = app('App\Http\Controllers\PC\Project\ProjectController')->getListRole($company_id);
        $projectAttributes = $this->projectAttributeService->getProjectAttributes();
        $projectAttributes = isset($projectAttributes["data"]) ? $projectAttributes["data"] : "";
        // if user is guest then disable all input, textarea
        $disable = $currentUser->guest_flg == config('apps.user.not_guest') ? '' : 'disabled';

        return view('my-portal.my-project', compact('my_projects', 'project_name', 'project_status', 'isFilter', 'projectAttributes', 'roles'));
    }

    public function getMyGraph(Request $request)
    {
        $currentUser = auth()->user();
        $project_status = 0;
        $project_name = '';
        $isFilter = false;
        if (!empty($request->input('project_name')) || !empty($request->input('project_status'))) {
            $isFilter = true;
        }
        $my_projects = $this->userProjectService->listProjectByUser($currentUser->user_id, $request);

        if ($request->ajax()) {
            return $this->respondSuccess(__('message.COMPLETE'), ['data' => $my_projects['data'] ?? []]);
        }

        if (!is_null($request->input('project_status'))) {
            $project_status = $request->input('project_status');
        }
        if (!is_null($request->input('project_name'))) {
            $project_name = $request->input('project_name');
        }
        return view('my-portal.my-graph', compact('my_projects', 'project_name', 'project_status', 'isFilter'));
    }
    private $userProjectService;
    private $goodService;
    private $taskStatusService;
    private $projectAttributeService;
}
