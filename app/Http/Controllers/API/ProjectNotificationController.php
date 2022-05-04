<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\Project\ProjectRequest;
use App\Http\Requests\ProjectNotification\CreateProjectNotificationRequest;
use App\Http\Requests\ProjectNotification\EditProjectNotificationRequest;
use App\Http\Requests\ProjectNotification\ProjectNotificationRequest;
use App\Services\CompanyService;
use App\Services\ProjectNotificationService;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Services\BaseService;
use App\Repositories\ProjectRepository;
use App\Repositories\ProjectNotificationRepository;

class ProjectNotificationController extends Controller
{
    protected $projectNotificationService;
    protected $companyService;
    protected $baseService;
    protected $projectRepo;
    protected $projectNotificationRepo;
    protected $projectService;

    public function __construct(
        ProjectNotificationService      $projectNotificationService,
        CompanyService                  $companyService,
        BaseService                     $baseService,
        ProjectRepository               $projectRepo,
        ProjectNotificationRepository   $projectNotificationRepo,
        ProjectService  $projectService
    ) {
        $this->projectNotificationService   = $projectNotificationService;
        $this->companyService               = $companyService;
        $this->baseService                  = $baseService;
        $this->projectRepo                  = $projectRepo;
        $this->projectNotificationRepo      = $projectNotificationRepo;
        $this->projectService = $projectService;
    }

    public function deleteProjectNotification(ProjectNotificationRequest $request)
    {
        $currentUser = auth('api')->user();
        if (!$currentUser) {
            return $this->baseService->sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
        }

        // check exists project Notification
        $projectNotification = $this->projectNotificationRepo->getByCols([
            'project_notice_id' => $request->input('project_notice_id'),
            'delete_flg'        => config('apps.general.not_deleted')
        ]);
        if (!$projectNotification) {
            return $this->baseService->sendError(
                [trans('message.ERR_COM_0011', ['attribute' => 'label.general.project_notice_id'])],
                [],
                config('apps.general.error_code', 600)
            );
        }

        $result = $this->projectNotificationService->deleteProjectNotification(
            $currentUser->user_id,
            $projectNotification
        );

        return response()->json($result);
    }

    public function listProjectNotification(ProjectRequest $request)
    {
        $result = $this->projectNotificationService->getProjectNotification($request->input('project_id'), false);

        return response()->json($result);
    }

    public function creteProjectNotification(CreateProjectNotificationRequest $request)
    {
        $currentUser = auth('api')->user();

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

    public function editProjectNotification(EditProjectNotificationRequest $request)
    {
        $currentUser = auth('api')->user();
        if (!$currentUser) {
            return $this->baseService->sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
        }

        // check exists project
        $project = $this->projectRepo->getByCols([
            'project_id' => $request->input('project_id'),
            'delete_flg' => config('apps.general.not_deleted')
        ]);
        if (!$project) {
            return $this->baseService->sendError(
                [trans('message.ERR_COM_0011', ['attribute' => trans('label.task.project_id') ])],
                [],
                config('apps.general.error_code', 600)
            );
        }

        // check exists project Notification
        $projectNotification = $this->projectNotificationRepo->getByCols([
            'project_notice_id' => $request->input('project_notice_id'),
            'delete_flg'        => config('apps.general.not_deleted')
        ]);
        if (!$projectNotification) {
            return $this->baseService->sendError(
                [trans('message.ERR_COM_0011', ['attribute' => 'label.general.project_notice_id'])],
                [],
                config('apps.general.error_code', 600)
            );
        }

        $result = $this->projectNotificationService->editProjectNotification(
            $currentUser->user_id,
            $projectNotification,
            $request->input('project_id'),
            $request->input('project_notice_message'),
            $request->input('message_notice_start_date'),
            $request->input('message_notice_end_date')
        );

        return response()->json($result);
    }

    /**
     * Get All Notification of Project In Progress by company_id
     * TODO: A.E020.1
     * 2022-02-17
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listNotificationProjectInProgress(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return $this->respondWithError(trans('message.ERR_S.C_0001'));
        }
        $companyId = $user->company_id;
        if (!$companyId) {
            return $this->respondWithError(trans('validation.object_not_exist', ['object' => trans('general.company')]));
        }
        // Get data notification
        $result = $this->projectNotificationService->getListNotificationProjectInProgress($companyId);

        return response()->json($result);
    }

    /**
     * Add Notification Project
     * TODO: A.E020.2
     * 2020-02-21
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addNotificationToProject(Request $request)
    {
        // Validation
        $validator = Validator::make(request()->all(), [
            'company_id' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
            'notice_message' => 'required',
        ], [
            'company_id.required' => trans('validation.required', ['attribute' => trans('label.company.company_id')]),
            'start_date.required' => trans('validation.required', ['attribute' => trans('validation_attribute.message_notice_start_date')]),
            'end_date.required' => trans('validation.required', ['attribute' => trans('validation_attribute.message_notice_end_date')]),
            'notice_message.required' => trans('validation.required', ['attribute' => trans('validation_attribute.project_notice_message')]),
        ]);

        if ($validator->fails()) {
            $response['status'] = config('apps.general.error');
            $response['message'] = $validator->errors()->all();
            $response['message_id'] = ['validation.required'];
            $response['error_code'] = config('apps.general.error_code');
            return response()->json($response);
        }

        // Check exist company
        $existCompany = $this->companyService->getCompanyInformation($request->input('company_id'));
        if ($existCompany['status'] == -1) {
            $response['status'] = config('apps.general.error');
            $response['message'] = [trans('validation.object_not_exist', ['object' => trans('label.company.company_id')])];
            $response['message_id'] = ['validation.object_not_exist'];
            $response['error_code'] = config('apps.general.error_code');
            return response()->json($response);
        }

        // Add project to notification
        $result = $this->projectNotificationService->addProjectToNotification(
            $request->input('company_id'),
            $request->user()->user_id,
            $request->input('notice_message'),
            $request->input('start_date'),
            $request->input('end_date'),
        );

        return response()->json($result);
    }
}
