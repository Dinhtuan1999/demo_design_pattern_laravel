<?php

namespace App\Services;

use App\Helpers\Transformer;
use App\Models\Task;
use App\Repositories\Repository;
use App\Repositories\GoodRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use App\Repositories\WatchListRepository;
use App\Transformers\Task\TaskDetailTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\BaseService;
use Illuminate\Support\Facades\Storage;

class UserTaskService extends BaseService
{
    public function __construct(
        UserRepository $userRepo,
        TaskRepository $taskRepo,
        GoodRepository $goodRepo,
        WatchListRepository $watchListRepo
    ) {
        $this->userRepo      = $userRepo;
        $this->taskRepo      = $taskRepo;
        $this->goodRepo      = $goodRepo;
        $this->watchListRepo = $watchListRepo;
    }

    /**
     * C040 My task - get Task By User
     *
     * @param  Request $request
     * @param  string $userId
     * @return mixed
     */
    public function getTaskByUser(Request $request, $userId)
    {
        $user = $this->userRepo->getByCol('user_id', $userId);
        if (empty($user)) {
            return self::sendError([ trans('user.ERR_S.C_0001') ]);
        }
        try {
            /* Version 1 */
            /*
            $filters['t_task']         = Repository::NOT_DELETED;
            $filters['user_id']        = $userId;
            $filters['parent_task_id'] = 'NULL';
            if (!empty($request->input('task_name'))) {
                $filters['like'] = [ 'task_name' => $request->input('task_name') ];
            }
            if (!is_null($request->input('task_status_id'))) {
                $filters['task_status_id'] = [ 'task_status_id' => $request->input('task_status_id') ];
            }

            $data = $this->taskRepo->get(
                $filters,
                config('apps.general.my_task_per_page'),
                [ 'by' => 'task_name', 'type' => 'desc' ],
                Task::TASK_RELATION
            );
            */

            /* Version 2 */
            $taskQuery = $this->taskRepo->getModel()::where('t_task.delete_flg', config('apps.general.not_deleted'))
            ->select('t_task.*')
            ->join('t_project', 't_project.project_id', '=', 't_task.project_id')
            ->where('t_project.delete_flg', config('apps.general.not_deleted'))
            ->where('t_task.user_id', $userId);
            // search task name
            if (!empty($request->input('task_name'))) {
                $taskQuery = $taskQuery->where('task_name', 'LIKE', "%{$request->input('task_name')}%");
            }

            // filter by task status id
            switch ($request->input('task_status_id')) {
                // when no task status id filter, set status filter default is not_complete
                case null:
                    $request->merge(['task_status_id' => config('apps.task.status_key_not_complete')]);
                    // no break
                case config('apps.task.status_key_not_complete'):
                    $taskQuery = $taskQuery->where(function ($query) {
                        $query->orWhere('task_status_id', config('apps.task.status_key.in_progress'))
                            ->orWhere('task_status_id', config('apps.task.status_key.not_started'));
                    });
                    break;
                case config('apps.task.status_key_all'):
                    break;
                default:
                    $taskQuery = $taskQuery->where('task_status_id', $request->input('task_status_id'));
                    break;
            }

            $data = $taskQuery->orderBy('t_task.task_name', 'DESC')
                ->with(Task::TASK_RELATION)->get();
            $data = $this->taskRepo->formatAllRecord($data);
            if (count($data)) {
                $data = $data->toArray();
                foreach ($data as $key => $item) {
                    $isLike = 0;
                    if (count($item['goods']) && in_array($userId, collect($item['goods'])->pluck('user_id')->toArray(), true)) {
                        $isLike = 1;
                    }
                    $item['is_like'] = $isLike;

                    $isWatch = 0;
                    if (count($item['watch_lists']) && in_array($userId, collect($item['watch_lists'])->pluck('user_id')->toArray(), true)) {
                        $isWatch = 1;
                    }
                    $item['is_watch'] = $isWatch;
                    $item['number_like'] = count($item['goods']);

                    $userLikes = [];
                    if (count($item['goods'])) {
                        foreach ($item['goods'] as $good) {
                            $userLike = [];
                            $userLike['user_id'] = $good['user_id'];
                            $userLike['disp_name'] = !empty($good['user']['disp_name']) ? $good['user']['disp_name'] : '';
                            $userLike['icon_image_path'] = !empty($good['user']['icon_image_path']) ? Storage::url($good['user']['icon_image_path']) : '';
                            $userLikes[] = $userLike;
                        }
                    }
                    $item['user_likes'] = $userLikes;

                    unset($item['project'], $item['sub_tasks'], $item['check_lists'],
                        $item['check_lists_complete'], $item['user'], $item['sub_tasks_complete']
                        , $item['breakdowns'], $item['reminds'], $item['disclosure_range_mst']
                        , $item['priority_mst'], $item['task_group'], $item['goods'], $item['watch_lists']
                        , $item['attachment_files']);
                    $data['data'][$key] = $item;
                }
            }
            $result['status'] = config('apps.general.success');
            $result['task_name'] = '';
            $result['task_status_id'] = null;
            if (!empty($request->input('task_name'))) {
                $result['task_name'] = $request->input('task_name');
            }
            if (!is_null($request->input('task_status_id'))) {
                $result['task_status_id'] = $request->input('task_status_id');
            }

            $result['data']   = $data;
            return $result;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            return self::sendError([ trans('message.ERR_EXCEPTION') ]);
        }
    }

    public function addTaskToWatchList($task, $userId)
    {
        try {
            $watchList = $this->watchListRepo->getByCols([
                'task_id' => $task->task_id,
                'user_id' => $userId,
            ]);
            if ($watchList) {
                $watchList->delete_flg = config('apps.general.not_deleted');
                $watchList->update_user_id = $userId;
            } else {
                $watchList                 = $this->watchListRepo->getInstance();
                $watchList->user_id        = $userId;
                $watchList->task_id        = $task->task_id;
                $watchList->create_user_id = $userId;
                $watchList->update_user_id = $userId;
            }
            $watchList->save();
            $task = $this->taskRepo->getByCol('task_id', $task->task_id, Task::TASK_RELATION);
            $task = $this->taskRepo->formatRecord($task);
            $subTasks = $task->sub_tasks;
            $task = $task->toArray();
            $task = $this->taskRepo->detailTask($task, $userId);
            $task['sub_tasks'] = Transformer::collection(new TaskDetailTransformer(), $subTasks)['data'];
            return self::sendResponse([ trans('message.SUCCESS') ], $task);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return self::sendError([ trans('message.INF_COM_0010') ], [], config('apps.general.error_code', 600));
        }
    }

    private $userRepo;
    private $taskRepo;
    private $goodRepo;
    private $watchListRepo;
}
