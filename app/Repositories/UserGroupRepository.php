<?php

namespace App\Repositories;

use App\Models\UserGroup;

class UserGroupRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(UserGroup::class);
    }

    public function getUserGroup($queryParam = [])
    {
        return $this->getModel()::query()->where($queryParam)->orderBy('user_group_name', 'DESC')
                    ->get();
    }
}
