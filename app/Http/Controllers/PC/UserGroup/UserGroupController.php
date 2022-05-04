<?php

namespace App\Http\Controllers\PC\UserGroup;

use App\Http\Controllers\PC\Controller;
use App\Http\Requests\UserGroup\CreateOrUpdateUserGroupRequest;
use App\Http\Requests\UserGroup\DeleteUserGroupRequest;
use App\Repositories\UserGroupRepository;
use App\Services\UserGroupService;
use Illuminate\Support\Facades\Auth;

class UserGroupController extends Controller
{
    protected $userGroupRepository;

    public function __construct(UserGroupService $userGroupService, UserGroupRepository $userGroupRepository)
    {
        $this->userGroupService = $userGroupService;
        $this->userGroupRepository = $userGroupRepository;
    }
    public function getListUserGroupManagement()
    {
        $user = Auth::user();
        $companyId = $user->company_id;
        $data = $this->userGroupService->getUserGroup($companyId);
        return view('user-group-management.user-group-management', compact('data'));
    }
    public function getUserGroup()
    {

        //1.get current companyID
        $user = Auth::user();
        $companyId = $user->company_id;

        $data = $this->userGroupService->getUserGroup($companyId);

        //return view('users.group', compact('data'));
    }

    public function createOrUpdateUserGroups(CreateOrUpdateUserGroupRequest $request)
    {
        $user = Auth::user();

        if (!$user) {
            $this->setSessionFlashError(trans('user.ERR_S.C_0001'));
            return redirect()->back();
        }
        $result = $this->userGroupService->createOrUpdateUserGroupsByUser($user, $request);
        if (!$result) {
            $this->setSessionFlashError(trans('message.NOT_COMPLETE'));
            return redirect()->back();
        }
        $this->setSessionFlashError(trans('message.COMPLETE'));
        return redirect()->back();
    }

    public function delete(DeleteUserGroupRequest $request)
    {
        $userGroupId = $request->get('user_group_id');
        $userGroup = $this->userGroupRepository->findByField('user_group_id', $userGroupId);
        if (!$userGroup) {
            $this->setSessionFlashError(trans('message.ERR_NOTFOUND_MODEL', ['field' => 'user_group_id']));
            if ($request->ajax()) {
                return response()->json([ 'error' => trans('message.ERR_NOTFOUND_MODEL', ['field' => 'user_group_id'])]);
            }
            return redirect()->back();
        }
        $result = $this->userGroupService->deleteUserGroupByUserGroupId($userGroupId);
        if (!$result) {
            $this->setSessionFlashError(trans('message.NOT_COMPLETE'));
            if ($request->ajax()) {
                return response()->json([ 'error' => trans('message.ERR_NOTFOUND_MODEL', ['field' => 'user_group_id'])]);
            }
            return redirect()->back();
        }
        $this->setSessionFlashError(trans('message.COMPLETE'));
        return response()->json([ 'success' => trans('message.COMPLETE')]);
    }
    private $userGroupService;
}
