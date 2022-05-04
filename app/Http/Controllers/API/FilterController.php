<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\FilterService;
use App\Services\PriorityMstService;
use App\Services\ProjectService;
use App\Services\TaskService;
use App\Services\TaskStatusService;
use App\Services\TaskGroupService;
use App\Services\AttachmentFileService;
use Illuminate\Support\Facades\Auth;

class FilterController extends Controller
{
    protected $taskStatusService;
    protected $priorityMstService;
    protected $taskService;
    protected $filterService;
    protected $taskGroupService;
    protected $attachmentFileService;
    protected $projectService;

    public function __construct(
        TaskStatusService $taskStatusService,
        PriorityMstService $priorityMstService,
        TaskService $taskService,
        FilterService $filterService,
        TaskGroupService $taskGroupService,
        AttachmentFileService $attachmentFileService,
        ProjectService $projectService
    ) {
        $this->taskStatusService = $taskStatusService;
        $this->priorityMstService = $priorityMstService;
        $this->taskService = $taskService;
        $this->filterService = $filterService;
        $this->taskGroupService = $taskGroupService;
        $this->attachmentFileService = $attachmentFileService;
        $this->projectService = $projectService;
    }

    public function getGroupFilter($projectId)
    {
        $status = $this->taskStatusService->getListTaskStatus();

        $priority = $this->priorityMstService->getListPriority();

        $manager = $this->taskService->getManagers($projectId);

        $author = $this->taskService->getAuthors($projectId);

        $taskGroups = $this->taskGroupService->getTaskGroup($projectId);

        $authorFile = $this->attachmentFileService->getAuthors($projectId);

        $members = $this->taskService->getProjectMembers($projectId);

        return response()->json($this->filterService->prepareFilter($status, $priority, $manager, $author, $taskGroups, $authorFile, $members));
    }

    public function getListProjectAndManagerByCurrentUser()
    {
        $currentUser = Auth::user();
        $projects = $this->projectService->getProjectByUserRole($currentUser);
        $managers = $this->projectService->getManagerByProjectIds($projects['data']->pluck('project_id')->toArray());

        return response()->json($this->filterService->projectsAndMangersFilter($projects, $managers));
    }
}
