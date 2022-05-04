<?php

namespace App\Http\Controllers\API\UserGroup;

use App\Http\Controllers\API\Controller;
use App\Http\Requests\UserGroup\CreateOrUpdateUserGroupRequest;
use App\Http\Requests\UserGroup\DeleteUserGroupRequest;
use App\Repositories\UserGroupRepository;
use App\Services\UserGroupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserGroupController extends Controller
{
    private $userGroupService;
    private $userGroupRepository;

    public function __construct(UserGroupService $userGroupService, UserGroupRepository $userGroupRepository)
    {
        $this->userGroupService = $userGroupService;
        $this->userGroupRepository = $userGroupRepository;
    }

    public function getUserGroup()
    {

        //1.get current companyID
        $user = Auth::user();
        $companyId = $user->company_id;

        $data = $this->userGroupService->getUserGroup($companyId);

        if ($data['status'] == config("apps.general.error")) {
            return $this->respondWithError($data['message']);
        }

        return $this->respondSuccess('success', $data['data']);
    }

    public function createOrUpdateUserGroups(CreateOrUpdateUserGroupRequest $request)
    {
        $user = Auth::user();

        if (!$user) {
            return $this->respondWithError(trans('user.ERR_S.C_0001'));
        }
        $result = $this->userGroupService->createOrUpdateUserGroupsByUser($user, $request);
        if (!$result) {
            return $this->respondWithError(trans('message.NOT_COMPLETE'));
        }
        return $this->respondSuccess(trans('message.COMPLETE'));
    }

    public function delete(DeleteUserGroupRequest $request)
    {
        $userGroupId = $request->get('user_group_id');
        $userGroup = $this->userGroupRepository->findByField('user_group_id', $userGroupId);
        if (!$userGroup) {
            return $this->respondWithError(trans('message.ERR_NOTFOUND_MODEL', ['field' => 'user_group_id']));
        }
        $result = $this->userGroupService->deleteUserGroupByUserGroupId($userGroupId);
        if (!$result) {
            return $this->respondWithError(trans('message.NOT_COMPLETE'));
        }
        return $this->respondSuccess(trans('message.COMPLETE'));
    }
}
