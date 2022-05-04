<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Breakdown\BreakdownProgressRequest;
use App\Http\Requests\Breakdown\BreakdownRequest;
use App\Http\Requests\Breakdown\CreateBreakdownRequest;
use App\Http\Requests\Project\ProjectExistsRequest;
use App\Services\BreakdownService;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\BaseService;
use App\Repositories\BreakdownRepository;
use Illuminate\Validation\Rule;

class BreakdownController extends Controller
{
    protected $breakdownService;
    protected $baseService;
    protected $breakdownRepo;
    protected $taskService;

    public function __construct(
        BreakdownService    $breakdownService,
        BaseService         $baseService,
        BreakdownRepository $breakdownRepo,
        TaskService $taskService
    ) {
        $this->breakdownService = $breakdownService;
        $this->baseService = $baseService;
        $this->breakdownRepo = $breakdownRepo;
        $this->taskService = $taskService;
    }

    public function getBreakdownByManager(ProjectExistsRequest $request)
    {
        $currentUser = auth('api')->user();
        $manager = null;

        if ($request->has('manager') && is_array($request->input('manager')) && count($request->input('manager')) > 0) {
            $manager = $request->input('manager');
        }

        $result = $this->breakdownService->getBreakdownByManager($request->input('project_id'), $manager, $currentUser->user_id, false);

        return response()->json($result);
    }

    public function updateBreakdownProgress(BreakdownProgressRequest $request)
    {
        $currentUser = auth('api')->user();
        if (!$currentUser) {
            return $this->baseService->sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
        }

        // check exists breakdown
        $breakdown = $this->breakdownRepo->getByCol(
            'breakdown_id',
            $request->input('breakdown_id')
        );
        if (!$breakdown) {
            return $this->baseService->sendError(
                [trans('message.ERR_COM_0011', ['attribute' => trans('validation_attribute.t_breakdown')])],
                [],
                config('apps.general.error_code', 600)
            );
        }

        $result = $this->breakdownService->updateBreakdownProgress(
            $request->input('progress'),
            $breakdown,
            $currentUser->user_id
        );

        return response()->json($result);
    }

    public function createOrUpdateBreakdown(CreateBreakdownRequest $request)
    {
        $currentUser = auth('api')->user();
        $data = [
            'breakdown' => null,
            'progress' => null,
            'comment' => null,
            'plan_date' => null,
            'reportee_user_id' => null
        ];

        // check task
        $record = $this->taskService->detail($request->input('task_id'));
        if ($record['status'] === config('apps.general.error')) {
            $record['message_id'] = ['ERR_COM_0011'];
            return response()->json($record);
        }

        // if update, check breakdown
        if ($request->has('breakdown_id') && !empty($request->input('breakdown_id'))) {
            $recordBreakdown = $this->breakdownService->checkRecord($request->input('breakdown_id'));
            if ($recordBreakdown['status'] !== config('apps.general.success')) {
                return response()->json($recordBreakdown);
            } else {
                $data['breakdown'] = $recordBreakdown['data'];
            }
        }

        if ($request->has('progress')) {
            $data['progress'] = $request->input('progress');
        }

        if ($request->has('comment')) {
            $data['comment'] = $request->input('comment');
        }

        if ($request->has('plan_date')) {
            $data['plan_date'] = $request->input('plan_date');
        }

        if ($request->has('reportee_user_id')) {
            $data['reportee_user_id'] = $request->input('reportee_user_id');
        }

        $result = $this->breakdownService->createOrUpdateBreakdown(
            $request->input('task_id'),
            $request->input('work_item'),
            $data,
            $currentUser->user_id
        );

        return response()->json($result);
    }

    public function deleteBreakdown(BreakdownRequest $request)
    {
        $currentUser = auth('api')->user();

        $record = $this->breakdownService->checkRecord($request->input('breakdown_id'));
        if ($record['status'] === config('apps.general.error')) {
            return response()->json($record);
        }

        $result = $this->breakdownService->deleteBreakdown(
            $record['data'],
            $currentUser->user_id
        );

        return response()->json($result);
    }
}
