<?php

namespace App\Repositories;

use App\Models\Trash;
use App\Models\User;
use Carbon\Carbon;

class TrashRepository extends Repository
{
    public function __construct(
        ProjectRepository $projectRepo,
        TaskGroupRepository $taskGroupRepo,
        TaskRepository $taskRepo,
        AttachmentFileRepository $fileRepo,
        UserRepository $userRepo
    ) {
        parent::__construct(Trash::class);

        $this->projectRepo = $projectRepo;
        $this->taskGroupRepo = $taskGroupRepo;
        $this->taskRepo = $taskRepo;
        $this->fileRepo = $fileRepo;
        $this->userRepo = $userRepo;
    }

    public function getListTrashTaskByUserID($user_id, $page = null, $take = 5)
    {
        $user = $this->userRepo->getByCol('user_id', $user_id, [User::COMPANY_OWNED]);
        if (isset($user->company_owned) && $user->company_owned) {
            $user_company_id = $user->company_owned->company_id;

            $trashes = $this->getModel()::select('t_trash.trash_id', 't_trash.identyfying_code', 't_project.project_name', 't_task_group.group_name', 't_task.task_name', 't_attachment_file.attachment_file_name', 't_trash.delete_date', 't_trash.delete_user_id')
                    ->leftJoin('t_project', 't_trash.project_id', '=', 't_project.project_id')
                    ->leftJoin('t_task_group', 't_trash.task_group_id', '=', 't_task_group.task_group_id')
                    ->leftJoin('t_task', 't_trash.task_id', '=', 't_task.task_id')
                    ->leftJoin('t_attachment_file', 't_trash.attachment_file_id', '=', 't_attachment_file.attachment_file_id')
                    ->join('t_company', 't_project.company_id', '=', 't_company.company_id')
                    ->where([
                        't_company.company_id' => $user_company_id,
                    ])
                    ->whereIn('t_trash.identyfying_code', [
                        config('apps.trash.identyfying_code.project'),
                        config('apps.trash.identyfying_code.task_group'),
                        config('apps.trash.identyfying_code.task'),
                        config('apps.trash.identyfying_code.file')
                    ])
                    ->where(function ($q) {
                        $q->where(['t_project.delete_flg' => config('apps.general.is_deleted')])
                            ->orWhere(['t_task_group.delete_flg' => config('apps.general.is_deleted')])
                            ->orWhere(['t_task.delete_flg' => config('apps.general.is_deleted')])
                            ->orWhere(['t_attachment_file.delete_flg' => config('apps.general.is_deleted')]);
                    })->paginate($take);

            return $trashes->toArray();
        }
        return null;
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
        $record->project_name = !empty($record->project->project_name) ? $record->project->project_name : '';
        $record->task_group_name = !empty($record->task_group->group_name) ? $record->task_group->group_name : '';
        $record->task_name = !empty($record->task->task_name) ? $record->task->task_name : '';
        $record->file_name = !empty($record->attachment_file->attachment_file_name) ? $record->attachment_file->attachment_file_name : '';

        $record->date_delete_at = !empty($record->create_datetime)
            ? Carbon::parse($record->create_datetime)->format('Y/m/d') : '';


        return $record;
    }
}
