<?php

namespace App\Http\Controllers\API\UserProject;

use App\Http\Controllers\Controller;
use App\Services\UserProjectService;
use Illuminate\Http\Request;

class UserProjectController extends Controller
{
    public function __construct(UserProjectService $userProjectService)
    {
        $this->userProjectService = $userProjectService;
    }

    public function listMyProject(Request $request)
    {
        $currentUser = auth('api')->user();
        $data = $this->userProjectService->listProjectByUser($currentUser->user_id, $request);
        $data['data'] = collect($data['data'])->values()->toArray();
        return response()->json($data);
    }

    private $userProjectService;
}
