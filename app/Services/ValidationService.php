<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ValidationService
{
    public function getTaskByProject(Request $request)
    {
        return Validator::make(request()->all(), [
            'project_id' => 'required',
        ], [
            'project_id.required' => trans(
                'validation.required',
                ['attribute' => trans('validation_attribute.project_id')]
            ),
        ]);
    }
}
