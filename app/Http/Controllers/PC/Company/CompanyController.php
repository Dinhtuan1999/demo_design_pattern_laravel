<?php

namespace App\Http\Controllers\PC\Company;

use App\Helpers\Transformer;
use App\Http\Controllers\PC\Controller;
use App\Http\Requests\API\ApiGetUserLicenseRequest;
use App\Http\Requests\Company\GetDetailsInformationRequest;
use App\Http\Requests\Company\GetGraphByCompanyRequest;
use App\Http\Requests\Company\GetListMembersRequest;
use App\Http\Requests\Company\GetListUsersRequest;
use App\Http\Requests\Company\SetBookmarkCompanyRequest;
use App\Http\Requests\Company\UpdateMultiUserRequest;
use App\Repositories\ProjectRepository;
use App\Services\BaseService;
use App\Services\BookmarkCompanyService;
use App\Services\CompanyService;
use App\Services\KindService;
use App\Services\LicenceManagementService;
use App\Services\UserGroupService;
use App\Services\UserService;
use App\Http\Requests\Company\UpdateCompanyInformationRequest;
use App\Repositories\CountyRepository;
use App\Repositories\IndustryRepository;
use App\Services\ProjectAttributeService;
use App\Services\NumberOfEmployeeService;
use App\Transformers\Company\GetMemberDetailTransformer;
use App\Transformers\Project\ProjectBasicWithGraphTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CompanyController extends Controller
{
    private $companyService;
    private $licenceManagementService;
    private $userService;
    private $userGroupService;
    private $industryRepository;
    private $countyRepository;
    private $numberOfEmployeeService;
    private $baseService;
    private $bookmarkCompanyService;
    private $kindService;
    private $projectRepository;
    private $projectAttributeService;


    public function __construct(
        CompanyService           $companyService,
        LicenceManagementService $licenceManagementService,
        UserService              $userService,
        UserGroupService         $userGroupService,
        IndustryRepository       $industryRepository,
        CountyRepository         $countyRepository,
        NumberOfEmployeeService  $numberOfEmployeeService,
        BookmarkCompanyService   $bookmarkCompanyService,
        BaseService              $baseService,
        KindService              $kindService,
        ProjectRepository        $projectRepository,
        ProjectAttributeService  $projectAttributeService
    ) {
        $this->companyService = $companyService;
        $this->licenceManagementService = $licenceManagementService;
        $this->userService = $userService;
        $this->userGroupService = $userGroupService;
        $this->industryRepository = $industryRepository;
        $this->countyRepository = $countyRepository;
        $this->numberOfEmployeeService = $numberOfEmployeeService;
        $this->baseService = $baseService;
        $this->bookmarkCompanyService = $bookmarkCompanyService;
        $this->kindService = $kindService;
        $this->projectRepository = $projectRepository;
        $this->projectAttributeService = $projectAttributeService;

        // $this->middleware('can:accountSupper-Contractor')->only([
        //     'userManagementIndex',
        //     'companyInfoIndex',
        // ]);
    }


    /**
     * Page G010: Search member company
     *
     * @param GetListMembersRequest $request
     * @return View|JsonResponse
     */

    public function getListMembers(GetListMembersRequest $request)
    {
        $user = Auth::user();
        $companyId = $user['company_id'];
        $searchValue = $request->get('keyword');
        $userGroupIds = $request->get('user_group_ids');
        //get list members of company
        $members = $this->companyService->getListMembersByCompanyId($companyId, $searchValue, $userGroupIds);
        if ($request->ajax()) {
            return $this->respondSuccess(__('message.COMPLETE'), compact('members'));
        }
        //get list groups of company
        $userGroups = $this->companyService->getListUserGroupByCompanyId([$companyId])['data'] ?? [];
        $projectAttributes = $this->projectAttributeService->getProjectAttributes();
        $projectAttributes = $projectAttributes['data'] ?? [];
        //get member detail first
        $memberDetailTransformer = [];
        $memberFirst = $members->first();
        if ($memberFirst) {
            $memberDetailTransformer = Transformer::item(new GetMemberDetailTransformer(), $memberFirst)['data'];
        }

        return view('company.members.index', compact('members', 'userGroups', 'memberDetailTransformer', 'projectAttributes'));
    }

    /**
     * page management index D010
     *
     * @param GetListUsersRequest $request
     * @return void
     */
    public function userManagementIndex(GetListUsersRequest $request)
    {
        if (Gate::denies('company-available')) {
            abort(403);
        }
        if (!(Gate::allows('accountSupper') || Gate::allows('accountContractor'))) {
            abort(403);
        }

        $curentUser = Auth::user();
        $companyId = $curentUser->company_id;

        $params = array_merge(['company_id' => $companyId], $request->only(['mail_address', 'super_user_auth_flg', 'service_contractor_auth_flg', 'company_search_flg', 'user_groups']));
        $users = $this->userService->getUserLicense($params);
        $users = $users['data'] ?? [];

        $licenseData = $this->licenceManagementService->getNumberLicences($companyId);
        $licenseData = $licenseData['data'] ?? [];
        $userGroups = $this->userGroupService->getUserGroupByCompanyId($companyId);

        return view('company.user-managements.index', compact('licenseData', 'users', 'userGroups', 'curentUser'));
    }

    /**
     * Update info user D010
     *
     * @param UpdateMultiUserRequest $request
     * @return void
     */
    public function updateMultiUser(UpdateMultiUserRequest $request)
    {
        $user = Auth::user();
        $companyId = $user->company_id;
        $dataUsers = $request->get('users');

        $result = $this->companyService->updateMultiUser($dataUsers, $companyId);

        if (!$result) {
            $this->setSessionFlashError(trans('message.ERR_COM_0052'));
            return redirect()->back();
        }
        $this->setSessionFlashSuccess(trans('message.INF_COM_0052'));
        return redirect()->back();
    }

    /**
     * get company info - D020
     *
     * @return void
     */
    public function companyInfoIndex()
    {
        if (Gate::denies('accountMember')) {
            abort(403);
        }
        if (Gate::denies('company-available')) {
            abort(403);
        }

        $user = Auth::user();
        if (!$user) {
            $this->setSessionFlashError(trans('user.ERR_S.C_0001'));
            return redirect()->back();
        }
        $company = $this->companyService->getCompanyInfoByCompanyId($user->company_id);
        $industries = $this->industryRepository->all([], ['by' => 'display_order', 'type' => 'asc'], [], ['industry_id', 'industry_name', 'display_order']);
        $counties = $this->countyRepository->all([], ['by' => 'display_order', 'type' => 'asc'], [], ['county_name', 'county_id', 'display_order']);
        $numberOfEmployees = $this->numberOfEmployeeService->getListNumberOfEmployees();

        return view('company.info-settings.index', compact('company', 'industries', 'counties', 'numberOfEmployees'));
    }

    public function updateCompanyInfo(UpdateCompanyInformationRequest $request)
    {
        $user = Auth::user();

        if (!$user) {
            $this->setSessionFlashError(trans('user.ERR_S.C_0001'));
            return redirect()->back();
        }

        $companyId = $user->company_id;
        $params = $request->all();

        $data = $this->companyService->updateCompanyInformation($params, $companyId);

        if (empty($data) || $data['status'] == config('apps.general.error')) {
            $this->setSessionFlashError(trans('message.ERR_COM_0052'));
            return redirect()->back();
        }
        $this->setSessionFlashSuccess(trans('message.INF_COM_0052'));
        return redirect()->back();
    }

    /**
     * Get details company information
     * @param GetDetailsInformationRequest $request
     * @return JsonResponse
     */
    public function getDetailsInformation(GetDetailsInformationRequest $request)
    {
        //2. call addUserPayment with params in paymentHistoryService
        $data = $this->companyService->getDetailsInformation($request->company_id);
        //3. Return Response
        if (empty($data) || $data['status'] == config('apps.general.error')) {
            $this->respondWithError(trans('message.NOT_COMPLETE'));
        }
        return $this->respondSuccess(trans('message.COMPLETE'), $data['data']);
    }

    /**
     * Get graph by company
     * @param GetGraphByCompanyRequest $request
     * @return JsonResponse
     */
    public function getGraphByCompany(GetGraphByCompanyRequest $request)
    {
        //2. call getGraphByCompany with params in companyService
        $data = $this->companyService->getGraphByCompany($request->company_id);
        //3. Return Response
        if (empty($data) || $data['status'] == config('apps.general.error')) {
            return $this->respondWithError(trans('message.FAIL'));
        }
        return $this->respondSuccess(trans('message.SUCCESS'), $data['data']);
    }

    /**
     * Set bookmark company
     * @param SetBookmarkCompanyRequest $request
     * @return JsonResponse
     */
    public function setBookmarkCompany(SetBookmarkCompanyRequest $request)
    {
        $currentUser = $request->user();
        if (!$currentUser) {
            return $this->baseService->sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
        }
        //2. call getGraphByCompany with params in companyService
        $data = [];
        if ($request->is_bookmark == config('apps.general.is_not_bookmark')) {
            $data = $this->bookmarkCompanyService->deleteCompanyBookmark($request->company_id, $currentUser->user_id);
        } elseif ($request->is_bookmark == config('apps.general.is_bookmark')) {
            $data = $this->bookmarkCompanyService->addCompanyBookmark($request->company_id, $currentUser->user_id);
        }

        if ($data['status'] == config("apps.general.status_error_code")) {
            $data['data']['message'] = trans("message.ERR_H020_0001");
            return $this->respondSuccess(trans('message.SUCCESS'), $data['data']);
        }
        //3. Return Response
        if (empty($data) || $data['status'] == config('apps.general.error')) {
            return $this->respondWithError(trans('message.FAIL'));
        }
        return $this->respondSuccess(trans('message.SUCCESS'), $data['data']);
    }

    /**
     * Get list report task group by project
     * @param Request $request
     * @return JsonResponse
     */
    public function getListReportTaskGroupProject(Request $request): JsonResponse
    {
        try {
            if (empty($request->project_id)) {
                return $this->sendError(trans('message.NOT_COMPLETE'));
            }

            $project = $this->projectRepository->getById($request->project_id);
            $reportTaskGroupProject = [];
            if ($project) {
                $projectTransformer = Transformer::item(new ProjectBasicWithGraphTransformer(), $project)['data'];
                if (isset($projectTransformer['graph']['task_management'])) {
                    $reportTaskGroupProject = $projectTransformer['graph']['task_management'];
                }
            }

            return $this->respondSuccess(trans('message.SUCCESS'), $reportTaskGroupProject);
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());
            return $this->sendError(
                trans('message.ERR_EXCEPTION')
            );
        }
    }

    /**
     * Get member detail
     * @param Request $request
     * @return JsonResponse
     */
    public function getMemberDetail(Request $request): JsonResponse
    {
        try {
            if (empty($request->user_id)) {
                return $this->sendError(trans('message.NOT_COMPLETE'));
            }

            $orderBy = $request->order_by ?? [];
            $projectAttributeIds = $request->get('project_attribute_ids') ?? [];
            $memberDetail = $this->companyService->getMemberDetailByUserId($request->user_id, $projectAttributeIds, $orderBy);
            $bar = [];
            $pie = [];
            if ($memberDetail) {
                $memberDetailTransformer = Transformer::item(new GetMemberDetailTransformer(), $memberDetail)['data'];
                if ($memberDetailTransformer) {
                    if (isset($memberDetailTransformer['projects'][0]['graph']['task_management'])) {
                        $bar = $memberDetailTransformer['projects'][0]['graph']['task_management'];
                    }
                    if (isset($memberDetailTransformer['graph']['role_ownership'])) {
                        $pie = $memberDetailTransformer['graph']['role_ownership'];
                    }
                }
            }
            return $this->respondSuccess(__('message.SUCCESS'), compact('memberDetailTransformer', 'bar', 'pie'));
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());
            return $this->sendError(
                trans('message.ERR_EXCEPTION')
            );
        }
    }
}
