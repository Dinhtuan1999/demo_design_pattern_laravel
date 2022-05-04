<?php

namespace App\Http\Controllers\PC\ProjectAttribute;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\ProjectAttribute\UpdateProjectAttributeRequest;
use App\Services\ProjectAttributeService;
use Illuminate\Support\Facades\Auth;

class ProjectAttributeController extends Controller
{
    private $projectAttributeService;

    public function __construct(ProjectAttributeService $projectAttributeService)
    {
        $this->projectAttributeService = $projectAttributeService;
    }

    public function updateProjectAttribute(UpdateProjectAttributeRequest $request)
    {
        $currentUserId = Auth::user()->user_id;
        $result = $this->projectAttributeService->updateProjectAttribute($request, $currentUserId);

        if (empty($result) || $result['status'] == config('apps.general.error')) {
            return $this->respondWithError(trans('message.NOT_COMPLETE'));
        }

        return redirect()->back();
    }
    public function getListProjectAttribute()
    {
        $projectAttributes = $this->projectAttributeService->getProjectAttributes();
        return  $projectAttributes;
    }
}
