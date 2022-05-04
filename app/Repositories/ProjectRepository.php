<?php

namespace App\Repositories;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

class ProjectRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(Project::class);
        $this->fields = Project::FIELDS;
    }

    public function restoreProjectFromTrash($project_id)
    {
        $table = 't_task_group';
        try {
            if ($project_id) {
                // check project is deleted
                $project = $this->getById($project_id);
                if ($project && $project->delete_flg != config('apps.general.is_deleted')) {
                    return [
                        'status' => false,
                        'error' => ['table_not_found' => $table]
                    ];
                }

                $project->update(['delete_flg' => config('apps.general.not_deleted')]);
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

    public function isExists($projectId)
    {
        return $this->getInstance()::where('project_id', $projectId)->exists();
    }

    public function getListProjectInProgress($company_id)
    {
        $model = $this->getModel();

        $model = $model::where('company_id', $company_id)
            ->where('delete_flg', config('apps.general.not_deleted'))
            ->where('project_status', config('apps.project.status_key.in_progress'))
            ->orderBy('project_name', 'asc')
            ->get(['project_id', 'project_name']);
        return $model->take(5)->toArray();
    }

    public function getAllProjectOfCompanyWithTasks(string $companyId)
    {
        $model = $this->getModel();

        $model = $model::select('project_id', 'project_name', 'actual_start_date', 'actual_end_date', 'project_overview_public');
        $model = $model->where('company_id', $companyId);
        $model = $model->where('delete_flg', config('apps.general.not_deleted'));
        $model = $model->withCount(Project::PROJECT_PARTICIPANTS . ' AS number_of_person');
        $model = $model->withCount(Project::TASKS . ' AS number_of_tasks');
        return $model->get();
    }

    public function getAllProjectOfCompanyWithAttributes(string $companyId)
    {
        return $this->getInstance()::where('company_id', $companyId)
                         ->where('t_project.delete_flg', config('apps.general.not_deleted'))
                         ->with('project_attributes')
                         ->get();
    }

    /**
     * Get All Project by company_id
     * TODO: S.E020.2
     * 2022-02-21
     *
     * @param $companyId
     * @return mixed
     */
    public function getListProjectsByCompanyId($companyId)
    {
        $model = $this->getModel();

        $model = $model::where('company_id', $companyId);
        $model = $model->where('delete_flg', config('apps.general.not_deleted'));
        return $model->get();
    }

    public function getListLog($projectId, $identifyCode = null)
    {
        $query = $this->getInstance()->query()
                ->join('t_project_log', 't_project_log.project_id', '=', 't_project.project_id')
                ->select(
                    't_project_log.log_id',
                    't_project_log.project_id',
                    't_project_log.task_group_id',
                    't_project_log.task_id',
                    't_project_log.log_message',
                    't_project_log.update_user_id',
                    't_project_log.regist_datetime',
                    't_project.project_id'
                )
                ->where('t_project.project_id', $projectId);
        if (is_array($identifyCode) && count($identifyCode)) {
            $query->whereIn('identifying_code', $identifyCode);
        }

        return $query->orderBy('t_project_log.regist_datetime', 'desc')->get()->take(5);
    }

    /**
     * Get Project by companyId & userId
     * TODO: S.G020.1
     *
     * @param $companyId
     * @return mixed
     */

    public function getProjectByUser($companyId)
    {
    }
}
