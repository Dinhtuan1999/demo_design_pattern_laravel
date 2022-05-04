<?php

namespace App\Http\Controllers\PC;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectNotification\CreateProjectNotificationRequest;
use App\Http\Requests\ProjectNotification\EditProjectNotificationRequest;
use App\Http\Requests\ProjectNotification\ProjectNotificationRequest;
use App\Http\Requests\ProjectRequest;
use App\Services\ProjectNotificationService;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Class ProjectNotificationController
 * @package App\Http\Controllers\PC
 */
class ProjectNotificationController extends Controller
{
    protected $projectNotificationService;
    protected $projectService;

    public function __construct(
        ProjectNotificationService $projectNotificationService,
        ProjectService $projectService
    ) {
        $this->projectNotificationService = $projectNotificationService;
        $this->projectService = $projectService;
    }

    /**
     * Get list project notification
     * @param ProjectRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectNotification(ProjectRequest $request)
    {
        $result = $this->projectNotificationService->getProjectNotification($request->input('project_id'));

        if (count($result['data']) == 0) {
            $result['data'] = null;
        } else {
            $result['data']= view('project.response.list_project_notification', [
                'data' => $result['data']
            ])->render();
        }

        return response()->json($result);
    }

    /**
     * Add project notification
     * @param CreateProjectNotificationRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addProjectNotification(CreateProjectNotificationRequest $request)
    {
        $currentUser = Auth::user();

        $record = $this->projectService->checkRecord(
            $request->input('project_id')
        );

        if ($record['status'] === config('apps.general.error')) {
            return response()->json($record);
        }

        $result = $this->projectNotificationService->creteProjectNotification(
            $currentUser->user_id,
            $request->input('project_id'),
            $request->input('project_notice_message'),
            $request->input('message_notice_start_date'),
            $request->input('message_notice_end_date')
        );

        return response()->json($result);
    }

    /**
     * Edit project notification
     * @param EditProjectNotificationRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function editProjectNotification(EditProjectNotificationRequest $request)
    {
        $currentUser = Auth::user();

        // check exists project
        $record = $this->projectService->checkRecord(
            $request->input('project_id')
        );

        if ($record['status'] === config('apps.general.error')) {
            return response()->json($record);
        }

        // check exists project Notification
        $record = $this->projectNotificationService->checkRecord($request->input('project_notice_id'));

        if ($record['status'] === config('apps.general.error')) {
            return response()->json($record);
        }

        $result = $this->projectNotificationService->editProjectNotification(
            $currentUser->user_id,
            $record['data'],
            $request->input('project_id'),
            $request->input('project_notice_message'),
            $request->input('message_notice_start_date'),
            $request->input('message_notice_end_date')
        );

        return response()->json($result);
    }

    /**
     * Delete project notification
     * @param ProjectNotificationRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteProjectNotification(ProjectNotificationRequest $request)
    {
        $currentUser = Auth::user();

        // check exists project Notification
        $record = $this->projectNotificationService->checkRecord($request->input('project_notice_id'));

        if ($record['status'] === config('apps.general.error')) {
            return response()->json($record);
        }

        $result = $this->projectNotificationService->deleteProjectNotification(
            $currentUser->user_id,
            $record['data']
        );

        return response()->json($result);
    }
}
