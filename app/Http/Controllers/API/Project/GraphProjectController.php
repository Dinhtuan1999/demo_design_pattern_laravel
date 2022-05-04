<?php

namespace App\Http\Controllers\API\Project;

use App\Http\Controllers\API\Controller;
use App\Services\GraphProjectService;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GraphProjectController extends Controller
{
    private $taskService;

    public function __construct(
        GraphProjectService $graphProjectService,
        TaskService $taskService
    ) {
        $this->graphProjectService = $graphProjectService;
        $this->taskService = $taskService;
    }

    /**
     * A.C040.12 data graph
     *
     * @param  mixed $projectId
     * @return \Illuminate\Http\JsonResponse
     */
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
     * C070 list detail task
     * @param Request $request
     * @return array|mixed
     */
    public function getListDetailTask(Request $request)
    {
        if ($request->has('task_group_id')
            && $request->input('task_group_id') != config('apps.task.task_detail.others')
            && !$request->has('task_status_id')) {
            $response = $this->taskService->getTaskByProjectTaskGroups($request->input('project_id'), $request->input('task_group_id'));
        } else {
            $response = $this->taskService->getGraphDetail(
                $request->input('project_id'),
                $request->input('task_status_id'),
                $request->input('manager_id'),
                $request->input('task_group_id')
            );
        }

        return $response;
    }

    private $graphProjectService;
}
