<?php

namespace App\Services;

use App\Helpers\Transformer;
use App\Models\Task;
use App\Repositories\TaskRepository;
use App\Repositories\WatchListRepository;
use App\Transformers\Task\TaskDetailTransformer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WatchListService
{
    protected $watchListRepository;
    private $taskRepo;
    public function __construct(WatchListRepository $watchListRepository, TaskRepository $taskRepo)
    {
        $this->watchListRepository = $watchListRepository;
        $this->taskRepo = $taskRepo;
    }

    public function validateDeleteWatchList(Request $request)
    {
        return Validator::make(request()->all(), [
            'task_id' => 'required',
        ], [
            'task_id.required' => trans('validation.required', ['attribute' => trans('label.general.task_id')]),
        ]);
    }

    public function deleteWatchList($record, $userId)
    {
        $response = [
            'status'        => config('apps.general.success'),
            'data'          => null,
            'message'       => [trans('message.SUCCESS')],
            'message_id'    => ['SUCCESS']
        ];

        try {
            $record->delete_flg = config('apps.general.is_deleted');
            $record->update_user_id = $userId;
            $record->update_datetime = Carbon::now();

            $record->save();

            $task = $this->taskRepo->getByCol('task_id', $record->task_id, Task::TASK_RELATION);
            $task = $this->taskRepo->formatRecord($task);
            $subTasks = $task->sub_tasks;
            $task = $task->toArray();
            $task = $this->taskRepo->detailTask($task, $userId);
            $response['data'] = $task;
            $response['data']['sub_tasks'] = Transformer::collection(new TaskDetailTransformer(), $subTasks)['data'];
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response['status']     = config('apps.general.error');
            $response['message'] = [trans('message.ERR_EXCEPTION') ];
            $response['message_id'] = ['ERR_EXCEPTION'];
            $response['error_code'] = config('apps.general.error_code');
        }

        return $response;
    }

    public function checkRecord($id)
    {
        $response = [
            'status'        => config('apps.general.success'),
            'data'          => null,
            'message'       => [trans('message.SUCCESS')],
            'message_id'    => ['SUCCESS']
        ];

        try {
            $record   = $this->watchListRepository->getByCols([
                'task_id'       => $id,
                'delete_flg'    => config('apps.general.not_deleted')
            ]);

            if (!$record) {
                $response['status'] = config('apps.general.error');
                $response['message'] = [trans(
                    'message.ERR_COM_0011',
                    ['attribute' => trans('validation_attribute.t_watch_list')]
                )];
                $response['message_id'] = ['ERR_COM_0011'];
            } else {
                $response['data'] = $record;
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response['status']     = config('apps.general.error');
            $response['message'] = [trans('message.ERR_EXCEPTION') ];
            $response['message_id'] = ['ERR_EXCEPTION'];
            $response['error_code'] = config('apps.general.error_code');
        }

        return $response;
    }
}
