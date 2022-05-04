<?php

namespace App\Http\Controllers\API\Project;

use App\Http\Controllers\API\Controller;
use App\Http\Requests\Project\CopyProjectRequest;
use App\Http\Requests\Project\CreateTemplateRequest;
use App\Repositories\ProjectRepository;
use App\Scopes\DeleteFlgNotDeleteScope;
use App\Services\CopyProjectService;
use Illuminate\Http\Request;

class CopyProjectController extends Controller
{
    public function __construct(
        CopyProjectService $copyProjectService,
        ProjectRepository $projectRepo
    ) {
        $this->copyProjectService = $copyProjectService;
        $this->projectRepo        = $projectRepo;
    }

    public function copyProject(CopyProjectRequest $request)
    {
        $projectId = $request->input('project_id');
        $model = $this->projectRepo->getModel();
        $project = $model::where('project_id', $projectId)->withoutGlobalScope(new DeleteFlgNotDeleteScope())->first();
        if (empty($project)) {
            $result['status'] = config('apps.general.error');
            $result['error_code'] = config('apps.general.error_code');
            $result['message'] = [trans('validation.object_not_exist', ['object' => trans('label.project.name')])];
            return $result;
        }
        if ($project->delete_flg == config('apps.general.is_deleted')) {
            $result['status'] = config('apps.general.error');
            $result['error_code'] = config('apps.general.error_code');
            $result['message'] = [trans('message.ERR_COM_0011', ['attribute' => $project->project_name])];
            return $result;
        }
        $projectName = $request->input('copy_project_name');
        $currentUser = auth('api')->user();
        return $this->copyProjectService->copyProject($projectId, $projectName, $request->all(), 0, $currentUser->user_id);
    }

    public function createTemplate(CreateTemplateRequest $request)
    {
        $projectId = $request->input('project_id');
        $model = $this->projectRepo->getModel();
        $project = $model::where('project_id', $projectId)->withoutGlobalScope(new DeleteFlgNotDeleteScope())->first();
        if (empty($project)) {
            $result['status'] = config('apps.general.error');
            $result['error_code'] = config('apps.general.error_code');
            $result['message'] = [trans('validation.object_not_exist', ['object' => trans('label.project.name')])];
            return $result;
        }
        if ($project->delete_flg == config('apps.general.is_deleted')) {
            $result['status'] = config('apps.general.error');
            $result['error_code'] = config('apps.general.error_code');
            $result['message'] = [trans('message.ERR_COM_0011', ['attribute' => $project->project_name])];
            return $result;
        }
        $projectName = $request->input('copy_project_name');
        $currentUser = auth('api')->user();
        return $this->copyProjectService->copyProject($projectId, $projectName, $request->all(), 1, $currentUser->user_id);
    }

    private $copyProjectService;
    private $projectRepo;
}
