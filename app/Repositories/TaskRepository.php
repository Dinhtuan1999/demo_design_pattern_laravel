<?php

namespace App\Repositories;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\Trash;
use Carbon\Carbon;
use Facade\Ignition\Tabs\Tab;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TaskRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(Task::class);
    }

    public function formatAllRecord($records)
    {
        if (!empty($records)) {
            foreach ($records as &$record) {
                $record = $this->formatRecord($record);
            }
        }
        return $records;
    }

    public function formatRecord($record)
    {
        $record->project_name = !empty($record->project) ? $record->project->project_name : '';
        $record->task_group_name = !empty($record->task_group) ? $record->task_group->group_name : '';
        $record->user_name = !empty($record->user) ? $record->user->disp_name : '';
        $record->start_date = !empty($record->start_date) ? $record->start_date : '';
        $record->end_date = !empty($record->end_date) ? $record->end_date : '';
        $record->piority_name = !empty($record->priority_mst) ? $record->priority_mst->priority_name : '';
        $record->number_subtask = count($record->sub_tasks);
        $record->number_subtask_completed = count($record->sub_tasks_complete);
        $record->number_checklist = count($record->check_lists);
        $record->number_checklist_completed = count($record->check_lists_complete);

        $record->start_plan_date = !empty($record->start_plan_date) ? $record->start_plan_date : '';
        $record->end_plan_date = !empty($record->end_plan_date) ? $record->end_plan_date : '';
        $record->icon_image_path = !empty($record->user) ? $record->user->getIconImageUrlAttribute() : null;
        return $record;
    }


    public function getManagers($projectId)
    {
        $model = $this->getModel();
        $model = $model::where('project_id', $projectId)
            ->where('delete_flg', config('apps.general.not_deleted'))
            ->with('manager')->select('user_id')
            ->groupBy('user_id')
            ->get();
        $managers = $this->formatUserFilter($model, 'manager');
        foreach ($managers as $manager) {
            $manager->icon_image_path = getFullPathFile($manager->icon_image_path);
        }
        return $managers;
    }

    public function getAuthors($projectId)
    {
        $model = $this->getModel();
        $model = $model::where('project_id', $projectId)
            ->where('delete_flg', config('apps.general.not_deleted'))
            ->with('create_user')->select('create_user_id')
            ->groupBy('create_user_id')
            ->get();
        $authors = $this->formatUserFilter($model, 'create_user');
        foreach ($authors as $author) {
            $author->icon_image_path = getFullPathFile($author->icon_image_path);
        }
        return $authors;
    }

    public function formatUserFilter($records, $keyObject)
    {
        $result = [];
        if (count($records) == 0) {
            return $result;
        }
        foreach ($records as $item) {
            if ($item[$keyObject]) {
                $result[] = $item[$keyObject];
            }
        }
        return $result;
    }

    public function getBreakdownByManager($projectId, $manager, $userId, $paginate = true)
    {
        $model = $this->getModel();

        // Filter
        $model = $model::where('project_id', $projectId);
        if ($manager != null && count($manager) > 0) {
            $model = $model->whereIn('user_id', $manager);
        }
        $model = $model->where(function ($query) {
            $query->where('delete_flg', config('apps.general.not_deleted'));
        });

        // Relationship
        $model = $model->with('user', 'project', 'task_group', 'breakdowns', 'breakdowns.followups');

        $model = $model->with('breakdowns', function ($query2) use ($userId) {
            $query2->withCount('followups');

            $query2->withCount(['followups as follow' => function ($query3) use ($userId) {
                $query3->where('followup_user_id', $userId);
            }]);
        });

        $model = $model->has('breakdowns');
        $model = $model->orderBy('create_datetime', 'DESC');
        if ($paginate) {
            $model = $model->paginate(config('apps.breakdown.record_per_page'));
        } else {
            $model = $model->get();
        }


        // prepare data
//        $model->getCollection()->transform(function ($item) {
//            if ($item->breakdowns->count() > 0) {
//                foreach ($item->breakdowns as &$breakdown) {
//                    $breakdown->plan_date = !empty($breakdown->plan_date) ?
//                        Carbon::parse($breakdown->plan_date)->format('Y/m/d') : $breakdown->plan_date;
//                }
//            }
//            return $item;
//        });

        // response
        $model->transform(function ($item) {
            if (!empty($item->user)) {
                if (!str_contains($item->user->icon_image_path, Storage::url(''))) {
                    $item->user->icon_image_path = $item->user->getIconImageAttribute();
                }
            }

            if (count($item->breakdowns) > 0) {
                foreach ($item->breakdowns as $breakdown) {
                    if (count($breakdown->followups) > 0) {
                        foreach ($breakdown->followups as $followup) {
                            if (!is_null($followup->user)) {
                                if (!str_contains($followup->user->icon_image_path, Storage::url(''))) {
                                    $followup->user->icon_image_path = $followup->user->getIconImageAttribute();
                                }
                            }
                        }
                    }
                }
            }

            return $item;
        });
        return $model;
    }

    public function getTaskByProject($projectId, $filter)
    {
        $model = $this->getModel();

        $model = $model::where('project_id', $projectId);
        $model = $model->where('delete_flg', config('apps.general.not_deleted'));

        $model = $model->where(function ($query) {
            $query->orWhere('parent_task_id', null)
                ->orWhere('parent_task_id', '');
        });

        if ($filter['watch_list'] == true) {
            $model = $model->with('watch_lists');
            $model = $model->has('watch_lists');
        }

        if (is_countable($filter['status']) && count($filter['status']) > 0) {
            $model = $model->whereIn('task_status_id', $filter['status']);
        }
        if (is_countable($filter['priority']) && count($filter['priority']) > 0) {
            $model = $model->whereIn('priority_id', $filter['priority']);
        }
        if (is_countable($filter['manager']) && count($filter['manager']) > 0) {
            $model = $model->whereIn('user_id', $filter['manager']);
        }
        if (is_countable($filter['author']) && count($filter['author']) > 0) {
            $model = $model->whereIn('create_user_id', $filter['author']);
        }

        $model = $model->withCount('sub_tasks');
        $model = $model->withCount('sub_tasks_complete');
        $model = $model->withCount('check_lists');
        $model = $model->withCount('check_lists_complete');

        $model = $model->with('sub_tasks', function ($query2) use ($filter) {
            if ($filter['watch_list'] == true) {
                $query2->has('watch_lists');
            }

            if (is_countable($filter['status']) && count($filter['status']) > 0) {
                $query2->whereIn('task_status_id', $filter['status']);
            }
            if (is_countable($filter['priority']) && count($filter['priority']) > 0) {
                $query2->whereIn('priority_id', $filter['priority']);
            }
            if (is_countable($filter['manager']) && count($filter['manager']) > 0) {
                $query2->whereIn('user_id', $filter['manager']);
            }
            if (is_countable($filter['author']) && count($filter['author']) > 0) {
                $query2->whereIn('create_user_id', $filter['author']);
            }

            $query2->withCount('check_lists');
            $query2->withCount('check_lists_complete');
            $query2->with('user');
            $query2->with('task_status');
            $query2->with('priority_mst');
            $query2->with('task_group');
            $query2->with('task_parent');

            $query2->orderBy('sub_task_display_order', 'DESC');
        });

        $model = $model->with('user');
        $model = $model->with('task_status');
        $model = $model->with('priority_mst');
        $model = $model->with('task_group');
        $model = $model->with('task_parent');


        $model = $model->orderBy('parent_task_display_order', 'DESC');
        $model = $model->get();

        // response
        return $model;
    }

    public function getTaskByProjectV4($projectId, $filter, $flagFilter)
    {
        DB::statement("SET sql_mode = false");

        $sqlParentTask = '(
            SELECT
                t_task.project_id,
                t_task.task_id,
                row_number() over(order by $order_by ) as parent_rank
            FROM t_task
            WHERE
                t_task.project_id = "$project_id"
                AND t_task.parent_task_id IS NULL
                AND t_task.delete_flg = $delete_flg
        )
        as parent_task';

        $values = [
            '$project_id' => $projectId,
            '$delete_flg' => config('apps.general.not_deleted'),
            '$order_by' => 't_task.create_datetime DESC',
        ];

        $parentTask = strtr($sqlParentTask, $values);

        $model = DB::table(DB::raw($parentTask));

        $model = $model->leftJoin('t_task', function ($join) {
            $join->on('t_task.parent_task_id', '=', 'parent_task.task_id');
            $join->orOn('t_task.task_id', '=', 'parent_task.task_id');
            $join->where('t_task.delete_flg', config('apps.general.not_deleted'));
        });

        $model = $model->select(
            't_task.project_id',
            't_task.task_group_id',
            'parent_task.parent_rank',
            't_task.task_id',
            't_task.parent_task_id',
            't_task.task_status_id',
            't_task.priority_id',
            't_task.user_id',
            't_task.create_user_id'
        );

        if ($filter['watch_list'] === true) {
            $model = $model->join('t_watch_list', function ($join) {
                $join->on('t_watch_list.task_id', '=', 't_task.task_id')
                    ->where('t_watch_list.delete_flg', config('apps.general.not_deleted'));
            });
        }

        if (is_countable($filter['status']) && count($filter['status']) > 0) {
            $model = $model->whereIn('t_task.task_status_id', $filter['status']);
        }

        if (is_countable($filter['priority']) && count($filter['priority']) > 0) {
            $model = $model->whereIn('t_task.priority_id', $filter['priority']);
        }

        if (is_countable($filter['manager']) && count($filter['manager']) > 0) {
            $model = $model->whereIn('t_task.user_id', $filter['manager']);
        }

        if (is_countable($filter['author']) && count($filter['author']) > 0) {
            $model = $model->whereIn('t_task.create_user_id', $filter['author']);
        }

        if ($filter['watch_list'] === true) {
            $model = $model->groupBy('t_task.task_id');
        }

        $model = $model->orderBy('parent_task.parent_rank', 'ASC')
            ->orderBy('t_task.parent_task_id', 'ASC')
            ->orderBy('t_task.create_datetime', 'DESC');

        Log::info($model->toSql());

        $model = $model->paginate(config('apps.general.paginate_default'));

        return $model;
    }

    public function isExists($task_id)
    {
        return $this->getInstance()::where('task_id', $task_id)->exists();
    }

    public function getListTaskByGroup($userId, $taskGroupId, $perPage)
    {
        $model = $this->getModel();
        $model = $model::where('task_group_id', $taskGroupId);
        $model = $model->where('delete_flg', config('apps.general.not_deleted'));
        $model = $model->withCount(
            [
                Task::SUB_TASKS . ' AS number_subtask',
                Task::SUB_TASKS_COMPLETE . ' AS number_subtask_completed',
                Task::CHECK_LISTS . ' AS number_checklist',
                Task::CHECK_LISTS_COMPLETE . ' AS number_checklist_completed',
                Task::GOODS . ' AS is_like' => function ($query) use ($userId) {
                    $query->select(DB::raw('IF(count(*) > 0, 1, 0)'));
                    $query->where('delete_flg', config('apps.general.not_deleted'));
                    $query->where('user_id', $userId);
                },
                Task::WATCH_LISTS . ' AS is_watch_list' => function ($query) use ($userId) {
                    $query->select(DB::raw('IF(count(*) > 0, 1, 0)'));
                    $query->where('user_id', $userId);
                }
            ]
        );
        $model = $model->with(
            Task::TASK_GROUP,
            Task::CHECK_LISTS,
            Task::PRIORITY_MST,
            Task::TASK_STATUS,
            Task::USER,
            Task::BREAKDOWNS,
            Task::REMINDS,
            Task::ATTACHMENT_FILES
        );
        $model = $model->orderBy('create_datetime', 'DESC');
        $model = $model->paginate($perPage);
        return $model;
    }

    public function detailTask($item, $userId, $unsetRelation = true)
    {
        $isLike = 0;
        if (!empty($item['goods']) && count($item['goods']) && in_array($userId, collect($item['goods'])->pluck('user_id')->toArray(), true)) {
            $isLike = 1;
        }
        $item['is_like'] = $isLike;

        $isWatch = 0;
        if (!empty($item['watch_lists']) && count($item['watch_lists']) && in_array($userId, collect($item['watch_lists'])->pluck('user_id')->toArray(), true)) {
            $isWatch = 1;
        }
        $item['is_watch'] = $isWatch;

        $item['number_like'] = is_countable($item['goods']) && count($item['goods']) ? count($item['goods']) : 0;

        $userLikes = [];
        if (!empty($item['goods']) && count($item['goods'])) {
            foreach ($item['goods'] as $good) {
                $userLike = [];
                $userLike['user_id'] = $good['user_id'];
                $userLike['disp_name'] = !empty($good['user']['disp_name']) ? $good['user']['disp_name'] : '';
                $userLike['icon_image_path'] = !empty($good['user']['icon_image_path']) ? Storage::url($good['user']['icon_image_path']) : '';
                $userLikes[] = $userLike;
            }
        }
        $item['user_likes'] = $userLikes;

        if ($unsetRelation) {
            unset($item['project'], $item['sub_tasks'], $item['check_lists'],
                $item['check_lists_complete'], $item['user'], $item['sub_tasks_complete']
                , $item['breakdowns'], $item['reminds'], $item['disclosure_range_mst']
                , $item['priority_mst'], $item['task_group'], $item['goods'], $item['watch_lists']
                , $item['attachment_files']);
        }

        return $item;
    }

    public function getSuggestManager($projectId, $key_word)
    {
        $model = $this->getModel();
        $model = $model::where('project_id', $projectId)
            ->select('user_id')
            ->with(
                [
                    Task::USER => function ($query) use ($key_word) {
                        if ($key_word) {
                            $query->where(function ($q) use ($key_word) {
                                $q->where('t_user.disp_name', "like", "%$key_word%")
                                    ->orWhere("t_user.mail_address", "like", "%$key_word%");
                            });
                        }
                    }
                ]
            )
            ->groupBy('user_id')
            ->get();

        return $this->formatUserFilter($model, Task::USER);
    }

    public function getGraphDetail($projectId, $taskStatusId, $managerId = null, $taskGroupId = null)
    {
        $query = $this->getInstance()->query()->with([
            'task_group' => function ($q) {
                $q->select('task_group_id', 'group_name');
            }
        ])
            ->join('t_project', 't_task.project_id', '=', 't_project.project_id')
            ->join('m_task_status', 'm_task_status.task_status_id', '=', 't_task.task_status_id')
            ->select(
                't_task.task_id',
                't_task.task_name',
                't_task.task_group_id',
                't_task.start_date',
                't_task.end_date',
                't_task.start_plan_date',
                't_task.end_plan_date',
                't_project.project_id',
                't_task.user_id',
            )
            ->where('t_project.project_id', $projectId)
            ->avaiable();
        if (!is_null($taskStatusId)) {
            $query = $query->where('t_task.task_status_id', $taskStatusId);
        }
        if (!is_null($managerId)) {
            if ($managerId != config('apps.task.task_detail.others')) {
                $query = $query->where('t_task.user_id', $managerId);
            } else {
                $query = $query->whereNull('t_task.user_id');
            }
        }
        if (!is_null($taskGroupId)) {
            if ($taskGroupId != config('apps.task.task_detail.others')) {
                $query = $query->where('t_task.task_group_id', $taskGroupId);
            } else {
                $query = $query->whereNull('t_task.task_group_id');
            }
        }

        return $query->orderBy('t_task.create_datetime', 'DESC')->paginate(config('apps.task.task_detail.per_page'));
    }

    public function getSimpleTaskById($id)
    {
        $model = $this->getModel();

        $model = $model::where('task_id', $id);
        $model = $model->where('delete_flg', config('apps.general.not_deleted'));

        // Relationship
        $model = $model->withCount('sub_tasks');
        $model = $model->withCount('sub_tasks_complete');

        $model = $model->first();

        return $model;
    }

    /**
     * @param $taskId
     * @return mixed
     */
    public function getTaskInfo($taskId)
    {
        $model = $this->getModel();
        $model = $model::where('task_id', $taskId);
        $model = $model->where('delete_flg', config('apps.general.not_deleted'));

        $model = $model->withCount('sub_tasks');
        $model = $model->withCount('sub_tasks_complete');
        $model = $model->withCount('check_lists');
        $model = $model->withCount('check_lists_complete');

        $model = $model->with('user');
        $model = $model->with('task_status');
        $model = $model->with('priority_mst');
        $model = $model->with('task_group');
        $model = $model->with('project');
        $model = $model->with('task_parent');

        $model = $model->first();

        return $model;
    }

    /**
     * Query list task of project by group
     * @param $projectId
     * @param $groupTaskId
     * @return mixed
     */
    public function getTaskByProjectTaskGroups($projectId, $groupTaskId)
    {
        $query = $this->getInstance()->query()->with([
            'task_group' => function ($q) {
                $q->select('task_group_id', 'group_name');
            }
        ])
            ->join('t_project', 't_task.project_id', '=', 't_project.project_id')
            ->join('t_user', 't_task.user_id', '=', 't_user.user_id')
            ->select(
                't_task.task_id',
                't_task.task_name',
                't_task.task_group_id',
                't_task.start_date',
                't_task.end_date',
                't_task.start_plan_date',
                't_task.end_plan_date',
                't_project.project_id',
                't_user.disp_name',
                't_user.icon_image_path'
            )
            ->where('t_project.project_id', $projectId)
            ->avaiable()
            ->whereHas('task_group', function ($qg) use ($groupTaskId) {
                $qg->where('task_group_id', $groupTaskId);
            })
            ->orderBy('t_task.create_datetime', 'DESC')
            ->paginate(config('apps.task.task_detail.per_page'));

        $query->getCollection()->transform(function ($item) {
            $item->icon_image_path = Storage::url($item->icon_image_path);

            return $item;
        });

        return $query;
    }
}
