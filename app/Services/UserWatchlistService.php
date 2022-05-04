<?php

namespace App\Services;

use App\Models\User;
use App\Models\WatchList;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use App\Repositories\WatchListRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserWatchlistService extends BaseService
{
    public function __construct(UserRepository $userRepo, TaskRepository $taskRepo, WatchListRepository $watchRepo)
    {
        $this->watchRepo = $watchRepo;
        $this->userRepo = $userRepo;
        $this->taskRepo = $taskRepo;
    }

    /**
     * My watchlist - listUserWatch
     *
     * @param Request $request
     * @param string $userId
     * @return mixed
     */
    public function listUserWatch(Request $request, $userId)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        $result = [];
        $result['status'] = config('apps.general.error');
        $result['message'] = [];
        $result['task_name'] = '';
        $result['task_status_id'] = null;
        $data = $this->watchRepo->getByCol('user_id', $userId, [WatchList::WATCHLIST]);
        if (empty($data)) {
            $result['data'] = $data;
            return $result;
        }
        $result['status'] = config('apps.general.success');
        $data = $data->watchlist();

        // filter by task status id
        switch ($request->input('task_status_id')) {
            // when no task status id filter, set status filter default is not_complete
            case null:
                $request->merge(['task_status_id' => config('apps.task.status_key_not_complete')]);
            // no break
            case config('apps.task.status_key_not_complete'):
                $data = $data->where(function ($query) {
                    $query->orWhere('task_status_id', config('apps.task.status_key.in_progress'))
                        ->orWhere('task_status_id', config('apps.task.status_key.not_started'));
                });
                break;
            case config('apps.task.status_key_all'):
                break;
            default:
                $data = $data->where('task_status_id', $request->input('task_status_id'));
                break;
        }

        if (!empty($request->input('task_name'))) {
            $data = $data->where('task_name', 'LIKE', '%' . $request->input('task_name') . '%');
        }
        $data = $data->where('t_task.delete_flg', config('apps.general.not_deleted'));
        $data = $data->get();

        if (count($data)) {
            foreach ($data as $item) {
                $item = $this->taskRepo->formatRecord($item);
            }
            $data = $data->toArray();
            foreach ($data as $key => $item) {
                $item = $this->taskRepo->detailTask($item, $userId);
                unset($item['project'], $item['sub_tasks'], $item['check_lists'],
                    $item['check_lists_complete'], $item['user'], $item['sub_tasks_complete']
                    , $item['priority_mst'], $item['task_group'], $item['goods'], $item['watch_lists']);
                $data['data'][$key] = $item;
            }
        }

        if (!empty($request->input('task_name'))) {
            $result['task_name'] = $request->input('task_name');
        }
        if (!is_null($request->input('task_status_id'))) {
            $result['task_status_id'] = $request->input('task_status_id');
        }

        $result['data'] = $data;
        return $result;
    }

    private $userRepo;
    private $taskRepo;
}
