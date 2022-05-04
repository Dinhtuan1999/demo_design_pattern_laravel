<?php

namespace App\Services;

use App\Models\RoleMst;
use App\Repositories\RoleRepository;
use App\Repositories\ProjectParticipantRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Services\BaseService;

class RoleService extends BaseService
{
    private $roleRepo;
    private $projectParticipantRepo;


    public function __construct(
        RoleRepository $roleRepo,
        ProjectParticipantRepository $projectParticipantRepo
    ) {
        $this->roleRepo = $roleRepo;
        $this->projectParticipantRepo = $projectParticipantRepo;
    }

    public function addOrUpdateRole($currentUser, $listProjectRoles)
    {
        $oldListProjectRoles = $this->roleRepo->getRoleIdByCompanyId($currentUser->company_id);
        if (empty($listProjectRoles)) {
            // delete all role
            $this->deleteAllRole($oldListProjectRoles, $currentUser);
            return self::sendResponse([ trans('message.SUCCESS') ], []);
        } else {
            // delete role
            $role_ids = [];
            foreach ($listProjectRoles as $listProjectRole) {
                if (isset($listProjectRole['role_id'])) {
                    array_push($role_ids, $listProjectRole['role_id']);
                }
            }

            // Delete  roles that are not in the list of roles that are passed
            $this->roleRepo->getModel()::where('company_id', $currentUser->company_id)->whereNotIn('role_id', $role_ids)->update([
                'delete_flg' => config('apps.general.is_deleted'),
                'update_user_id'    => $currentUser->user_id,
            ]);

            foreach ($listProjectRoles as $listProjectRole) {
                if (!isset($listProjectRole['role_id']) && !empty($listProjectRole['role_name'])) {
                    // create role
                    $this->create($listProjectRole, $currentUser);
                    continue;
                }
                $projectRole = $this->roleRepo->findByField('role_id', $listProjectRole['role_id']);
                if (!empty($projectRole->role_id)) {
                    // update role
                    $this->deleteAndUpdateProjectRole($listProjectRole, $currentUser);
                }
            }
        }

        $roles = $this->roleRepo->getProjectRoleByCompanyId($currentUser->company_id);

        return self::sendResponse([ trans('message.SUCCESS') ], $roles);
    }

    public function create($listProjectRole, $currentUser)
    {
        $result           = [];
        $result['status'] = config('apps.general.error');

        $projectRole = [
            'role_id'           => AppService::generateUUID(),
            'company_id'        => $currentUser->company_id,
            'role_name'         => $listProjectRole['role_name'],
            'create_datetime'   => date('Y-m-d H:i:s'),
            'update_datetime'   => date('Y-m-d H:i:s'),
            'create_user_id'    => $currentUser->user_id,
            'update_user_id'    => $currentUser->user_id,
        ];

        $this->roleRepo->store($projectRole);
        $result['status']  = config('apps.general.success');
        $result['message'] = [trans('message.SUCCESS')];

        return $result;
    }

    public function deleteAndUpdateProjectRole($listProjectRole, $currentUser)
    {
        $result = [];
        // update project role
        $updateProjectRole = [
            'role_name'         => $listProjectRole['role_name'],
            'delete_flg'        => config('apps.general.not_deleted'),
            'update_user_id'    => $currentUser->user_id,
        ];
        $this->roleRepo->updateByField('role_id', $listProjectRole['role_id'], $updateProjectRole);
        $result['status'] = config('apps.general.success');
        $result['message'] = [trans('message.SUCCESS')];
        return $result;
    }

    public function deleteAllRole($oldListProjectRoles, $currentUser)
    {
        $result = [];
        $this->roleRepo->deleteRoleByRoleIds($oldListProjectRoles, $currentUser);
        $result['status'] = config('apps.general.success');
        $result['message'] = [trans('message.SUCCESS')];
        return $result;
    }

    public function getListRole($company_id)
    {
        $result           = [];
        $roles = $this->roleRepo->getProjectRoleByCompanyId($company_id);
        // Check if there are members using the role yet
        foreach ($roles as $role) {
            $item = $this->projectParticipantRepo->getByCol('role_id', $role->role_id);
            if ($item) {
                $role->exist = config('apps.role.exist');
            } else {
                $role->exist = config('apps.role.not_exist');
            }
        }
        $result['data']       = $roles;
        $result['status']     = config('apps.general.success');
        $result['message']    = [trans('message.SUCCESS')];
        return $result;
    }

    public function validateProjectRoleForm(Request $request)
    {
        return Validator::make(
            $request->all(),
            [
                'project_roles'                  => ['nullable', 'array'],
                'project_roles.*.role_id'        => ['nullable'],
                'project_roles.*.role_name'      => ['nullable', 'max:50'],
            ],
            [
                'project_roles.array'                          => trans('message.INF_COM_0007'),
                'project_roles.*.role_name.max'                => trans('message.ERR_COM_0002', ['attribute' => trans('label.role.role_name'), 'max' => '50']),
            ]
        );
    }
}
