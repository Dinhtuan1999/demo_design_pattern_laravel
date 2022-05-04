<?php

namespace App\Http\Controllers\API\NumberOfEmployee;

use App\Helpers\Transformer;
use App\Http\Controllers\API\Controller;
use App\Services\NumberOfEmployeeService;
use App\Transformers\NumberOfEmployee\NumberOfEmployeeTransfomer;

class NumberOfEmployeeController extends Controller
{
    private $numberOfEmployeeService;

    public function __construct(NumberOfEmployeeService $numberOfEmployeeService)
    {
        $this->numberOfEmployeeService = $numberOfEmployeeService;
    }


    public function getListNumberOfEmployees()
    {
        $numberOfEmployees = $this->numberOfEmployeeService->getListNumberOfEmployees();
        return $this->respondSuccess('', Transformer::collection(new NumberOfEmployeeTransfomer(), $numberOfEmployees));
    }
}
