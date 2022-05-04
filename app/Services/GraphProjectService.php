<?php

namespace App\Services;

use App\Repositories\TaskGroupRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GraphProjectService extends BaseService
{
    public function __construct(
        UserRepository $userRepo,
        TaskGroupRepository $taskGroupRepo
    ) {
        $this->userRepo = $userRepo;
        $this->taskGroupRepo = $taskGroupRepo;
        $this->statusColors        = config('apps.task.status_color');
    }

    /**
     * S.C040.12 Get graph project data
     *
     * @param  string $projectId
     * @return mixed
     */
    public function getGraphData($projectId)
    {
        // 1 : validate project Id
        $validator = Validator::make(['project_id' => $projectId], [
            'project_id' => [
                'required',
                Rule::exists('t_project', 'project_id')
                    ->where('delete_flg', config('apps.general.not_deleted'))
            ]
        ]);
        if ($validator->fails()) {
            return $this->sendError($validator->messages()->all());
        }

        // 2 : get graph project data
        $data['pie_chart_1'] = $this->dataTaskAllStatus($projectId);
        $data['pie_chart_2'] = $this->dataTaskCompleteAndInComplete($projectId);
        $data['stacked_chart_1'] = $this->dataTaskMember($projectId);
        $data['stacked_chart_2'] = $this->dataTaskGroup($projectId);

        return $this->sendResponse([trans('message.COMPLETE')], $data);
    }

    public function dataTaskAllStatus($projectId)
    {
        $result           = [];
        $result['data']   = [];
        $result['total']  = 0;
        $statusKey        = array_keys(config('apps.task.pie_chart_status_key'));
        $sql              = "SELECT
                                COUNT( CASE WHEN task_status_id = 0 THEN 1 END ) AS not_started,
                                COUNT( CASE WHEN task_status_id = 1 THEN 1 END ) AS in_progress,
                                COUNT( CASE WHEN task_status_id = 2 THEN 1 END ) AS delay_start,
                                COUNT( CASE WHEN task_status_id = 3 THEN 1 END ) AS delay_complete,
                                COUNT( CASE WHEN task_status_id = 4 THEN 1 END ) AS complete
                            FROM
                                t_task
                            WHERE
                                project_id = ? AND delete_flg = ?";
        $queryResult      = DB::select($sql, [ $projectId, config('apps.general.not_deleted') ]);
        if (!empty($queryResult[0])) {
            $queryResult = (array)$queryResult[0];
            $totalTask = array_sum($queryResult);
            $result['total'] = $totalTask;
            $calculatedPercent = [];
            $notStartedValue = null;
            $notStartedKey = null;
            foreach ($statusKey as $key => $value) {
                $result['data'][$key]['id']   = $key;
                $result['data'][$key]['data']   = $queryResult[$value];
                $result['data'][$key]['label'] = trans('label.task')[$value];
                $result['data'][$key]['color'] = $this->statusColors[$value];
                if ($value != config('apps.task.status.0')) {
                    $result['data'][$key]['percent'] = $this->calculatePercent($queryResult[$value], $totalTask);
                    $calculatedPercent[] = $result['data'][$key]['percent'];
                } else {
                    $notStartedKey = $key;
                    $notStartedValue = $result['data'];
                }
            }
            $result['data'][$notStartedKey] = array_merge($notStartedValue[0], ['percent' => strval(100 - array_sum($calculatedPercent))]);
        }
        return $result;
    }

    public function dataTaskCompleteAndInComplete($projectId)
    {
        $result           = [];
        $result['data']   = [];
        $result['total']  = 0;
        $sql              = "SELECT
                                COUNT( CASE WHEN task_status_id = 2 THEN 1 END ) AS complete,
                                COUNT( CASE WHEN task_status_id <> 2 THEN 1 END ) AS not_complete
                            FROM
                                t_task
                            WHERE
                                project_id = ? AND delete_flg = ?";
        $queryResult      = DB::select($sql, [ $projectId, config('apps.general.not_deleted')]);
        if (!empty($queryResult[0])) {
            $queryResult        = (array)$queryResult[0];
            $totalTask = array_sum($queryResult);
            $result['total'] = $totalTask;

            $result['data'][0]['id']   = config('apps.task.status_key.complete');
            ;
            $result['data'][0]['data']   = $queryResult['complete'];
            $result['data'][0]['label'] = trans('label.task.complete');
            $result['data'][0]['percent'] = $this->calculatePercent($queryResult['complete'], $totalTask);
            $result['data'][0]['color'] = config('apps.task.status_color.complete');

            $result['data'][1]['id']   = config('apps.task.status_key_not_complete');
            $result['data'][1]['data']   = $queryResult['not_complete'];
            $result['data'][1]['label'] = trans('label.task.not_complete');
            $result['data'][1]['percent'] = $this->calculatePercent($queryResult['not_complete'], $totalTask);
            $result['data'][1]['color'] = config('apps.task.status_color.not_complete');
        }
        return $result;
    }

    public function dataTaskMember($projectId)
    {
        $result             = [];
        $userInfo  = $this->userRepo->getModel()::join('t_project_participant', 't_user.user_id', '=', 't_project_participant.user_id')
            ->where('t_project_participant.project_id', $projectId)
            ->where('t_user.delete_flg', config('apps.general.not_deleted'))
            ->get(['t_user.user_id', 't_user.disp_name']);
        if ($userInfo->count() == 0) {
            return $result;
        }

        $usernames = [];
        foreach ($userInfo as $info) {
            $usernames[$info->user_id] = $info->disp_name;
            $users[] = $info->user_id;
        }
        $sqlSelect = [];
        $statusKey = (config('apps.task.status_key'));
        $users[] = null;
        $usernames = array_merge($usernames, [config('apps.task.task_detail.others') => trans('label.project.others')]);

        foreach ($users as $user) {
            foreach ($statusKey as $key => $value) {
                if (is_null($user)) {
                    $keyCount    = str_replace('-', '_', config('apps.task.task_detail.others')).'__'.$key;
                    $count       = " COUNT( CASE WHEN task_status_id = $value AND user_id IS NULL THEN 1 END ) AS '{$keyCount}' ";
                } else {
                    $keyCount    = str_replace('-', '_', $user).'__'.$key;
                    $count       = " COUNT( CASE WHEN task_status_id = $value AND user_id = '{$user}' THEN 1 END ) AS '{$keyCount}' ";
                }

                $sqlSelect[] = $count;
            }
        }
        $sqlSelectStr = implode(', ', $sqlSelect);

        $sql          = "SELECT
                            $sqlSelectStr
                        FROM
                            t_task
                        WHERE
                            project_id = ? AND delete_flg = ?";
        $queryResult    = DB::select($sql, [ $projectId, config('apps.general.not_deleted') ]);
        if (!empty($queryResult[0])) {
            $queryResult = (array) $queryResult[0];
            $result = $this->calculateStackedChart($queryResult, $usernames, 'user_id', 'user_name');
        }

        return $result;
    }

    public function dataTaskGroup($projectId)
    {
        $result           = [];
        $taskGroups = $this->taskGroupRepo->all(
            [ 'project_id' => $projectId, 'delete_flg' => config('apps.general.not_deleted') ],
            [],
            [],
            ['task_group_id', 'group_name']
        )
        ;
        if ($taskGroups->count() == 0) {
            return $result;
        }

        $taskGroupNames = [];
        $taskGroupIds = [];
        $sqlSelect = [];
        $statusKey = (config('apps.task.status_key'));
        foreach ($taskGroups as $taskGroup) {
            $taskGroupIds[] = $taskGroup->task_group_id;
            $taskGroupNames[$taskGroup->task_group_id] = $taskGroup->group_name;
        }
        $taskGroupIds[] = null;
        $taskGroupNames = array_merge($taskGroupNames, [config('apps.task.task_detail.others') => trans('label.project.others')]);
        foreach ($taskGroupIds as $taskGroupId) {
            foreach ($statusKey as $key => $value) {
                if (is_null($taskGroupId)) {
                    $keyCount    = str_replace('-', '_', config('apps.task.task_detail.others')).'__'.$key;
                    $count       = " COUNT( CASE WHEN task_status_id = $value AND task_group_id IS NULL THEN 1 END ) AS '{$keyCount}' ";
                } else {
                    $keyCount    = str_replace('-', '_', $taskGroupId).'__'.$key;
                    $count       = " COUNT( CASE WHEN task_status_id = $value AND task_group_id = '{$taskGroupId}' THEN 1 END ) AS '{$keyCount}' ";
                }

                $sqlSelect[] = $count;
            }
        }

        $sqlSelectStr = implode(', ', $sqlSelect);
        $sql          = "SELECT
                            $sqlSelectStr
                        FROM
                            t_task
                        WHERE
                            project_id = ? AND delete_flg = ?";

        $queryResult      = DB::select($sql, [ $projectId, config('apps.general.not_deleted') ]);

        if (!empty($queryResult[0])) {
            $queryResult        = (array) $queryResult[0];
            $result = $this->calculateStackedChart($queryResult, $taskGroupNames, 'task_group_id', 'group_name');
        }

        return $result;
    }

    /**
     * Calculate percent
     *
     * @param  int $value
     * @param  int $total
     * @return string
     */
    public function calculatePercent($value, $total)
    {
        if (empty($total)) {
            return "0";
        }
        return number_format((($value / $total) *  100), 0);
    }

    public function calculateStackedChart($queryResult, $arrayNames, $idKey = 'user_id', $nameKey = 'user_name')
    {
        $result    = [];
        $statusKey = (config('apps.task.stacked_chart_status_key'));
        $keyProgress = [];
        $indexMember = 0;
        foreach ($queryResult as $key => $value) {
            $keyArr = explode('__', $key);
            $userKey = $keyArr[0];
            if (!in_array($userKey, $keyProgress, true)) {
                $result[$indexMember][$idKey] = $userKey;
                $result[$indexMember][$nameKey] = $arrayNames[str_replace('_', '-', $userKey)];

                $itemDatasets = [];
                $totalTask = 0;
                foreach ($statusKey as $keyStatus => $valueStatus) {
                    $itemDatasets[$valueStatus]['id'] = $valueStatus;
                    $itemDatasets[$valueStatus]['data'] = $queryResult[$userKey.'__'.$keyStatus];
                    $itemDatasets[$valueStatus]['label'] = trans('label.task')[$keyStatus];
                    $totalTask += $queryResult[$userKey.'__'.$keyStatus];
                    $itemDatasets[$valueStatus]['percent'] = "0";

                    $itemDatasets[$valueStatus]['color'] = $this->statusColors[$keyStatus];
                }
                $calculatedPercent = [];
                $notStartedKey = null;
                if ($totalTask) {
                    foreach ($itemDatasets as $key => $dataset) {
                        if ($dataset['label'] != trans('label.task.not_started')) {
                            $itemDatasets[$key]['percent'] = $this->calculatePercent($itemDatasets[$key]['data'], $totalTask);
                            $calculatedPercent[] = $itemDatasets[$key]['percent'];
                        } else {
                            $notStartedKey = $key;
                        }
                    }
                    $itemDatasets[$notStartedKey]['percent'] = strval(100 - array_sum($calculatedPercent));
                }

                $result[$indexMember]['total'] = $totalTask;
                $result[$indexMember]['data'] = $itemDatasets;
                $keyProgress[] = $userKey;
                $indexMember++;
            }
        }
        return $result;
    }

    private $userRepo;
    private $taskGroupRepo;
    private $statusColors;
}
