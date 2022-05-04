<?php

namespace App\Repositories;

use App\Models\User;
use App\Services\AppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(User::class);
        $this->fields = $this->getInstance()->getFillable();
    }

    public function getUserByUserId(string $userId)
    {
        return $this->getInstance()->where('user_id', $userId)->first();
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
        //$record->name = $record
        return $record;
    }

    public function updateById($id, array $data)
    {
        return $this->getModel()::where('user_id', $id)->update($data);
    }

    /**
     * get user by company
     *
     * @param array $queryParams
     * @return collection
     */
    public function getUserLicense($queryParams = [])
    {
        $query = $this->getModel()::query()->select([
                            'user_id',
                            'mail_address',
                            'user_group_id',
                            'super_user_auth_flg',
                            'service_contractor_auth_flg',
                            'company_search_flg'
        ]);
        $query->where(function ($q) use (&$queryParams) {
            if (isset($queryParams['super_user_auth_flg'])) {
                $q->orWhere('super_user_auth_flg', $queryParams['super_user_auth_flg']);
                unset($queryParams['super_user_auth_flg']);
            }
            if (isset($queryParams['service_contractor_auth_flg'])) {
                $q->orWhere('service_contractor_auth_flg', $queryParams['service_contractor_auth_flg']);
                unset($queryParams['service_contractor_auth_flg']);
            }
            if (isset($queryParams['company_search_flg'])) {
                $q->orWhere('company_search_flg', $queryParams['company_search_flg']);
                unset($queryParams['company_search_flg']);
            }
        });

        if (isset($queryParams['user_groups']) && is_array($queryParams['user_groups'])) {
            $query->whereIn('t_user.user_group_id', $queryParams['user_groups']);
            unset($queryParams['user_groups']);
        }

        $query->where($queryParams);
        return $query->orderBy('mail_address', 'asc')->get();
    }

    public function update($id, $data)
    {
        $data = User::where('user_id', $id)->update($data);
        return $data;
    }

    /**
     * Create user record
     *
     * @param  array $userInfo
     * @return mixed
     */
    public function create($userInfo)
    {
        $userInfo['user_id'] = AppService::generateUUID();
        try {
            $this->getInstance()->create($userInfo);
            return [
                'status' => config('apps.general.success'),
                'message' => [trans('message.SUCCESS')]
            ];
        } catch (\Exception $e) {
            return [
                'status' => config('apps.general.error'),
                'message' => [trans('message.ERR_EXCEPTION')]
            ];
        }
    }

    public function countUsedLicenceNum($queryParams)
    {
        $queryParams[] = ['user_group_id', '!=', ""];
        return $this->getInstance()::where($queryParams)
            ->where('delete_flg', config('apps.general.not_deleted'))
            ->count();
    }

    /**
     * Get user by login key and passsword
     *
     * @param  string $loginKey
     * @param  string $mailAddress
     * @return mixed
     */
    public function getUserByLoginKeyAndPasssword($loginKey, $mailAddress)
    {
        return $this->getModel()::join(
            't_company',
            't_user.company_id',
            '=',
            't_company.company_id'
        )
            ->where('t_company.login_key', $loginKey)
            ->where('t_user.mail_address', $mailAddress)
            ->first(['t_user.user_id', 't_user.mail_address']);
    }

    public function getUserByProject($projectId, $filter)
    {
        $model = $this->getModel();

        $model = $model::where('delete_flg', config('apps.general.not_deleted'));

        $model = $model->with('task', function ($query) use ($filter, $projectId) {
            $query->where('project_id', $projectId);

            $query->where(function ($query2) {
                $query2->orWhere('parent_task_id', null)
                    ->orWhere('parent_task_id', '');
            });

            if ($filter['watch_list'] == true) {
                $query->with('watch_lists');
                $query->has('watch_lists');
            }

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

            // Relationship
            $query->withCount('sub_tasks');
            $query->withCount('sub_tasks_complete');
            $query->withCount('check_lists');
            $query->withCount('check_lists_complete');
            $query->with('task_group');
            $query->with('task_status');
            $query->with('priority_mst');

            $query->with('sub_tasks', function ($query2) use ($filter) {
                if ($filter['watch_list'] == true) {
                    $query2->with('watch_lists');
                    $query2->has('watch_lists');
                }

                if ($filter['status'] != null && count($filter['status']) > 0) {
                    $query2->whereIn('task_status_id', $filter['status']);
                }

                if ($filter['priority'] != null && count($filter['priority']) > 0) {
                    $query2->whereIn('priority_id', $filter['priority']);
                }
                if ($filter['manager'] != null && count($filter['manager']) > 0) {
                    $query2->whereIn('user_id', $filter['manager']);
                }
                if ($filter['author'] != null && count($filter['author']) > 0) {
                    $query2->whereIn('create_user_id', $filter['author']);
                }

                $query2->withCount('check_lists');
                $query2->withCount('check_lists_complete');
                $query2->with('task_group');
                $query2->with('task_status');
                $query2->with('priority_mst');
            });
        });

        $model = $model->orderBy('create_datetime', 'DESC');
        $model = $model->paginate(config('apps.general.paginate_default'));

        // response
        return $model;
    }

    public function getTaskManagerByProjectV4($projectId, $filter, $flagFilter)
    {
        DB::statement("SET sql_mode = false");

        $sqlParentTask = '(
            SELECT
                t_user.user_id,
                t_user.disp_name,
                t_task.task_id,
                t_task.project_id,
                row_number() over(order by $order_by ) as parent_rank
            FROM t_user
            $join t_task ON t_task.user_id = t_user.user_id AND t_task.delete_flg = $delete_flg
            WHERE
                t_task.project_id = "$project_id"
                AND t_task.parent_task_id IS NULL
                AND t_user.delete_flg = $delete_flg
        )
        as parent_task';

        $values = [
            '$project_id' => $projectId,
            '$delete_flg' => config('apps.general.not_deleted'),
            '$order_by' => 't_user.create_datetime DESC, t_task.create_datetime DESC',
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
            'parent_task.user_id',
            'parent_task.disp_name',
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

    public function isExists($id)
    {
        return $this->getInstance()::where('user_id', $id)->exists();
    }

    public function searchMemberByName($company_id, $key_word)
    {
        $model = $this->getModel();
        $model = $model::where('company_id', $company_id)
                ->where('guest_flg', config('apps.general.not_guest'));
        if ($key_word) {
            $model = $model->where('disp_name', 'LIKE', '%' . $key_word . '%');
        }
        $model = $model->get(['user_id', 'disp_name']);
        return $model;
    }

    /**
     * transform
     *
     * @param  collection $users
     * @return collection
     */
    public function transformImagePath($users)
    {
        return $users->transform(function ($user) {
            $user['icon_image_path'] = $user->iconImageUrl;
            return $user;
        });
    }

    public function getProjectMembers($projectId)
    {
        return $this->getModel()::join('t_project_participant', 't_user.user_id', '=', 't_project_participant.user_id')
            ->where('t_project_participant.project_id', $projectId)
            ->where('t_user.delete_flg', config('apps.general.not_deleted'))
            ->get(['t_user.user_id', 't_user.disp_name', 't_user.icon_image_path']);
    }

    public function getUserByProjectV5($projectId, $filter, $flagFilter)
    {
        $model = $this->getModel();
        $model = $model::where('delete_flg', config('apps.general.not_deleted'));

        $model = $model->with([
            'task' => function ($query) use ($filter, $projectId) {
                $query->where('project_id', $projectId);

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
                $query->with('task_group');
                $query->with('user');
                $query->with('task_status');
                $query->with('priority_mst');

                $query->orderBy('parent_task_display_order', 'DESC');

                $query->with('sub_tasks', function ($query2) use ($filter, $projectId) {
                    $query2->where('project_id', $projectId);

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
                    $query2->with('task_group');
                    $query2->with('user');
                    $query2->with('task_status');
                    $query2->with('priority_mst');

                    $query2->orderBy('sub_task_display_order', 'DESC');
                });
            }]);

        if ($flagFilter) {
            $model = $model->whereHas('task', function ($query) use ($filter, $projectId) {
                $query->where('delete_flg', config('apps.general.not_deleted'));
                $query->where('project_id', $projectId);

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


        $model = $model->orderBy('create_datetime', 'DESC');
        //Log::info($model->toSql());
        $model = $model->get();

        // response
        return $model;
    }
}
