<?php

namespace App\Services;

use App\Repositories\NumberOfEmployeeRepository;
use App\Services\BaseService;

class NumberOfEmployeeService extends BaseService
{
    protected $numberOfEmployeeRepository;

    public function __construct(NumberOfEmployeeRepository $numberOfEmployeeRepository)
    {
        $this->numberOfEmployeeRepository = $numberOfEmployeeRepository;
    }

    /**
     * get list Number Of Employees orderby number_of_employees
     *
     * @return collection
     */
    public function getListNumberOfEmployees()
    {
        return $this->numberOfEmployeeRepository->all([], ['by' => 'create_datetime', 'type' => 'asc'], [], ['*']);
    }
}
