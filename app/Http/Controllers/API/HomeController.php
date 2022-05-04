<?php

namespace App\Http\Controllers\API;

use App\Repositories\DispColorRepository;
use App\Repositories\TaskGroupDispColorRepository;
use App\Repositories\UserDispColorRepository;
use App\Services\AppService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct(AppService $appService, UserDispColorRepository $userDispColorRepo, TaskGroupDispColorRepository $taskGroupDispColorRepo)
    {
        $this->appService = $appService;
        $this->userDispColorRepo = $userDispColorRepo;
        $this->taskGroupDispColorRepo = $taskGroupDispColorRepo;
    }

    public function changeLanguage(Request $request)
    {
        $this->appService->changeLanguage($request);
        return response()->json([
            'message' => 'success',
            'status' => config('apps.general.success'),
            'data' => []
        ]);
    }

    public function listDispColorUser()
    {
        $result = [];
        try {
            $result['status'] = config('apps.general.success');
            $result['message'] = [trans('message.SUCCESS')];
            $result['data'] = $this->userDispColorRepo->all();
        } catch (\Exception $exception) {
            $result['status'] = config('apps.general.error');
            $result['error_code'] = config('apps.general.error_code');
            $result['message'] = [trans('message.FAIL')];
        }
        return $result;
    }

    public function listDispColorTaskGroup()
    {
        $result = [];
        try {
            $result['status'] = config('apps.general.success');
            $result['message'] = [trans('message.SUCCESS')];
            $result['data'] = $this->taskGroupDispColorRepo->all();
        } catch (\Exception $exception) {
            $result['status'] = config('apps.general.error');
            $result['error_code'] = config('apps.general.error_code');
            $result['message'] = [trans('message.FAIL')];
        }
        return $result;
    }

    /**
     * get list tooltip question mark
     * @return array
     */
    public function listTooltipQuestionMark(): array
    {
        $result = [];
        try {
            $result['status'] = config('apps.general.success');
            $result['message'] = [trans('message.SUCCESS')];
            $result['data'] = trans('general.tooltip.questions');
        } catch (\Exception $exception) {
            $result['status'] = config('apps.general.error');
            $result['error_code'] = config('apps.general.error_code');
            $result['message'] = [trans('message.FAIL')];
        }
        return $result;
    }

    private $appService;
    private $userDispColorRepo;
    private $taskGroupDispColorRepo;
}
