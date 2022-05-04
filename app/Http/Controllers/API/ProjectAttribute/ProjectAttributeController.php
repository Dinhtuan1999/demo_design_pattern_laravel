<?php

namespace App\Http\Controllers\API\ProjectAttribute;

use App\Http\Controllers\API\Controller;
use App\Http\Requests\ProjectAttribute\UpdateProjectAttributeRequest;
use App\Repositories\ProjectRepository;
use App\Repositories\ProjectAttributeRepository;
use App\Repositories\ProjectOwnedAttributeRepository;
use App\Services\ProjectAttributeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProjectAttributeController extends Controller
{
    private $projectAttributeService;
    private $projectRepo;
    private $projectAttributeRepo;
    private $projectOwnedAttributeRepo;

    public function __construct(
        ProjectAttributeService $projectAttributeService,
        ProjectRepository $projectRepo,
        ProjectAttributeRepository $projectAttributeRepo,
        ProjectOwnedAttributeRepository $projectOwnedAttributeRepo
    ) {
        $this->projectAttributeService = $projectAttributeService;
        $this->projectRepo = $projectRepo;
        $this->projectAttributeRepo = $projectAttributeRepo;
        $this->projectOwnedAttributeRepo = $projectOwnedAttributeRepo;
    }

    public function updateProjectAttribute(UpdateProjectAttributeRequest $request)
    {
        $result = [];

        if ($request->has('type_action') && $request->get('type_action') == 'update') {
            if (!$this->projectRepo->isExists($request->data['project_id']) || !$this->projectAttributeRepo->isExists($request->data['project_attribute_id'])) {
                return $this->respondWithError([trans('message.NOT_COMPLETE')]);
            }
            if ($this->projectOwnedAttributeRepo->getByCols(['project_id' => $request->data['project_id'], 'project_attribute_id' => $request->data['project_attribute_id']])) {
                return $this->respondWithError([trans('message.INF_COM_0012', ['attribute' => trans('label.project_owned_attribute.id')])]);
            }

            $currentUserId = Auth::user()->user_id;
            $result = $this->projectAttributeService->updateProjectAttribute($request, $currentUserId);

            if (empty($result) || $result['status'] == config('apps.general.error')) {
                return $this->respondWithError([trans('message.NOT_COMPLETE')]);
            }
        } elseif ($request->has('type_action') && $request->get('type_action') == 'remove') {
            if (!$this->projectOwnedAttributeRepo->getByCol('project_id', $request->data['project_id']) || !$this->projectOwnedAttributeRepo->getByCol('project_attribute_id', $request->data['project_attribute_id'])) {
                if (empty($result) || $result['status'] == config('apps.general.error')) {
                    return $this->respondWithError([trans('message.NOT_COMPLETE')]);
                }
            }

            $result = $this->projectAttributeService->removeProjectAttribute($request);
        } else {
            return $this->respondWithError([trans('message.NOT_COMPLETE')]);
        }

        return response()->json($result);
    }

    /**
     * Get project attributes
     * TODO: A.E030.1
     * 2022-02-24
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectAttributesByProjectId(Request $request)
    {
        // Validation
        $validator = Validator::make(request()->all(), [
            'project_id' => 'required'
        ], [
            'project_id.required' => trans('validation.required', ['attribute' => trans('label.project_attribute.id')]),
        ]);

        if ($validator->fails()) {
            $response['status'] = config('apps.general.error');
            $response['message'] = $validator->errors()->all();
            $response['message_id'] = ['validation.required'];
            $response['error_code'] = config('apps.general.error_code');
            return response()->json($response);
        }

        // Check exist project
        $existProject = $this->projectRepo->getById($request->input('project_id'));
        if (!$existProject) {
            $response['status'] = config('apps.general.error');
            $response['message'] = [trans('validation.object_not_exist', ['object' => trans('label.project_attribute.id')])];
            $response['message_id'] = ['validation.object_not_exist'];
            $response['error_code'] = config('apps.general.error_code');
            return response()->json($response);
        }

        // Get project attributes
        $result = $this->projectAttributeService->getProjectAttributesByProjectId($request->input('project_id'));

        return response()->json($result);
    }
    public function getListProjectAttribute()
    {
        $projectAttributes = $this->projectAttributeService->getProjectAttributes();
        return response()->json($projectAttributes);
    }
}
