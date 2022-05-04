<?php

namespace App\Repositories;

use App\Models\DispColor;

class DispColorRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(DispColor::class);

        $this->fields = $this->getInstance()->getFillable();
    }
}
