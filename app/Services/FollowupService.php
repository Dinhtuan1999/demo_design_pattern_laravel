<?php

namespace App\Services;

use App\Repositories\BreakdownRepository;
use App\Repositories\TaskRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Log;
use App\Repositories\FollowupRepository;

class FollowupService extends BaseService
{
    protected $followupRepo;
    protected $breakdownRepository;

    public function __construct(
        FollowupRepository $followupRepo,
        BreakdownRepository $breakdownRepository
    ) {
        $this->followupRepo = $followupRepo;
        $this->breakdownRepository = $breakdownRepository;
    }

    public function addOrDeleteFollowup($breakdown, $userId)
    {
        $response = $this->initResponse();
        try {
            $followup = $this->followupRepo->getByCols([
                'breakdown_id'  => $breakdown->breakdown_id,
                'task_id'  => $breakdown->task_id,
                'followup_user_id'  => $userId
            ]);

            if ($followup) {
                if ($followup->delete_flg == config('apps.general.is_deleted')) {
                    $followup->delete_flg      = config('apps.general.not_deleted');
                    $followup->update_datetime = date('Y-m-d H:i:s');
                    $followup->update_user_id  = $userId;
                } else {
                    $followup->delete_flg      = config('apps.general.is_deleted');
                    $followup->update_datetime = date('Y-m-d H:i:s');
                    $followup->update_user_id  = $userId;
                }
                $followup->save();
            } else {
                $record = $this->followupRepo->getInstance();
                $record->breakdown_id       = $breakdown->breakdown_id;
                $record->task_id            = $breakdown->task_id;
                $record->followup_user_id   = $userId;
                $record->create_datetime    = date('Y-m-d H:i:s');
                $record->update_datetime    = date('Y-m-d H:i:s');
                $record->create_user_id     = $userId;
                $record->update_user_id     = $userId;
                $record->delete_flg         = config('apps.general.not_deleted');
                $record->save();
            }
            $response['data'] = $this->breakdownRepository->getFollowBreakdown($breakdown->breakdown_id, $userId);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $response = $this->exceptionError();
        }
        return $response;
    }
}
