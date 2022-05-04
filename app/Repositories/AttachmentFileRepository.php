<?php

namespace App\Repositories;

use App\Models\AttachmentFile;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttachmentFileRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(AttachmentFile::class);
        $this->fields = AttachmentFile::FIELDS;
    }

    public function restoreAttachmentFileFromTrash($attachment_file_id)
    {
        $table = 't_attachment_file';
        try {
            if ($attachment_file_id) {
                // check file is deleted
                $file = $this->getById($attachment_file_id);
                if ($file->delete_flg != config('apps.general.is_deleted')) {
                    return [
                        'status' => false,
                        'error' => ['table_not_found' => $table]
                    ];
                }

                $task = $file->{AttachmentFile::TASK}()->first();

                // check project parent has deleted
                $project = $task->{Task::PROJECT}()->first();
                if ($project->delete_flg == config('apps.general.is_deleted')) {
                    return [
                        'status' => false,
                        'error' => ['parent_has_deleted' => 'project']
                    ];
                }
                // check task_group parent has deleted
                $task_group = $task->{Task::TASK_GROUP}()->first();
                if ($task_group->delete_flg == config('apps.general.is_deleted')) {
                    return [
                        'status' => false,
                        'error' => ['parent_has_deleted' => 'task group']
                    ];
                }
                // check task parent parent has deleted
                $task_parent = $task->{Task::TASK_PARENT}()->first();
                if ($task_parent->del_flg == config('apps.general.is_deleted')) {
                    return [
                        'status' => false,
                        'error' => ['parent_has_deleted' => 'task parent']
                    ];
                }
                // check task has deleted
                if ($task->del_flg == config('apps.general.is_deleted')) {
                    return [
                        'status' => false,
                        'error' => ['parent_has_deleted' => 'task']
                    ];
                }

                $file->update(['delete_flg' => config('apps.general.not_deleted')]);
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

    public function baseQuery($projectId, $filter)
    {
        $model = $this->getModel();

        // Filter
        $model = $model::select(
            't_attachment_file.attachment_file_id',
            't_attachment_file.attachment_file_name',
            't_attachment_file.file_size',
            't_attachment_file.create_datetime',
            't_task.task_id',
            't_task.task_name',
            't_task_group.group_name',
            't_user.user_id',
            't_user.disp_name'
        )
            ->join('t_task', 't_attachment_file.task_id', '=', 't_task.task_id')
            ->join('t_task_group', 't_task.task_group_id', '=', 't_task_group.task_group_id')
            ->join('t_user', 't_user.user_id', '=', 't_attachment_file.create_user_id');

        $model = $model->where('t_attachment_file.delete_flg', config('apps.general.not_deleted'));
        $model = $model->where('t_task.delete_flg', config('apps.general.not_deleted'));
        $model = $model->where('t_task_group.delete_flg', config('apps.general.not_deleted'));

        $model = $model->where('t_task.project_id', $projectId);

        if ($filter['author'] != null && count($filter['author']) > 0) {
            $model = $model->whereIn('t_attachment_file.create_user_id', $filter['author']);
        }

        if ($filter['group'] != null && count($filter['group']) > 0) {
            $model = $model->whereIn('t_task_group.task_group_id', $filter['group']);
        }

        $model = $model->orderBy('t_attachment_file.create_datetime', 'DESC');

        return $model;
    }

    public function getListFile($projectId, $filter)
    {
        $model = null;

        if ($filter['search'] != null) {
            $queryByFileName = $this->queryByFileName($projectId, $filter);
            $queryByFileSize = $this->queryByFileSize($projectId, $filter);
            $queryByGroupName = $this->queryByGroupName($projectId, $filter);
            $queryByTaskName = $this->queryByTaskName($projectId, $filter);
            $queryByAuthor = $this->queryByAuthor($projectId, $filter);

            $model = $queryByFileName;
            $model = $model->union($queryByFileSize);
            $model = $model->union($queryByGroupName);
            $model = $model->union($queryByTaskName);
            $model = $model->union($queryByAuthor);
        } else {
            $model = $this->baseQuery($projectId, $filter);
        }

        $model = $model->paginate(config('apps.notification.record_per_page'));

        return $model;
    }

    public function queryByFileName($projectId, $filter)
    {
        $model = $this->baseQuery($projectId, $filter);

        if ($filter['search'] != null) {
            $model = $model->where('t_attachment_file.attachment_file_name', 'LIKE', '%'.$filter['search'].'%');
        }

        return $model;
    }

    public function queryByFileSize($projectId, $filter)
    {
        $model = $this->baseQuery($projectId, $filter);

        if ($filter['search'] != null) {
            $model = $model->where('t_attachment_file.file_size', 'LIKE', '%'.$filter['search'].'%');
        }

        return $model;
    }

    public function queryByGroupName($projectId, $filter)
    {
        $model = $this->baseQuery($projectId, $filter);

        if ($filter['search'] != null) {
            $model = $model->where('t_task_group.group_name', 'LIKE', '%'.$filter['search'].'%');
        }

        return $model;
    }

    public function queryByTaskName($projectId, $filter)
    {
        $model = $this->baseQuery($projectId, $filter);

        if ($filter['search'] != null) {
            $model = $model->where('t_task.task_name', 'LIKE', '%'.$filter['search'].'%');
        }

        return $model;
    }

    public function queryByAuthor($projectId, $filter)
    {
        $model = $this->baseQuery($projectId, $filter);

        if ($filter['search'] != null) {
            $model = $model->where('t_user.disp_name', 'LIKE', '%'.$filter['search'].'%');
        }

        return $model;
    }

    /**
     * Get all authors file of project
     *
     * @param  string $projectId
     * @return mixed
     */
    public function getAuthors($projectId)
    {
        DB::statement("SET sql_mode = false");

        $authors = $this->getInstance()->query()
            ->join('t_task', 't_attachment_file.task_id', '=', 't_task.task_id')
            ->join('t_task_group', 't_task.task_group_id', '=', 't_task_group.task_group_id')
            ->join('t_user', 't_user.user_id', '=', 't_attachment_file.create_user_id')
            ->join('t_project', 't_task.project_id', '=', 't_project.project_id')
            ->where('t_attachment_file.delete_flg', config('apps.general.not_deleted'))
            ->where('t_task.delete_flg', config('apps.general.not_deleted'))
            ->where('t_task_group.delete_flg', config('apps.general.not_deleted'))
            ->where('t_project.delete_flg', config('apps.general.not_deleted'))
            ->where('t_task.project_id', $projectId)
            ->select([
                't_user.user_id',
                't_user.disp_name',
                't_user.icon_image_path',
            ])
            ->groupBy('t_user.user_id')
            ->get();
        foreach ($authors as $author) {
            $author->icon_image_path = getFullPathFile($author->icon_image_path);
        }
        return $authors;
    }
}
