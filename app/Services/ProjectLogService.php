<?php

namespace App\Services;

use App\Models\ProjectLog;
use App\Repositories\ProjectLogRepository;
use App\Repositories\ProjectRepository;
use Illuminate\Support\Facades\Log;

class ProjectLogService extends BaseService
{
    public function __construct(
        ProjectLogRepository $projectLogRepo,
        ProjectRepository    $projectRepo
    ) {
        $this->projectLogRepo = $projectLogRepo;
        $this->projectRepo = $projectRepo;
    }

    public function getLog($projectId, $identifyCode, $taskId = null)
    {
        try {
            // Check empty projectId is exists
            if (empty($projectId) && !$this->projectRepo->isExists($projectId)) {
                return $this->sendError(trans('message.NOT_COMPLETE'));
            }
            //  Call getListLog function in Project Repository to get Get List Log
            $data = $this->projectLogRepo->getInstance()->query();
            if (is_countable($identifyCode) && count($identifyCode)) {
                $data = $data->whereIn('t_project_log.identifying_code', $identifyCode);
            }
            $data = $data->select(['t_project_log.*'])
                ->where('t_project_log.project_id', $projectId);
            if (!is_null($taskId)) {
                $data = $data->where('t_project_log.task_id', $taskId);
            }
            $data = $data->orderBy('t_project_log.regist_datetime', 'desc')
                ->with(ProjectLog::USER)
                ->paginate(config('apps.general.notices.per_page'));

            return $this->sendResponse(trans('message.COMPLETE'), $data);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.NOT_COMPLETE')
            );
        }
    }

    private $projectLogRepo;
    private $projectRepo;
}
