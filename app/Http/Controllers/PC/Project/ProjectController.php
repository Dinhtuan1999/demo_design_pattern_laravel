<?php

namespace App\Http\Controllers\PC\Project;

use App\Helpers\TaskHelper;
use App\Helpers\Transformer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\CreateProjectRequest;
use App\Http\Requests\Project\GetDetailProjectRequest;
use App\Http\Requests\Project\GetListLogProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Repositories\ProjectParticipantRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\TaskRepository;
use App\Services\AttachmentFileService;
use App\Services\PriorityMstService;
use App\Services\ProjectService;
use App\Services\TaskGroupService;
use App\Services\TaskService;
use App\Services\TaskStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\RoleService;
use App\Repositories\CompanyRepository;
use App\Http\Requests\Project\UpdateProjectCompleteRequest;
use App\Models\TaskGroupDispColor;
use App\Repositories\DispColorRepository;
use App\Services\ProjectAttributeService;
use App\Services\UserService;
use App\Transformers\TaskGroup\TaskGroupDetailTransformer;
use App\Transformers\User\UserBasicTransfomer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Session;

class ProjectController extends Controller
{
    private $projectService;
    private $projectRepo;
    private $roleService;
    private $companyRepo;
    private $taskRepository;
    private $projectAttributeService;
    private $attachmentFileService;
    private $taskService;
    private $taskGroupService;
    private $taskStatusService;
    private $priorityMstService;
    private $projectParticipantRepo;
    private $dispColorRepository;
    private $taskGroupDispColor;
    private $userService;

    public function __construct(
        ProjectAttributeService $projectAttributeService,
        ProjectService $projectService,
        ProjectRepository $projectRepo,
        RoleService $roleService,
        CompanyRepository $companyRepo,
        AttachmentFileService $attachmentFileService,
        TaskService $taskService,
        TaskGroupService $taskGroupService,
        TaskStatusService $taskStatusService,
        PriorityMstService $priorityMstService,
        ProjectParticipantRepository $projectParticipantRepo,
        TaskRepository  $taskRepository,
        DispColorRepository $dispColorRepository,
        TaskGroupDispColor $taskGroupDispColor,
        UserService $userService
    ) {
        $this->projectService = $projectService;
        $this->projectRepo    = $projectRepo;
        $this->roleService    = $roleService;
        $this->companyRepo = $companyRepo;
        $this->projectAttributeService = $projectAttributeService;
        $this->attachmentFileService = $attachmentFileService;
        $this->taskService = $taskService;
        $this->taskGroupService = $taskGroupService;
        $this->taskStatusService = $taskStatusService;
        $this->priorityMstService = $priorityMstService;
        $this->projectParticipantRepo = $projectParticipantRepo;
        $this->taskRepository = $taskRepository;
        $this->dispColorRepository = $dispColorRepository;
        $this->taskGroupDispColor = $taskGroupDispColor;
        $this->userService = $userService;
    }

