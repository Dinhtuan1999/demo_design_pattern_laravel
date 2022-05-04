<?php

namespace App\Services;

use App\Models\ProjectAttribute;
use App\Repositories\ProjectRepository;
use App\Repositories\ProjectAttributeRepository;
use App\Repositories\ProjectOwnedAttributeRepository;
use App\Repositories\KindRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProjectAttributeService extends BaseService
{
    public function __construct(
        ProjectOwnedAttributeRepository $projectOwnedAttributeRepo,
        KindRepository $kindRepo
    ) {
        $this->projectOwnedAttributeRepo = $projectOwnedAttributeRepo;
        $this->kindRepo = $kindRepo;
    }

    public function updateProjectAttribute(Request $request, $currentUserId="")
    {
        try {
            $data['project_id']           = $request->data['project_id'];
            $data['project_attribute_id'] = $request->data['project_attribute_id'];
            $data['others_message']       = $request->others_message;
            $data['create_datetime']      = date('Y-m-d');
            $data['update_datetime']      = date('Y-m-d');
            $data['create_user_id']       = $currentUserId;
            $data['update_user_id']       = $currentUserId;

            $projectOwnedAttribute = $this->projectOwnedAttributeRepo->store($data);
            if (!$projectOwnedAttribute) {
                $result['status']  = -1;
                $result['message'] = [trans('message.FAIL')];
                return $result;
            }

            $result['status']  = config('apps.general.success');
            $result['message'] = [trans('message.SUCCESS')];
            return $result;
        } catch (\Throwable $th) {
            $result['status'] = config('apps.general.error');
            $result['message'] = [trans('message.FAIL')];
            return $result;
        }
    }

    public function removeProjectAttribute(Request $request)
    {
        try {
            $projectOwnedAttribute = $this->projectOwnedAttributeRepo->updateByMultipleField($request->data, ['delete_flg' => config('apps.general.is_deleted')]);
            if (!$projectOwnedAttribute) {
                $result['status']  = -1;
                $result['message'] = [trans('message.FAIL')];
                return $result;
            }

            $result['status']  = config('apps.general.success');
            $result['message'] = [trans('message.SUCCESS')];
            return $result;
        } catch (\Throwable $th) {
            $result['status'] = config('apps.general.error');
            $result['message'] = [trans('message.FAIL')];
            return $result;
        }
    }

    /**
     * Get project attributes
     * TODO: S.E030.1
     * 2022-02-24
     *
     * @param $projectId
     * @return array
     */
    public function getProjectAttributesByProjectId($projectId)
    {
        $response = [
            'status' => config('apps.general.success'),
            'data' => null,
            'message' => [trans('message.SUCCESS')],
            'message_id' => ['SUCCESS'],
            'error_code' => null,
        ];

        try {
            $response['data'] = $this->projectOwnedAttributeRepo->getProjectAttributes($projectId);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response = $this->exceptionError();
        }

        return $response;
    }
    public function getProjectAttributes()
    {
        try {
            $kinds = $this->kindRepo->getModel()::with("project_attributes")->get();
            return $this->sendResponse([
                [trans('message.SUCCESS')]
            ], $kinds);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError([
                [trans('message.ERR_EXCEPTION')]
            ]);
        }
    }
    private $projectOwnedAttributeRepo;
    private $kindRepo;
}
