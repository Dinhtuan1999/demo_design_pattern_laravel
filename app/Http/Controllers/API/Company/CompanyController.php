<?php

namespace App\Http\Controllers\API\Company;

use App\Helpers\Transformer;
use App\Http\Controllers\API\Controller;
use App\Http\Requests\Company\GetListMembersRequest;
use App\Http\Requests\Company\GetListUserGroupRequest;
use App\Http\Requests\Company\SearchTasksRequest;
use App\Http\Requests\GetListKindsByCompanyIdRequest;
use App\Services\CompanyService;
use App\Services\TaskService;
use App\Transformers\Company\ListKindsByCompanyTransformer;
use App\Http\Requests\Company\GetMemberDetailByUserIdRequest;
use App\Services\KindService;
use App\Services\UserGroupService;
use App\Transformers\Company\GetMemberDetailTransformer;
use App\Transformers\Company\ListMemberTransformer;
use App\Transformers\UserGroup\UserGroupTransformer;
//use GuzzleHttp\Psr7\Request;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompanyController extends Controller
{
    private $companyService;
    private $kindService;
    private $userGroupService;
    private $taskService;

    public function __construct(
        CompanyService $companyService,
        KindService $kindService,
        UserGroupService $userGroupService,
        TaskService $taskService
    ) {
        $this->companyService = $companyService;
        $this->kindService = $kindService;
        $this->userGroupService = $userGroupService;
        $this->taskService = $taskService;
    }


    /**
     * api get listmember by company id
     *
     * @param GetListMembersRequest $request
     * @return void
     */
    public function getListMembers(GetListMembersRequest $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->respondWithError(trans('user.ERR_S.C_0001'));
        }
        $companyId = $user->company_id;
        $searchValue = $request->get('search_value');
        $userGroupIds = $request->get('user_group_ids');

        $members = $this->companyService->getListMembersByCompanyId($companyId, $searchValue, $userGroupIds);
        return $this->respondSuccess(null, Transformer::pagination(new ListMemberTransformer(), $members));
    }

    public function getListKindsByCompanyId(GetListKindsByCompanyIdRequest $request)
    {
        $companyId = $request->get('company_id');
        $kinds = $this->companyService->getListKindsByCompanyId($companyId);

        return $this->respondSuccess(null, Transformer::collection(new ListKindsByCompanyTransformer(), $kinds));
    }

    /**
     * get list kinds with project attribute
     *
     * @param GetListKindsByCompanyIdRequest $request
     * @return object
     */
    public function getListKinds()
    {
        $kinds = $this->kindService->getListKindsWithProjectAttribute();
        return $this->respondSuccess(null, Transformer::collection(new ListKindsByCompanyTransformer(), $kinds));
    }

    public function getMemberDetailByUserId(GetMemberDetailByUserIdRequest $request)
    {
        $userId = $request->get('user_id');
        $orderBy = $request->get('order_by', []);
        $projectAttributeIds = $request->get('project_attribute_ids') ?? [];

        $memberDetail = $this->companyService->getMemberDetailByUserId($userId, $projectAttributeIds, $orderBy);

        if (!$memberDetail) {
            return $this->respondWithError(trans('user.ERR_S.C_0001'));
        }

        return $this->respondSuccess(null, Transformer::item(new GetMemberDetailTransformer(), $memberDetail));
    }
    public function getListUserGroupByCompanyId(GetListUserGroupRequest $request)
    {
        $orderBy =validateOrderByHelper($request->order_by, ['user_group_name', 'asc']);
        $user = Auth::user();
        if (!$user) {
            return $this->respondWithError(trans('user.ERR_S.C_0001'));
        }
        $companyId = $user->company_id;
        $userGroup =  $this->userGroupService->getListUserGroupByCompanyId($companyId, $orderBy);

        return $this->respondSuccess(null, Transformer::collection(new UserGroupTransformer(), $userGroup));
    }

    /**
     * screen G030 - search tasks
     * @param SearchTasksRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchTasks(SearchTasksRequest $request)
    {
        $currentUser = Auth::user();
        $filters = [
            'key_word' => $request->input('key_word'),
            'project_ids' => $request->input('project_ids'),
            'user_ids' => $request->input('user_ids'),
            'order_by_project_name' => $request->input('order_by_project_name'),
            'order_by_group_name' => $request->input('order_by_group_name'),
            'order_by_task_name' => $request->input('order_by_task_name')
        ];
        $result = $this->taskService->searchTasksByUser($currentUser, $filters);

        return response()->json($result);
    }
}
