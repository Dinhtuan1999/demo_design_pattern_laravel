<?php

namespace App\Http\Controllers\PC\Role;

use App\Http\Controllers\Controller;
use App\Repositories\RoleRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\ProjectParticipantRepository;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleController extends Controller
{
    private $roleRepo;
    private $roleService;
    private $companyRepo;
    private $projectParticipantRepo;
    public function __construct(
        RoleRepository   $roleRepo,
        RoleService      $roleService,
        CompanyRepository $companyRepo,
        ProjectParticipantRepository $projectParticipantRepo
    ) {
        $this->roleRepo = $roleRepo;
        $this->roleService = $roleService;
        $this->companyRepo = $companyRepo;
        $this->projectParticipantRepo = $projectParticipantRepo;
    }

    public function addOrUpdateRole(Request $request)
    {
        $result           = [];
        $result['status'] = config('apps.general.error');
        $validator = $this->roleService->validateProjectRoleForm($request);
        if ($validator->fails()) {
            $result['status']  = config('apps.general.error');
            $result['message'] = $validator->errors()->all();
            $result['error_code'] = config('apps.general.error_code', 600);
            return $result;
        }

        $user = Auth::user();
        if (!$user->company_id) {
            $result['status']  = config('apps.general.error');
            $result['message'] = [trans('message.FAIL')];
            $result['error_code'] = config('apps.general.error_code', 600);
            return $result;
        }

        $listProjectRoles = $request->input('project_roles');

        if (!isset($listProjectRoles)) {
            $result['status']  = config('apps.general.error');
            $result['message'] = [trans('message.FAIL')];
            $result['error_code'] = config('apps.general.error_code', 600);
            return $result;
        }

        $result = $this->roleService->addOrUpdateRole($user, $listProjectRoles);

        return $result;
    }

    public function getListRole(Request $request)
    {
        $result           = [];
        $result['status'] = config('apps.general.error');
        $user = Auth::user();

        $company_id =$user->company_id;
        if (!isset($company_id)) {
            $result['status']  = config('apps.general.error');
            $result['message'] = [trans('message.FAIL')];
            $result['error_code'] = config('apps.general.error_code', 600);
            return $result;
        }

        $company = $this->companyRepo->getByCols(['company_id' => $company_id, 'delete_flg' => config('apps.general.not_deleted')]);
        if (!$company) {
            $result['status']  = config('apps.general.error');
            $result['message'] = [trans('message.ERR_COM_0011', [ 'attribute' => trans('label.role.company_id') ])];
            $result['error_code'] = config('apps.general.error_code', 600);
            return $result;
        }

        $result = $this->roleService->getListRole($company->company_id);

        return $result;
    }
    public function checkRoleExit(Request $request)
    {
        $result           = [];
        $role = $this->projectParticipantRepo->getByCol('role_id', $request->role_id);
        // if role exit then return false
        if ($role) {
            $result['status']  = config('apps.general.error');
            return $result;
        } else {
            $result['status']  = config('apps.general.success');
            return $result;
        }
    }
}