    /**
     * show the form for create a new project
     *
     * @param
     * @return
     */
    public function create()
    {
        $currentUser = Auth::user();
        $company_id = $currentUser->company_id;
        $roles = $this->getListRole($company_id);
        $projectAttributes = $this->projectAttributeService->getProjectAttributes();
        $projectAttributes = isset($projectAttributes["data"]) ? $projectAttributes["data"] : "";
        // if user is guest then disable all input, textarea
        $disable = $currentUser->guest_flg == config('apps.user.not_guest') ? '' : 'disabled';
        return  view('project.create_project', ['disable' => $disable, 'roles' => $roles['data'], "projectAttributes" => $projectAttributes]);
    }
    /**
     * get all info of project by user
     *
     * @param
     * @return
     */
    public function detailProjectWithUser(Request $request)
    {
        if (!$request->ajax()) {
            abort(404);
        }
        $currentUser = Auth::user();
        $company_id = $currentUser->company_id;
        $roles = $this->getListRole($company_id);
        $result = $this->projectService->detailProjectWithUser($request->id);
        if ($result["status"] == -1) {
            return redirect()->route("pc.create-project");
        }
        $result = isset($result["data"]) ? $result["data"] : "";
        $result->scheduled_start_date = formatShowDate($result->scheduled_start_date);
        $result->scheduled_end_date = formatShowDate($result->scheduled_end_date);
        $result->actual_start_date = formatShowDate($result->actual_start_date);
        $result->actual_end_date = formatShowDate($result->actual_end_date);
        $projectAttributes = $this->projectAttributeService->getProjectAttributes();
        $projectAttributes = isset($projectAttributes["data"]) ? $projectAttributes["data"] : "";
        $disable = $currentUser->guest_flg == config('apps.user.not_guest') ? '' : 'disabled';
        $html = view('project.partial.form', ['disable' => $disable, 'roles' => $roles['data'], "project" => $result, "projectAttributes" => $projectAttributes])->render();
        return response()->json($html);
    }
    /**
     * get all info of project
     *
     * @param
     * @return
     */
    public function getDetailProject(Request $request)
    {
        $result = $this->projectService->detailProjectWithUser($request->id);
        return response()->json($result);
    }
    /**
     *  get roles by company id
     *
     * @param
     * @return  array
     */
    //
    public function getListRole($company_id)
    {
        $roles = [];
        if (!isset($company_id)) {
            $roles = [];
        }
        $company = $this->companyRepo->getByCols(['company_id' => $company_id, 'delete_flg' => config('apps.general.not_deleted')]);
        if (!$company) {
            $roles = [];
            return $roles;
        }
        $roles = $this->roleService->getListRole($company->company_id);
        return $roles;
    }
    /**
     *  create or update project
     *
     * @param
     * @return
     */
    //
    public function createOrUpdate(CreateProjectRequest $request)
    {
        // if project exit then call update
        if (!empty($request->input('project_id'))) {
            $projectId = $this->update($request);
            if (!$projectId) {
                return redirect()->back()->with('error', trans('message.ERR_COM_0009'));
            }
            return redirect()->route('web.project.group', ['id' => $projectId]);
        } else {
            $projectId = $this->store($request);
            if (!$projectId) {
                return redirect()->back()->with('error', trans('message.ERR_COM_0008'));
            }
            return redirect()->route('web.project.group', ['id' => $projectId]);
        }
    }
    /**
     *  create project
     *
     * @param
     * @return
     */
    public function store($request)
    {
        $result           = [];
        $result['status'] = -1;
        $currentUser = Auth::user();
        $request->flash();
        $result = $this->projectService->create($request, $currentUser);
        if (isset($result["data"])) {
            $result =  $result["data"] ;
            return $result;
        }

        return false;
    }
    /**
     *  update project
     *
     * @param
     * @return
     */
    public function update($request)
    {
        $result           = [];
        $projectId = $request->project_id;
        $project          = $this->projectRepo->getByCol('project_id', $projectId);
        if (!$project) {
            return false;
        }
        $result = $this->projectService->update($projectId, $request, Auth::user());
        if (isset($result["data"])) {
            $result =  $result["data"] ;
            return $result['project_id'];
        }
        return false;
    }
    /**
     *  search member
     *
     * @param
     * @return
     */
    public function searchMember(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->respondWithError(trans('message.ERR_S.C_0001'));
        }
        $companyId = $user->company_id;
        $keyword = $request->keyword;
        $guestFlag = config('apps.general.not_guest');

        $projectId = null;
        if ($request->has('project_id')) {
            // check exist project
            $projectId = $request->project_id;
            $project = $this->projectRepo->getByCols([
                'project_id' => $projectId,
                'delete_flg' => config('apps.general.not_deleted')
            ]);

            if (!$project) {
                return $this->respondWithError(trans('message.ERR_COM_0155', ['object' => trans('validation_attribute.t_project')]));
            }
        }

