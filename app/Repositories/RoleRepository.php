<?php

namespace App\Repositories;

use App\Models\RoleMst;

class RoleRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(RoleMst::class);
        $this->fields = RoleMst::FIELDS;
    }

    public function getProjectRoleByCompanyId($company_id)
    {
        $model = $this->getModel();

        $model = $model::where('company_id', $company_id)
            ->where('delete_flg', config('apps.general.not_deleted'))->orderBy("create_datetime", "asc")
            ->get(['role_id', 'role_name']);
        return $model;
    }

    public function getRoleIdByCompanyId($company_id)
    {
        $model = $this->getModel();

        $model = $model::select('role_id')->where('company_id', $company_id)
            ->where('delete_flg', config('apps.general.not_deleted'))
            ->pluck('role_id')->toArray();
        return $model;
    }

    public function deleteRoleByRoleIds($role_ids, $currentUser)
    {
        $model = $this->getModel();

        $model = $model::where('company_id', $currentUser->company_id)->whereIn('role_id', $role_ids)->update([
            'delete_flg' => config('apps.general.is_deleted'),
            'update_user_id'    => $currentUser->user_id,
        ]);
        return $model;
    }
}
