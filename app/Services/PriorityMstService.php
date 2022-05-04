<?php

namespace App\Services;

use App\Repositories\PriorityMstRepository;
use Illuminate\Support\Facades\Log;

class PriorityMstService
{
    protected $priorityMstRepository;
    public function __construct(PriorityMstRepository $priorityMstRepository)
    {
        $this->priorityMstRepository = $priorityMstRepository;
    }

    public function getListPriority()
    {
        $response = [
            'status'        => config('apps.general.success'),
            'data'          => null,
            'message'       => [],
            'message_id'    => []
        ];

        try {
            $response['data'] = $this->priorityMstRepository->all(
                [],
                ['by' => 'display_order', 'type' => 'DESC'],
                [],
                ['priority_id','priority_name','display_order']
            );
            $response['message']    = [trans('message.SUCCESS')];
            $response['message_id'] = ['SUCCESS'];
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response['status']     = config('apps.general.error');
            $response['message'] = [trans('message.ERR_EXCEPTION') ];
            $response['message_id'] = ['ERR_EXCEPTION'];
        }

        return $response;
    }

    /**
     * get all priorrity
     *
     * @param array $col
     * @return void
     */
    public function getAllPriority($col = ['*'], $oderBy = ['display_order', 'asc'])
    {
        try {
            $model = $this->priorityMstRepository->getInstance();
            return $model->select($col)->orderBy($oderBy[0], $oderBy[1])->get();
        } catch (\Throwable $th) {
            set_log_error('getAllPriority', $th->getMessage());
        }

        return  collect();
    }
}
