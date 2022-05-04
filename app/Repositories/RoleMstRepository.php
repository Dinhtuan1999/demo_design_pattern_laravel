<?php


namespace App\Repositories;


use App\Models\RoleMst;

class RoleMstRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(RoleMst::class);
        $this->fields = RoleMst::FIELDS;
    }
}
