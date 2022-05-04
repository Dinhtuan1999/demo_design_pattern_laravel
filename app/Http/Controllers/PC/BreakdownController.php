<?php

namespace App\Http\Controllers\PC;

use App\Http\Controllers\Controller;
use App\Http\Requests\Breakdown\BreakdownProgressRequest;
use App\Http\Requests\Breakdown\BreakdownRequest;
use App\Http\Requests\Breakdown\CreateBreakdownRequest;
use App\Http\Requests\Breakdown\UpdateReporteeUserIdRequest;
use App\Http\Requests\Project\ProjectExistsRequest;
use App\Services\BreakdownService;
use App\Services\FollowupService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class BreakdownController extends Controller
{
    protected $breakdownService;
    protected $followupService;

    public function __construct(
        BreakdownService $breakdownService,
        FollowupService $followupService
    ) {
        $this->breakdownService = $breakdownService;
        $this->followupService = $followupService;
    }

    /**
     * Get breakdown
     *
     * @param ProjectExistsRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBreakdownByManager(ProjectExistsRequest $request)
    {
        $currentUser = Auth::user();
        $manager = null;

        if ($request->has('manager') && is_array($request->input('manager')) && count($request->input('manager')) > 0) {
            $manager = $request->input('manager');
        }

        $result = $this->breakdownService->getBreakdownByManager($request->input('project_id'), $manager, $currentUser->user_id);

        if (count($result['data']) == 0) {
            $result['data'] = null;
        } else {
            $result['data']= view('project.response.list_breakdown', [
                'data' => $result['data']
            ])->render();
        }


        return response()->json($result);
    }

    /**
     * Update breakdown progress
     *
     * @param BreakdownProgressRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateBreakdownProgress(BreakdownProgressRequest $request)
    {
        $currentUser = Auth::user();

        // check exists breakdown
        $record = $this->breakdownService->checkRecord($request->input('breakdown_id'));
        if ($record['status'] === config('apps.general.error')) {
            return response()->json($record);
        }

        $result = $this->breakdownService->updateBreakdownProgress(
            $request->input('progress'),
            $record['data'],
            $currentUser->user_id
        );

        return response()->json($result);
    }

    /**
     * Follow breakdown
     *
     * @param BreakdownRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function followBreakdown(BreakdownRequest $request)
    {
        $currentUser = Auth::user();

        $breakdown = $this->breakdownService->checkRecord($request->input('breakdown_id'));
        if ($breakdown['status'] != config('apps.general.success')) {
            return response()->json($breakdown);
        }

        $result = $this->followupService->addOrDeleteFollowup($breakdown['data'], $currentUser->user_id);

        return response()->json($result);
    }

    /**
     * Create Or Update Breakdown
     *
     * @param  CreateBreakdownRequest $request
     * @return JsonResponse
     */
    public function createOrUpdateBreakdown(CreateBreakdownRequest $request)
    {
        $data = [];
        $data['breakdown'] = null;

        // if update, check breakdown
        if ($request->has('breakdown_id') && !empty($request->input('breakdown_id'))) {
            $recordBreakdown = $this->breakdownService->checkRecord($request->input('breakdown_id'));
            if ($recordBreakdown['status'] !== config('apps.general.success')) {
                return response()->json($recordBreakdown);
            } else {
                $data['breakdown'] = $recordBreakdown['data'];
            }
        }

        $data['progress'] = $request->input('progress');
        $data['comment'] = $request->input('comment');
        $data['plan_date'] = $request->input('plan_date') ?? Carbon::now();
        $data['reportee_user_id'] = $request->input('reportee_user_id');

        $currentUser = Auth::user();
        $result = $this->breakdownService->createOrUpdateBreakdown(
            $request->input('task_id'),
            $request->input('work_item'),
            $data,
            $currentUser->user_id
        );

        return response()->json($result);
    }

    /**
     * Delete breakdown
     *
     * @param  BreakdownRequest $request
     * @return JsonResponse
     */
    public function deleteBreakdown(BreakdownRequest $request)
    {
        $record = $this->breakdownService->checkRecord($request->input('breakdown_id'));
        if ($record['status'] === config('apps.general.error')) {
            return response()->json($record);
        }
        $currentUser = Auth::user();
        $result = $this->breakdownService->deleteBreakdown(
            $record['data'],
            $currentUser->user_id
        );

        return response()->json($result);
    }

    public function updateReporteeUserId(UpdateReporteeUserIdRequest $request)
    {
        $currentUser = Auth::user();

        // check exists breakdown
        $listBreakdown = $request->input('list_breakdown_id');
        if (!empty($listBreakdown)) {
            foreach ($listBreakdown as $breakdown) {
                $record = $this->breakdownService->checkRecord($breakdown);
                if ($record['status'] === config('apps.general.error')) {
                    return response()->json($record);
                }
            }
        }

        $result = $this->breakdownService->updateReporteeUserId(
            $request,
            $currentUser->user_id
        );

        return response()->json($result);
    }
}
