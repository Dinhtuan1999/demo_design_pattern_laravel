<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;

class UserProjectService
{
    public function __construct(UserRepository $userRepo)
    {
        $this->userRepo = $userRepo;
    }

    public function listProjectByUser($userId, Request $request = null)
    {
        $projectStatus = 0;
        if (!is_null($request->input('project_status'))) {
            $projectStatus = (int)$request->input('project_status');
        }
        $result = [];
        $result['status'] = config('apps.general.error');
        $result['message'] = [];
        $data = $this->userRepo->getByCol('user_id', $userId, [ User::PROJECTS ]);
        if (empty($data)) {
            $result['status'] = config('apps.general.error');
            $result['message'] = [trans('message.ERR_COM_0011')];
            return $result;
        }
        $result['status'] = config('apps.general.success');
        $data = $data->projects()->where('t_project_participant.delete_flg', config('apps.general.not_deleted'))->with([Project::TASKS, Project::TASKS_COMPLETED]);
        if (!is_null($request->input('project_name'))) {
            $data = $data->where('project_name', 'LIKE', '%'.$request->input('project_name').'%');
        }
        // filter by project status id
        switch ($projectStatus) {
            case config('apps.project.filter_key.in_progress'):
                $data = $data->where('project_status', config('apps.project.project_status.in_progress'));
                break;
            case config('apps.project.filter_key.all_project'):
                $data = $data->where(function ($query) {
                    $query->orWhere('project_status', config('apps.project.project_status.in_progress'))
                        ->orWhere('project_status', config('apps.project.project_status.complete'));
                });
                break;
            case config('apps.project.filter_key.complete'):
                $data = $data->where('project_status', config('apps.project.project_status.complete'));
                break;
        }
        $data = $data->get();
        if ($data) {
            $data = $data->toArray();
            foreach ($data as $key => $item) {
                $totalTask = count($item['tasks']);
                $totalCompleted = count($item['tasks_completed']);
                $percent = !empty($totalTask) ? round($totalCompleted / $totalTask, 2) : 0;
                $percent = $percent !== 0 ? number_format($percent * 100) : 0;
                $data[$key]['percent_complete'] = $percent.'%';
                unset($data['data'][$key]['tasks'], $data['data'][$key]['tasks_completed']);
            }
        }
        $result['data'] = $data;
        return $result;
    }

    private $userRepo;
}
