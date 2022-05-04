<?php

namespace App\Http\Controllers\API\Role;

use App\Http\Controllers\Controller;
use App\Repositories\RoleRepository;
use App\Repositories\CompanyRepository;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Role\ApiRoleFormRequest;

class RoleController extends Controller
{
    private $roleRepo;
    private $roleService;
    private $companyRepo;

    public function __construct(
        RoleRepository   $roleRepo,
        RoleService      $roleService,
        CompanyRepository $companyRepo
    ) {
        $this->roleRepo = $roleRepo;
        $this->roleService = $roleService;
        $this->companyRepo = $companyRepo;
    }

    public function addOrUpdateRole(ApiRoleFormRequest $request)
    {
        $result           = [];
        $result['status'] = config('apps.general.error');

        $user = Auth::guard('api')->user();
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

        return response()->json($result);
    }

    public function getListRole()
    {
        $user = Auth::guard('api')->user();

        $result = $this->roleService->getListRole($user->company_id);

        return response()->json($result);
    }
}
