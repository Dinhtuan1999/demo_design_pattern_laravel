<?php

namespace App\Http\Controllers\PC\Project;

use App\Http\Controllers\PC\Controller;
use App\Http\Requests\Project\CopyProjectRequest;
use App\Repositories\ProjectRepository;
use App\Services\CopyProjectService;
use Illuminate\Http\Request;
use App\Http\Requests\Project\ProjectTemplateFormRequest;
use Illuminate\Support\Facades\Auth;

class CopyProjectController extends Controller
{
    private $copyProjectService;
    private $projectRepo;

    public function __construct(
        CopyProjectService $copyProjectService,
        ProjectRepository $projectRepo
    ) {
        $this->copyProjectService = $copyProjectService;
        $this->projectRepo        = $projectRepo;
    }

    public function createTemplate(ProjectTemplateFormRequest $request)
    {
        $projectId = $request->input('project_id');
        $projectName = $request->input('copy_project_name');
        $currentUser = Auth::user();
        $result = $this->copyProjectService->copyProject($projectId, $projectName, $request->all(), 1, $currentUser->user_id);
        return $result;
    }

    public function copyProject(CopyProjectRequest $request)
    {
        $projectId = $request->input('project_id');
        $project = $this->projectRepo->getByCol('project_id', $projectId);
        if (empty($project->project_id)) {
            $result['status'] = config('apps.general.error');
            $result['error_code'] = config('apps.general.error_code');
            $result['message'] = [trans('validation.object_not_exist', ['object' => trans('label.project.name')])];
            return $result;
        }
        $projectName = $request->input('copy_project_name');
        $currentUser = Auth::user()->user_id;
        return $this->copyProjectService->copyProject($projectId, $projectName, $request->all(), 0, $currentUser);
    }
}
