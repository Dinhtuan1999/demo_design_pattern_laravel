<?php

namespace App\Repositories;

use App\Models\Company;
use App\Models\NumberOfEmployee;
use App\Services\AppService;
use Illuminate\Support\Facades\DB;

class NumberOfEmployeeRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(NumberOfEmployee::class);
        $this->fields = NumberOfEmployee::FIELDS ;
    }
}
