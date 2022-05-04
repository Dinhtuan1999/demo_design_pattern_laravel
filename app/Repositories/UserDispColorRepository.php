<?php

namespace App\Repositories;

use App\Models\UserDispColor;

class UserDispColorRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(UserDispColor::class);

        $this->fields = $this->getInstance()->getFillable();
    }
}