        $result = $this->projectService->searchMember($companyId, $keyword, $guestFlag, $projectId);
        return $result;
    }
    /**
     *  search guest
     *
     * @param
     * @return
     */
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
        return $result;
    }
    /**
     *  update status of project
     *
     * @param
     * @return
     */
    public function updateStatusProject(UpdateProjectCompleteRequest $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->respondWithError(trans('message.ERR_S.C_0001'));
        }
        $userId = $user->user_id;
        $projectId = $request->project_id;
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
        $project = $this->projectRepo->getByCols(['project_id' => $projectId]);
        if (empty($project)) {
            return self::sendError(
                [trans('message.ERR_COM_0011', ['attribute' => trans('validation_attribute.t_project')])],
                config('apps.general.error_code')
            );
        }
        $result = $this->projectService->updateStatusProject($userId, $project, $status, $actualStartDate, $actualEndDate);
        return $result;
    }
    /**
     *  move project to trash
     *
     * @param
     * @return
     */
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

        $user = Auth::user();
        $result = $this->projectService->moveToTrash($projectId, $user);
        if ($result["status"] == 1) {
            Session::flash('move-to-trash-success', trans('message.INF_COM_0058', ['attribute' => $project->project_name]));
            Session::flash('trash-id', $result["data"]["trash_id"]);
            Session::flash('project-id', $projectId);
        }
        return  $result;
    }
    /**
     *  get list group task, priority, range
     *
     * @param
     * @return
     */
    public function getListGroupByProject($projectId, Request $request)
    {
        $currentUser =  Auth::user();

        if (!Gate::allows('company-available') || !(Gate::allows('accountSupper') || Gate::allows('accountContractor') || Gate::allows('accountGuest'))) {
            abort(403);
        }
        $project = $this->projectRepo->getByCols([
            'project_id' => $projectId,
            'delete_flg' => config('apps.general.not_deleted')
        ]);
        if (!$project) {
            return redirect()->back()->with('error', trans('message.ERR_COM_0011', ['attribute' => trans('validation_attribute.t_project')]));
        }

        $currentUserId = $currentUser->user_id;

        $status = $this->taskStatusService->getListTaskStatus();
        $priority = $this->priorityMstService->getListPriority();
        $manager = $this->taskService->getManagers($projectId);
        $authors = $this->taskService->getAuthors($projectId);
        $authorFile = $this->attachmentFileService->getAuthors($projectId);
        $taskGroups   = $this->taskGroupService->getTaskGroup($projectId);
        $dispColors = $this->taskGroupDispColor->all();

        $taskStatuses = TaskHelper::getListStatusTask();
        $priorities = $this->priorityMstService->getAllPriority(['priority_id', 'priority_name', 'display_order']);
        $managersByProjectId = Transformer::collection(new UserBasicTransfomer(), $this->userService->getManagersByProjectId($projectId))['data'] ?? [];
        $authorsByProjectId = Transformer::collection(new UserBasicTransfomer(), $this->userService->getAuthorsByProjectId($projectId))['data'] ?? [];

        return view('project.group_project')->with([
            'status' => $status['data'],
            'priority' => $priority['data'],
            'manager' => $manager['data'],
            'authors' => $authors['data'],
            'authorFiles' => $authorFile['data'],
            'taskGroups' => $taskGroups['data'],
            'project' => $project,
            'dispColors' => $dispColors,
            'projectId' => $projectId,
            'currentUserId' => $currentUserId,
            'taskStatuses' => $taskStatuses,
            'priorities' => $priorities,
            'managersByProjectId' => $managersByProjectId,
            'authorsByProjectId' => $authorsByProjectId,
        ]);
    }

    public function getListLogProject(GetListLogProjectRequest $request)
    {
        $projectId = $request->get('project_id');
        $getListLogProjects = $this->projectService->getListLog($projectId);

        $result['last_page'] = $getListLogProjects['data']['data']->lastPage();
        $result['data'] = \View::make("project.response.project_list_logs_result")
            ->with("listLogProjects", $getListLogProjects['data'])
            ->render();

        return $result;
    }

    public function getListFile(Request $request)
    {
        $projectId = $request->input('project_id');

        $project = $this->projectRepo->getByCols([
            'project_id' => $projectId,
            'delete_flg' => config('apps.general.not_deleted')
        ]);
        if (!$project) {
            return redirect()->back()->with('error', trans('message.ERR_COM_0011', ['attribute' => trans('validation_attribute.t_project')]));
        }

        $filter = [
            'search'    => null,
            'group'     => null,
            'author'    => null,
            'order'     => 't_attachment_file.create_datetime',
            'sort'      => 'desc',
        ];

        if ($request->has('search') && !empty($request->input('search'))) {
            $filter['search'] = $request->input('search');
        }

        if ($request->has('group') && is_array($request->input('group'))) {
            $filter['group'] = $request->input('group');
        }

        if ($request->has('author') && is_array($request->input('author'))) {
            $filter['author'] = $request->input('author');
        }

        if ($request->has('order') && !empty($request->input('order'))) {
            $filter['order'] = $request->input('order');
        }

        if ($request->has('sort') && !empty($request->input('sort'))) {
            $filter['sort'] = $request->input('sort');
        }

        $result = $this->attachmentFileService->getListFile(
            $request->input('project_id'),
            $filter
        );

        $result['last_page'] = $result['data']->lastPage();
        $result['data'] = \View::make("project.result_search_file_list")
            ->with(["fileLists" => $result['data']->toArray(), 'totalAll' => $result['data']->totalAll])
            ->render();

        return $result;
    }
    /**
     *  check user exit in project
     *
     * @param
     * @return
     */
    public function checkUserExit(Request $request)
    {
        $result           = [];
        $result['status'] = -1;
        $userId = $request->input('user_id');
        $projectId = $request->input('project_id');
        $project = $this->projectRepo->getByCols([
            'project_id' => $projectId,
            'delete_flg' => config('apps.general.not_deleted')
        ]);

        if (!$project) {
            return $result;
        }

        if (empty($userId)) {
            return $result;
        }
        // check user is the project creator or not?
        $projectPar = $this->projectParticipantRepo->getByCols([
            'project_id' => $projectId,
            'delete_flg' => config('apps.general.not_deleted'),
            'create_user_id' => $userId,
        ]);
        if ($projectPar) {
            $result['status'] = 1;
            return $result;
        }

        // check user is in any task or not?
        $task = $this->taskRepository->getByCols([
            'project_id' => $projectId,
            'delete_flg' => config('apps.general.not_deleted'),
            'user_id' => $userId
        ]);
        if ($task) {
            $result['status'] = 1;
            return $result;
        }

        return $result;
    }
    /**
    *  check user is guest or member
    *
    * @param #key
    * @return boolean
    */
    public function checkUserIsGuest(Request $request)
    {
        $email = $request->keyword;
        $currentUser =  Auth::user();
        $result = $this->projectService->checkUserIsGuest($email, $currentUser);
        return $result;
    }

    public function groupTaskIndexTemplate(Request $request)
    {
        $projectId = $request->get('project_id');

        $project = $this->projectRepo->findByField('project_id', $projectId);

        $filter = [
            'display_mode'  => 'group',
            'status'  => null,
            'priority'  => null,
            'manager'  => null,
            'author'  => null,
            'watch_list'  => null,
        ];

        if ($request->has('status') && is_array($request->get('status'))) {
            $filter['status'] = $request->get('status');
        }

        if ($request->has('priority') && is_array($request->get('priority'))) {
            $filter['priority'] = $request->get('priority');
        }

        if ($request->has('manager') && is_array($request->get('manager'))) {
            $filter['manager'] = $request->get('manager');
        }

        if ($request->has('author') && is_array($request->get('author'))) {
            $filter['author'] = $request->get('author');
        }

        if ($request->has('watch_list') && is_bool($request->get('watch_list'))) {
            $filter['watch_list'] = $request->get('watch_list');
        }

        $taskGroupsCollection = $this->taskGroupService->getTaskGroupDetailByProjectId($projectId, $filter, config('apps.general.paginate_task_groups'));
        $taskGroups = Transformer::pagination(new TaskGroupDetailTransformer(), $taskGroupsCollection)['data'] ?? [];

        $html = view('project.group-task.index', compact('project', 'taskGroupsCollection', 'taskGroups'))->render();

        return $this->respondSuccess('', ["html" =>$html, "lastPage" => $taskGroupsCollection->lastPage()]);
    }

    /**
     * get list group task by project id
     *
     * @param Request $request
     * @return void
     */
    public function getGroupTaskByProjectId(Request $request)
    {
        $projectId = $request->get('project_id');

        $filter = [
            'display_mode'  => 'group',
            'status'  => null,
            'priority'  => null,
            'manager'  => null,
            'author'  => null,
            'watch_list'  => null,
        ];

        if ($request->has('status') && is_array($request->get('status'))) {
            $filter['status'] = $request->get('status');
        }

        if ($request->has('priority') && is_array($request->get('priority'))) {
            $filter['priority'] = $request->get('priority');
        }

        if ($request->has('manager') && is_array($request->get('manager'))) {
            $filter['manager'] = $request->get('manager');
        }

        if ($request->has('author') && is_array($request->get('author'))) {
            $filter['author'] = $request->get('author');
        }

        if ($request->has('watch_list') && is_bool($request->get('watch_list'))) {
            $filter['watch_list'] = $request->get('watch_list');
        }

        $taskGroupsCollection = $this->taskGroupService->getTaskGroupDetailByProjectId($projectId, $filter, null);

        $taskGroups = Transformer::collection(new TaskGroupDetailTransformer(), $taskGroupsCollection)['data'] ?? [];

        return $this->respondSuccess(trans('message.SUCCESS'), ["task_groups" =>$taskGroups, "lastPage" => 0]);
    }

    /**
     * get list project  by user id
     *
     * @param
     * @return array
     */
    public function getProjectsByUser()
    {
        $user= Auth::user();
        $result = $this->projectService->getProjectByUser($user->user_id);
        return $result;
    }
}
