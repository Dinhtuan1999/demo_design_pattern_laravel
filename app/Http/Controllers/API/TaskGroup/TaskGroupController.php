<?php

namespace App\Http\Controllers\API\TaskGroup;

use App\Http\Controllers\API\Controller;
use App\Http\Requests\TaskGroup\CreateTaskGroupRequest;
use App\Services\TaskGroupService;

class TaskGroupController extends Controller
{
    private $taskGroupService;

    public function __construct(TaskGroupService $taskGroupService)
    {
        $this->taskGroupService = $taskGroupService;
    }

    public function storeTaskGroup(CreateTaskGroupRequest $request)
    {
        $data = $request->only(['project_id', 'group_name', 'disp_color_id']);
        $projectId = $request->get('project_id');
        $result = $this->taskGroupService->createTaskGroup($projectId, $data);
        if (!$result) {
            return $this->respondWithError(trans('message.FAIL'));
        }
        return $this->respondSuccess(trans('message.SUCCESS'), $result);
    }
}
