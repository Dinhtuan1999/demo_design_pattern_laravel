<?php

namespace App\Repositories;

use App\Models\Task;
use App\Models\TaskGroup;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskGroupRepository extends Repository
{
    private $taskRepo;

    public function __construct(TaskRepository $taskRepo)
    {
        parent::__construct(TaskGroup::class);
        $this->fields = TaskGroup::FIELDS;
        $this->taskRepo = $taskRepo;
    }

    public function restoreTaskGroupFromTrash($task_group_id)
    {
        $table = 't_task_group';
        try {
            if ($task_group_id) {
                // check task_group is deleted
                $task_group = $this->getById($task_group_id);
                if ($task_group && $task_group->delete_flg != config('apps.general.is_deleted')) {
                    return [
                        'status' => false,
                        'error' => ['table_not_found' => $table]
                    ];
                }

                // check project parent has deleted
                $project = $task_group->{TaskGroup::PROJECT}()->first();
                if ($project && $project->delete_flg == config('apps.general.is_deleted')) {
                    return [
                        'status' => false,
                        'error' => ['parent_has_deleted' => 'project']
                    ];
                }

                $task_group->update(['delete_flg' => config('apps.general.not_deleted')]);
                return [
                    'status' => true
                ];
            } else {
                return [
                    'status' => false,
                    'error' => ['table_not_found' => $table]
                ];
            }
        } catch (\Exception $ex) {
            return [
                'status' => false,
                'error' => ['exception' => $ex->getMessage()]
            ];
        }
    }

    public function getTaskGroupByProject($projectId, $filter)
    {
        $model = $this->getModel();

        $model = $model::where('project_id', $projectId);
        $model = $model->where('delete_flg', config('apps.general.not_deleted'));

        $model = $model->withCount('tasks');
        $model = $model->withCount('tasks_not_complete');
        $model = $model->with('disp_color:disp_color_id,disp_color_name,color_code');

        $model = $model->with('tasks', function ($query) use ($filter, $projectId) {
            $query->where('delete_flg', config('apps.general.not_deleted'));

            $query->where('project_id', $projectId);

            $query->where(function ($query2) {
                $query2->orWhere('parent_task_id', null)
                    ->orWhere('parent_task_id', '');
            });

            if ($filter['status'] != null && count($filter['status']) > 0) {
                $query->whereIn('task_status_id', $filter['status']);
            }

            if ($filter['priority'] != null && count($filter['priority']) > 0) {
                $query->whereIn('priority_id', $filter['priority']);
            }
            if ($filter['manager'] != null && count($filter['manager']) > 0) {
                $query->whereIn('user_id', $filter['manager']);
            }
            if ($filter['author'] != null && count($filter['author']) > 0) {
                $query->whereIn('create_user_id', $filter['author']);
            }

            if ($filter['watch_list'] == true) {
                $query->with('watch_lists');
                $query->has('watch_lists');
            }

            // Relationship
            $query->withCount('sub_tasks');
            $query->withCount('sub_tasks_complete');
            $query->withCount('check_lists');
            $query->withCount('check_lists_complete');
            $query->with(Task::TASK_RELATION);
        });

        $model = $model->with('tasks.sub_tasks', function ($query2) {
            $query2->withCount('check_lists');
            $query2->withCount('check_lists_complete');
            $query2->with(Task::TASK_RELATION);
        })->whereHas('tasks', function ($query) use ($filter, $projectId) {
            $query->where('delete_flg', config('apps.general.not_deleted'));

            $query->where('project_id', $projectId);

            $query->where(function ($query2) {
                $query2->orWhere('parent_task_id', null)
                    ->orWhere('parent_task_id', '');
            });

            if ($filter['status'] != null && count($filter['status']) > 0) {
                $query->whereIn('task_status_id', $filter['status']);
            }

            if ($filter['priority'] != null && count($filter['priority']) > 0) {
                $query->whereIn('priority_id', $filter['priority']);
            }
            if ($filter['manager'] != null && count($filter['manager']) > 0) {
                $query->whereIn('user_id', $filter['manager']);
            }
            if ($filter['author'] != null && count($filter['author']) > 0) {
                $query->whereIn('create_user_id', $filter['author']);
            }
            if ($filter['watch_list'] == true) {
                $query->with('watch_lists');
                $query->has('watch_lists');
            }
        });

        $model = $model->has('tasks');

        $model = $model->orderBy('create_datetime', 'DESC');
        $model = $model->paginate(config('apps.notification.record_per_page'));

        $currentUserId = Auth::user()->user_id;
        $model->getCollection()->transform(function ($item) use ($currentUserId) {
            foreach ($item['tasks'] as &$task) {
                foreach ($task['sub_tasks'] as &$sub_task) {
                    $sub_task = $this->formatRecord($sub_task);
                    $sub_task = $this->taskRepo->detailTask($sub_task, $currentUserId);
                }
                $task = $this->formatRecord($task);
                $task = $this->taskRepo->detailTask($task, $currentUserId, false);
                $task = $this->unsetRelationForTaskGroupByProject($task);
            }

            return $item;
        });

        return $model;
    }

    public function getTaskGroupByProjectV2($projectId, $filter)
    {
        try {
            DB::statement("SET sql_mode = false");
            $taskGroups = $this->getModel()::query()
                ->leftJoin('t_task', 't_task.task_group_id', '=', 't_task_group.task_group_id')
                ->join('t_project', 't_task_group.project_id', '=', 't_project.project_id')
                ->where([
                    't_project.delete_flg' => config('apps.general.not_deleted'),
                    't_project.project_id' => $projectId
                ])
                ->where(function ($query) {
                    $query->orWhere('t_task.parent_task_id', null)
                        ->orWhere('t_task.parent_task_id', '');
                });
            if (is_countable($filter['status']) && count($filter['status'])) {
                $taskGroups = $taskGroups->whereIn('task_status_id', $filter['status']);
            }
            if (is_countable($filter['priority']) && count($filter['priority'])) {
                $taskGroups = $taskGroups->whereIn('priority_id', $filter['priority']);
            }
            if (is_countable($filter['manager']) && count($filter['manager'])) {
                $taskGroups = $taskGroups->whereIn('user_id', $filter['manager']);
            }
            if (is_countable($filter['author']) && count($filter['author'])) {
                $taskGroups = $taskGroups->whereIn('create_user_id', $filter['author']);
            }
            $taskGroups = $taskGroups->orderBy('t_task_group.create_datetime', 'DESC')
                ->select(['t_task_group.*', 't_project.project_name'])
                ->groupBy(['t_task_group.task_group_id'])
                ->with([TaskGroup::TASKS_FULLL, TaskGroup::TASKS_NOT_COMPLETE_FULLL, TaskGroup::DISP_COLOR])
                ->paginate(config('apps.notification.record_per_page'));
            $currentUserId = Auth::user()->user_id;
            foreach ($taskGroups as $tg) {
                $tasks = $tg['task_full'];
                foreach ($tasks as $task) {
                    if (count($task['sub_tasks'])) {
                        foreach ($task['sub_tasks'] as $sub) {
                            $sub = $this->formatRecord($sub);
                            $sub = $this->taskRepo->detailTask($sub, $currentUserId);
                        }
                    }
                    $task = $this->formatRecord($task);
                    $task = $this->taskRepo->detailTask($task, $currentUserId, false);
                    $task = $this->unsetRelationForTaskGroupByProject($task);
                }
                $tg->tasks_count = count($tasks);
                $tg->tasks_not_complete_count = count($tg['tasks_not_complete_full']);
            }

            return $taskGroups;
        } catch (\Exception $exception) {
            dd($exception);
        }
    }

    public function getTaskGroupByProjectV4($projectId, $filter, $flagFilter)
    {
        DB::statement("SET sql_mode = false");

        $sqlParentTask = '(
            SELECT
                t_task_group.project_id,
                t_task_group.display_order,
                t_task_group.task_group_id,
                t_task_group.group_name,
                t_task.task_id,
                t_task.parent_task_display_order,
                row_number() over(order by $order_by ) as parent_rank
            FROM t_task_group
            $join t_task ON t_task.task_group_id = t_task_group.task_group_id AND t_task.delete_flg = $delete_flg
            WHERE
                t_task_group.project_id = "$project_id"
                AND t_task.parent_task_id IS NULL
                AND t_task_group.delete_flg = $delete_flg
        )
        as parent_task';

        $values = [
            '$project_id' => $projectId,
            '$delete_flg' => config('apps.general.not_deleted'),
            '$order_by' => 't_task_group.display_order DESC, t_task.parent_task_display_order DESC',
        ];

        if ($flagFilter) {
            $values['$join'] = 'INNER JOIN';
        } else {
            $values['$join'] = 'LEFT JOIN';
        }

        $parentTask = strtr($sqlParentTask, $values);

        $model = DB::table(DB::raw($parentTask));

        $model = $model->leftJoin('t_task', function ($join) {
            $join->on('t_task.parent_task_id', '=', 'parent_task.task_id');
            $join->orOn('t_task.task_id', '=', 'parent_task.task_id');
            $join->where('t_task.delete_flg', config('apps.general.not_deleted'));
        });

        $model = $model->select(
            'parent_task.project_id',
            'parent_task.task_group_id',
            'parent_task.group_name',
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

        // Log::info($model->toSql());

        $model = $model->paginate(config('apps.general.paginate_default'));

        return $model;
    }

    public function formatRecord($record)
    {
        $record['project_name'] = isset($record['project']) ? $record['project']['project_name'] : '';
        $record['task_group_name'] = isset($record['task_group']) ? $record['task_group']['group_name'] : '';
        $record['user_name'] = isset($record['user']) ? $record['user']['user_name'] : '';
        $record['start_date'] = isset($record['start_date']) ? Carbon::parse($record['start_date'])->format('Y/m/d') : '';
        $record['end_date'] = isset($record['end_date']) ? Carbon::parse($record['end_date'])->format('Y/m/d') : '';
        $record['piority_name'] = isset($record['priority_mst']) ? $record['priority_mst']['piority_name'] : '';
//        $record['number_subtask'] = count($record['sub_tasks']);
        $record['number_subtask'] = count($record['sub_tasks']);
        $record['number_subtask_completed'] = count($record['sub_tasks_complete']);
        $record['number_checklist'] = count($record['check_lists']);
        $record['number_checklist_completed'] = count($record['check_lists_complete']);
        $record['start_plan_date'] = isset($record['start_plan_date']) ? Carbon::parse($record['start_plan_date'])->format('Y/m/d') : '';
        $record['end_plan_date'] = isset($record['end_plan_date']) ? Carbon::parse($record['end_plan_date'])->format('Y/m/d') : '';
        $record['icon_image_path'] = isset($record['user']['icon_image_path']) ? asset('storage/' . $record['user']['icon_image_path']) : '';
        return $record;
    }

    public function unsetRelationForTaskGroupByProject($item)
    {
        /* keep item sub_tasks, and unset other */
        unset($item['project'], $item['check_lists'],
            $item['check_lists_complete'], $item['user'], $item['sub_tasks_complete']
            , $item['breakdowns'], $item['reminds'], $item['disclosure_range_mst']
            , $item['priority_mst'], $item['task_group'], $item['goods'], $item['watch_lists']
            , $item['attachment_files']);

        return $item;
    }

    public function getGroupDetail($taskGroupId)
    {
        return $this->getInstance()->query()
            ->join('m_task_group_disp_color', 'm_task_group_disp_color.disp_color_id', '=', 't_task_group.disp_color_id')
            ->join('t_project', 't_project.project_id', '=', 't_task_group.project_id')
            ->select(
                't_task_group.task_group_id',
                't_task_group.project_id',
                't_task_group.group_name',
                't_task_group.disp_color_id',
                'm_task_group_disp_color.disp_color_name',
                't_project.project_name'
            )
            ->where('t_task_group.task_group_id', $taskGroupId)
            ->where('t_task_group.delete_flg', config('apps.general.not_deleted'))
            ->get();
    }

    public function isExists($task_group_id)
    {
        return $this->getInstance()::where('task_group_id', $task_group_id)->exists();
    }

    public function getDataExport($projectId, $filter)
    {
        $model = $this->getModel();

        $model = $model::where('project_id', $projectId);
        $model = $model->where('delete_flg', config('apps.general.not_deleted'));

        if ($filter['group'] !== null && count($filter['group']) > 0) {
            $model = $model->whereIn('task_group_id', $filter['group']);
        }

        $model = $model->with('tasks', function ($query) use ($projectId) {
            $query->where('delete_flg', config('apps.general.not_deleted'));

            $query->where('project_id', $projectId);

            $query->where(function ($query2) {
                $query2->orWhere('parent_task_id', null)
                    ->orWhere('parent_task_id', '');
            });

            // Relationship
            $query->withCount('sub_tasks');
            $query->withCount('sub_tasks_complete');
            $query->withCount('check_lists');
            $query->withCount('check_lists_complete');

            $query->with('sub_tasks', function ($query2) {
                $query2->withCount('check_lists');
                $query2->withCount('check_lists_complete');
                $query2->with('user');
                $query2->with('task_status');
                $query2->with('priority_mst');
                $query2->with('check_lists');
                $query2->with('breakdowns');
                $query2->with('comments');
            });
            $query->with('user');
            $query->with('task_status');
            $query->with('priority_mst');
            $query->with('check_lists');
            $query->with('breakdowns');
            $query->with('comments');
        })->whereHas('tasks', function ($query) use ($projectId) {
            $query->where('delete_flg', config('apps.general.not_deleted'));
            $query->where('project_id', $projectId);

            $query->where(function ($query2) {
                $query2->orWhere('parent_task_id', null)
                    ->orWhere('parent_task_id', '');
            });
        });

        $model = $model->orderBy('create_datetime', 'DESC');
        $model = $model->get();

        // response
        return $model;
    }

    public function getTaskGroup($projectId)
    {
        $model = $this->getModel();
        return $model::where('project_id', $projectId)
            ->where('delete_flg', config('apps.general.not_deleted'))
            ->select('task_group_id', 'group_name')->get();
    }

    public function getTaskGroupByProjectV5($projectId, $filter, $flagFilter, $groups = [])
    {
        $model = $this->getModel();

        $model = $model::where('project_id', $projectId);
        $model = $model->where('delete_flg', config('apps.general.not_deleted'));

        if (count($groups) > 0) {
            $model = $model->whereIn('task_group_id', $groups);
        }

        $model = $model->with([
            'tasks' => function ($query) use ($filter) {
                $query->where(function ($query2) {
                    $query2->orWhere('parent_task_id', null)
                        ->orWhere('parent_task_id', '');
                });

                if ($filter['watch_list'] == true) {
                    $query->has('watch_lists');
                }

                if (is_countable($filter['status']) && count($filter['status']) > 0) {
                    $query->whereIn('task_status_id', $filter['status']);
                }
                if (is_countable($filter['priority']) && count($filter['priority']) > 0) {
                    $query->whereIn('priority_id', $filter['priority']);
                }
                if (is_countable($filter['manager']) && count($filter['manager']) > 0) {
                    $query->whereIn('user_id', $filter['manager']);
                }
                if (is_countable($filter['author']) && count($filter['author']) > 0) {
                    $query->whereIn('create_user_id', $filter['author']);
                }

                $query->withCount('sub_tasks');
                $query->withCount('sub_tasks_complete');
                $query->withCount('check_lists');
                $query->withCount('check_lists_complete');
                $query->with('user');
                $query->with('task_status');
                $query->with('priority_mst');

                $query->orderBy('parent_task_display_order', 'DESC');

                $query->with('sub_tasks', function ($query2) use ($filter) {
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

                    $query2->orderBy('sub_task_display_order', 'DESC');
                });
            }]);

        if ($flagFilter) {
            $model = $model->whereHas('tasks', function ($query) use ($filter, $projectId) {
                $query->where('delete_flg', config('apps.general.not_deleted'));

                if (is_countable($filter['status']) && count($filter['status']) > 0) {
                    $query->whereIn('task_status_id', $filter['status']);
                }
                if (is_countable($filter['priority']) && count($filter['priority']) > 0) {
                    $query->whereIn('priority_id', $filter['priority']);
                }
                if (is_countable($filter['manager']) && count($filter['manager']) > 0) {
                    $query->whereIn('user_id', $filter['manager']);
                }
                if (is_countable($filter['author']) && count($filter['author']) > 0) {
                    $query->whereIn('create_user_id', $filter['author']);
                }

                if ($filter['watch_list'] == true) {
                    $query->has('watch_lists');
                }
            });
        }



        $model = $model->orderBy('display_order', 'DESC');
        $model = $model->get();

        return $model;
    }
}
