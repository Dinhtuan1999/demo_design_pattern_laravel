<?php

namespace App\Services;

use App\Helpers\Transformer;
use App\Models\Task;
use App\Repositories\GoodRepository;
use App\Repositories\TaskRepository;
use App\Transformers\Task\TaskDetailTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\BaseService;

class GoodService extends BaseService
{
    protected $goodRepository;
    private $taskRepository;

    public function __construct(GoodRepository $goodRepository, TaskRepository $taskRepo)
    {
        $this->goodRepository = $goodRepository;
        $this->taskRepository = $taskRepo;
    }

    public function userLikeTask($userId, Request $request)
    {
        try {
            $record = $this->goodRepository->getByCols(['user_id' => $userId, 'task_id' => $request->input('task_id')]);
            if (empty($record->user_id)) {
                if (+$request->input('is_like') === config('apps.general.is_like_key.user_like')) {
                    $this->goodRepository->store([
                        'user_id' => $userId,
                        'task_id' => $request->input('task_id'),
                        'create_user_id' => $userId,
                        'update_user_id' => $userId,
                        'create_datetime' => date('Y-m-d H:i:s'),
                        'update_datetime' => date('Y-m-d H:i:s'),
                        'delete_flg' => config('apps.general.not_deleted')
                    ]);
//                    return self::sendResponse([trans('message.LIKE')]);
                }
//                else {
//                    return self::sendResponse([trans('message.SUCCESS')]);
//                }
            } else {
                if (+$request->input('is_like') === config('apps.general.is_like_key.user_unlike')) {
                    $record->update_user_id = $userId;
                    $record->delete_flg = config('apps.general.is_deleted');
                } else {
                    $record->update_user_id = $userId;
                    $record->delete_flg = config('apps.general.not_deleted');
                }
                $record->save();
            }
            $task = $this->taskRepository->getByCol('task_id', $request->input('task_id'), Task::TASK_RELATION);
            $task = $this->taskRepository->formatRecord($task);
            $subTasks = $task->sub_tasks;
            $task = $task->toArray();
            $task = $this->taskRepository->detailTask($task, $userId);
            $task['sub_tasks'] = Transformer::collection(new TaskDetailTransformer(), $subTasks)['data'];
            return $this->sendResponse([trans('message.SUCCESS')], $task);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError([ trans('message.INF_COM_0010') ], [], config('apps.general.error_code', 600));
        }
    }
}
