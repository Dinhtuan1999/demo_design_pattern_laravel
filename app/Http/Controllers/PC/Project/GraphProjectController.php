<?php

namespace App\Http\Controllers\PC\Project;

use App\Http\Controllers\PC\Controller;
use App\Services\GraphProjectService;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GraphProjectController extends Controller
{
    private $taskService;

    public function __construct(GraphProjectService $graphProjectService, TaskService $taskService)
    {
        $this->graphProjectService = $graphProjectService;
        $this->taskService = $taskService;
    }

    public function dataGraph($projectId)
    {
        try {
            return $this->graphProjectService->getGraphData($projectId);
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            return $this->respondWithError([trans('message.ERR_EXCEPTION')]);
        }
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getListDetailTask(Request $request)
    {
        if ($request->taskGroupId) {
            $response = $this->taskService->getTaskByProjectTaskGroups($request->projectId, $request->taskGroupId);
        } else {
            $response = $this->taskService->getGraphDetail($request->projectId, $request->taskStatusId);
        }

        return $response;
    }


    private $graphProjectService;
}
