<?php

namespace App\Services;

use App\Models\AttachmentFile;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\Trash;
use App\Repositories\AttachmentFileRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\TaskGroupRepository;
use App\Repositories\TaskRepository;
use App\Repositories\TrashRepository;
use App\Scopes\DeleteFlgNotDeleteScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\BaseService;

class TrashService extends BaseService
{
    protected $trashRepository;
    protected $taskRepository;
    protected $projectRepository;
    protected $taskGroupRepository;
    protected $attachMentRepository;

    public function __construct(
        TrashRepository $trashRepository,
        TaskRepository $taskRepository,
        ProjectRepository $projectRepository,
        TaskGroupRepository $taskGroupRepository,
        AttachmentFileRepository $attachMentRepository
    ) {
        $this->trashRepository      = $trashRepository;
        $this->taskRepository       = $taskRepository;
        $this->projectRepository    = $projectRepository;
        $this->taskGroupRepository  = $taskGroupRepository;
        $this->attachMentRepository = $attachMentRepository;
    }

    public function getListTrashTask($request)
    {
        $response = [];

        try {
            $data = $this->trashRepository->getListTrashTaskByUserID($request->user_id, $request->page);

            $response['data']       = $data->toArray();
            $response['status']     = config('apps.general.success');
            $response['message']    = trans('message.SUCCESS');
            $response['message_id'] = 'SUCCESS';
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());

            $response = $this->exceptionError();
        }

        return $response;
    }

    public function moveTaskToTrash($userId, $task)
    {
        try {
            DB::beginTransaction();

            $trash = $this->trashRepository->getByCols([
                'task_id'          => $task->task_id,
                'identyfying_code' => config('apps.trash.identyfying_code.task'),
            ]);

            if ($trash) {
                return self::sendError([ trans('message.INF_COM_0012', [ 'attribute' => trans('label.general.t_trash') ]) ], [], config('apps.general.error_code', 600));
            }
            $project = $this->projectRepository->getByCols([
                'project_id' => $task->project_id,
                'delete_flg' => config('apps.general.not_deleted')
            ]);
            $taskGroup = null;
            if (!is_null($task->task_group_id)) {
                $taskGroup = $this->taskGroupRepository->getByCols([
                    'task_group_id' => $task->task_group_id,
                    'delete_flg' => config('apps.general.not_deleted')
                ]);
            }
            if (is_null($project) || (!is_null($task->task_group_id) && is_null($taskGroup))) {
                return self::sendError([ trans('message.ERR_COM_0011', ['attribute' => $task->task_name ])]);
            }

            // data for update task
            $task->delete_flg     = config('apps.general.is_deleted');
            $task->update_user_id = $userId;

            // data to create trash
            $trash = $this->trashRepository->getInstance();

            $trash->trash_id         = AppService::generateUUID();
            $trash->identyfying_code = config('apps.trash.identyfying_code.task');
            $trash->task_id          = $task->task_id;
            $trash->delete_date      = Carbon::now();
            $trash->delete_user_id   = $userId;
            $trash->project_id       = $task->project_id;
            $trash->task_group_id    = $task->task_group_id;

            $trash->create_user_id = $userId;
            $trash->update_user_id = $userId;

            $task->save();
            $trash->save();

            $dataResponse['trash_id'] = $trash->trash_id;

            DB::commit();
            return self::sendResponse([ trans('message.SUCCESS') ], $dataResponse);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return self::sendError([ trans('message.INF_COM_0010') ]);
        }
    }

    public function moveTaskGroupToTrash($userId, $taskGroupId)
    {
        DB::beginTransaction();
        try {
            $taskGroup = $this->taskGroupRepository->getByCol('task_group_id', $taskGroupId);
            if (!$taskGroup) {
                return self::sendError([ trans('message.ERR_COM_0011', [ 'attribute' => trans('validation_attribute.t_task_group') ])], [], config('apps.general.error_code', 600));
            }

            $trash = $this->trashRepository->getByCols([
                'task_group_id'    => $taskGroupId,
                'identyfying_code' => config('apps.trash.identyfying_code.task_group'),
            ]);

            if ($trash) {
                return self::sendError(
                    [ trans('message.ERR_COM_0011', [ 'attribute' =>  $taskGroup->group_name ]) ],
                    [],
                    config('apps.general.error_code', 600)
                );
            }
            $project = $this->projectRepository->getByCols([
                'project_id' => $taskGroup->project_id,
                'delete_flg' => config('apps.general.not_deleted')
            ]);
            if (is_null($project)) {
                return self::sendError([ trans('message.ERR_COM_0011', ['attribute' => $taskGroup->group_name ])]);
            }

            $taskGroup->delete_flg     = config('apps.general.is_deleted');
            $taskGroup->update_user_id = $userId;

            $trash = $this->trashRepository->getInstance();

            $trash->trash_id         = AppService::generateUUID();
            $trash->identyfying_code = config('apps.trash.identyfying_code.task_group');
            $trash->delete_date      = Carbon::now();
            $trash->delete_user_id   = $userId;
            $trash->project_id       = $taskGroup->project_id;
            $trash->task_group_id    = $taskGroupId;

            $trash->create_user_id = $userId;
            $trash->update_user_id = $userId;

            $taskGroup->save();
            $trash->save();
            $dataResponse['trash_id'] = $trash->trash_id;

            DB::commit();
            return self::sendResponse([ trans('message.SUCCESS') ], $dataResponse);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());

            return self::sendError([ trans('message.ERR_EXCEPTION') ]);
        }
    }

    public function permanentlyDelete($trash_id, $userId)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        $response = [];
        $response['status']     = config('apps.general.error');
        $response['message']    = [ trans('message.INF_COM_0010') ];
        $response['error_code'] = config('apps.general.error_code');
        try {
            $trash = $this->trashRepository->getByCol('trash_id', $trash_id, Trash::RELATIONS);
            switch (+$trash->identyfying_code) {
                case config('apps.trash.identyfying_code.project'):
                   return $this->permanentlyDeleteProject($trash, $userId);
                case config('apps.trash.identyfying_code.task_group'):
                   return $this->permanentlyDeleteTaskGroup($trash, $userId);
                case config('apps.trash.identyfying_code.task'):
                    return $this->permanentlyDeleteTask($trash, $userId);
                case config('apps.trash.identyfying_code.file'):
                   return $this->permanentlyDeleteFile($trash, $userId);
                default:
                    return $response;
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response['status']     = config('apps.general.error');
            $response['message']    = [ trans('message.INF_COM_0010') ];
            $response['error_code'] = config('apps.general.error_code');
            return $response;
        }
    }

    public function restoreFromTrash($trash_id, $userId)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        $response               = [];
        $response['status']     = config('apps.general.error');
        $response['error_code'] = config('apps.general.error_code');
        $response['message']    = [ trans('message.FAIL') ];

        try {
            $trash = $this->trashRepository->getByCol('trash_id', $trash_id, Trash::RELATIONS);
            switch (+$trash->identyfying_code) {
                case config('apps.trash.identyfying_code.project'):
                    return $this->restoreProject($trash, $userId);
                case config('apps.trash.identyfying_code.task_group'):
                    return $this->restoreTaskGroup($trash, $userId);
                case config('apps.trash.identyfying_code.task'):
                    return $this->restoreTask($trash, $userId);
                case config('apps.trash.identyfying_code.file'):
                    return $this->restoreFile($trash, $userId);
                default:
                    return $response;
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $response['status']     = config('apps.general.error');
            $response['message']    = [ trans('message.INF_COM_0010') ];
            $response['error_code'] = config('apps.general.error_code');
            return $response;
        }
    }


    public function getListTrash($userId, Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        try {
            $filters                   = [];
            $filters['delete_user_id'] = $userId;
            if (!empty($request->input('identyfying_code'))) {
                $filters['identyfying_code'] = $request->input([ 'identyfying_code' ]);
            }
            $trashes = $this->trashRepository->get(
                $filters,
                config('apps.general.comments.per_page'),
                [ 'by' => 'update_datetime', 'type' => 'desc' ],
                Trash::RELATIONS
            );
            $data    = [];
            if (count($trashes)) {
                $data = $this->trashRepository->formatAllRecord($trashes);
                $data = $data->toArray();
                foreach ($data['data'] as $key => $item) {
                    unset($item['attachment_file'], $item['task_group'], $item['task'], $item['project']);
                    $data['data'][$key] = $item;
                }
            }
            return $data;
        } catch (\Exception $exception) {
            return [];
        }
    }


    public function restoreFile(Trash $trash, $userId)
    {
        $result = [];
        $file   = $trash->attachment_file;
        $task   = $trash->task;
        if (+$task->delete_flg === config('apps.general.is_deleted')) {
            $result['status']     = config('apps.general.error');
            $result['message']    = [ trans('message.INF_C030_0001', [ 'object' =>  $file->attachment_file_name, 'parent_object' => $task->task_name ]) ];
            $result['error_code'] = config('apps.general.error_code');
            return $result;
        }
        $taskGroup = $trash->task_group;
        if (+$taskGroup->delete_flg === config('apps.general.is_deleted')) {
            $result['status']     = config('apps.general.error');
            $result['message']    = [ trans('message.INF_C030_0001', [ 'object' =>  $file->attachment_file_name, 'parent_object' => $taskGroup->group_name ]) ];
            $result['error_code'] = config('apps.general.error_code');
            return $result;
        }
        $project = $trash->project;
        if (+$project->delete_flg === config('apps.general.is_deleted')) {
            $result['status']     = config('apps.general.error');
            $result['message']    = [ trans('message.INF_C030_0001', [ 'object' =>  $file->attachment_file_name, 'parent_object' => $project->project_name ]) ];
            $result['error_code'] = config('apps.general.error_code');
            return $result;
        }

        try {
            DB::beginTransaction();
            $file->delete_flg     = config('apps.general.not_deleted');
            $file->update_user_id = $userId;
            $file->save();

            $trash->delete();

            DB::commit();
            $result['status']  = config('apps.general.success');
            $result['message'] = [ trans('message.SUCCESS') ];
            $result['data']    = [];
            return $result;
        } catch (\Exception $exception) {
            DB::rollBack();
            $result['status']     = config('apps.general.error');
            $result['message']    = [ trans('message.FAIL') ];
            $result['error_code'] = config('apps.general.error_code');
            return $result;
        }
    }

    public function restoreTask(Trash $trash, $userId)
    {
        $result  = [];
        $task = $trash->task;
        $project = $trash->project;
        if (+$project->delete_flg === config('apps.general.is_deleted')) {
            $result['status']     = config('apps.general.error');
            $result['message']    = [ trans('message.INF_C030_0001', [ 'object' =>  $task->task_name, 'parent_object' => $project->project_name ]) ];
            $result['error_code'] = config('apps.general.error_code');
            return $result;
        }
        $taskGroup = $trash->task_group;
        if (+$taskGroup->delete_flg === config('apps.general.is_deleted')) {
            $result['status']     = config('apps.general.error');
            $result['message']    = [ trans('message.INF_C030_0001', [ 'object' =>  $task->task_name, 'parent_object' => $taskGroup->group_name ]) ];
            $result['error_code'] = config('apps.general.error_code');
            return $result;
        }
        if (!empty($task->parent_task_id)) {
            $parentTask = $this->taskRepository->getByCol('task_id', $task->parent_task_id);
            if ($parentTask->delete_flg === config('apps.general.is_deleted')) {
                $result['status']     = config('apps.general.error');
                $result['message']    = [ trans('validation.parent_is_deleted', [ 'object' => trans('label.task.task') ]) ];
                $result['error_code'] = config('apps.general.error_code');
                return $result;
            }
        }
        try {
            DB::beginTransaction();
            $task->delete_flg     = config('apps.general.not_deleted');
            $task->update_user_id = $userId;
            $task->save();

            $trash->delete();
            DB::commit();
            $result['status']  = config('apps.general.success');
            $result['message'] = [ trans('message.SUCCESS') ];
            $result['data']    = [];
            return $result;
        } catch (\Exception $exception) {
            DB::rollBack();
            $result['status']     = config('apps.general.error');
            $result['message']    = [ trans('message.FAIL') ];
            $result['error_code'] = config('apps.general.error_code');
            return $result;
        }
    }

    public function restoreTaskGroup(Trash $trash, $userId)
    {
        $result  = [];
        $taskGroup = $trash->task_group;
        $project = $trash->project;
        if (+$project->delete_flg === config('apps.general.is_deleted')) {
            $result['status']     = config('apps.general.error');
            $result['message']    = [ trans('message.INF_C030_0001', [ 'object' =>  $taskGroup->group_name, 'parent_object' => $project->project_name ]) ];
            $result['error_code'] = config('apps.general.error_code');
            return $result;
        }
        try {
            DB::beginTransaction();
            $taskGroup->delete_flg     = config('apps.general.not_deleted');
            $taskGroup->update_user_id = $userId;
            $taskGroup->save();

            $trash->delete();
            DB::commit();
            $result['status']  = config('apps.general.success');
            $result['message'] = [ trans('message.SUCCESS') ];
            $result['data']    = [];
            return $result;
        } catch (\Exception $exception) {
            DB::rollBack();
            $result['status']     = config('apps.general.error');
            $result['message']    = [ trans('message.FAIL') ];
            $result['error_code'] = config('apps.general.error_code');
            return $result;
        }
    }

    public function restoreProject(Trash $trash, $userId)
    {
        $result  = [];
        $project = $trash->project;
        try {
            DB::beginTransaction();
            $project->delete_flg     = config('apps.general.not_deleted');
            $project->update_user_id = $userId;
            $project->save();

            $trash->delete();
            DB::commit();
            $result['status']  = config('apps.general.success');
            $result['message'] = [ trans('message.SUCCESS') ];
            $result['data']    = [];
            return $result;
        } catch (\Exception $exception) {
            DB::rollBack();
            $result['status']     = config('apps.general.error');
            $result['message']    = [ trans('message.FAIL') ];
            $result['error_code'] = config('apps.general.error_code');
            return $result;
        }
    }

    public function permanentlyDeleteProject(Trash $trash, $userId)
    {
        $result = [];
        $project = $this->projectRepository->getModel()::query()
            ->where('project_id', $trash->project_id)
            ->with([ Project::TASKS, Project::PROJECT_OWNED_ATTRIBUTES, Project::PROJECT_PARTICIPANTS,
                Project::TASK_GROUPS ])
            ->withoutGlobalScope(new DeleteFlgNotDeleteScope())
            ->first();
        try {
            DB::beginTransaction();
            $update = [
                'delete_flg' => config('apps.general.is_deleted'),
                'update_user_id' => $userId
            ];

            $project->project_owned_attributes()->update($update);
            $project->project_participants()->update($update);
            $project->task_groups()->update($update);
            $taskIds = $project->tasks()->pluck('task_id')->toArray();
            if (count($taskIds)) {
                $this->attachMentRepository->getModel()::query()->whereIn('task_id', $taskIds)->update($update);
            }
            $project->tasks()->update($update);
            $project->update($update);
            $trash->delete();
            DB::commit();

            $result['status']  = config('apps.general.success');
            $result['message'] = [ trans('message.SUCCESS') ];
            $result['data']    = [];
            return $result;
        } catch (\Exception $exception) {
            DB::rollBack();
            $result['status']     = config('apps.general.error');
            $result['message']    = [ trans('message.FAIL') ];
            $result['error_code'] = config('apps.general.error_code');
            return $result;
        }
    }

    public function permanentlyDeleteTaskGroup(Trash $trash, $userId)
    {
        $result = [];
        $taskGroup = $this->taskGroupRepository->getByCol(
            'task_group_id',
            $trash->task_group_id,
            [TaskGroup::TASKS]
        );
        try {
            DB::beginTransaction();
            $update = [
                'delete_flg' => config('apps.general.is_deleted'),
                'update_user_id' => $userId
            ];
            $taskIds = $taskGroup->tasks()->pluck('task_id')->toArray();
            if (count($taskIds)) {
                $this->attachMentRepository->getModel()::query()->whereIn('task_id', $taskIds)->update($update);
            }
            $taskGroup->tasks()->update($update);
            $taskGroup->update($update);
            $trash->delete();
            DB::commit();

            $result['status']  = config('apps.general.success');
            $result['message'] = [ trans('message.SUCCESS') ];
            $result['data']    = [];
            return $result;
        } catch (\Exception $exception) {
            DB::rollBack();
            $result['status']     = config('apps.general.error');
            $result['message']    = [ trans('message.FAIL') ];
            $result['error_code'] = config('apps.general.error_code');
            return $result;
        }
    }

    public function permanentlyDeleteTask(Trash $trash, $userId)
    {
        $result = [];
        $task = $this->taskRepository->getByCol('task_id', $trash->task_id, [Task::SUB_TASKS, Task::ATTACHMENT_FILES]);
        try {
            DB::beginTransaction();
            $update = [
                'delete_flg' => config('apps.general.is_deleted'),
                'update_user_id' => $userId
            ];
            $subTaskIds = $task->sub_tasks()->pluck('task_id')->toArray();
            if (count($subTaskIds)) {
                $this->attachMentRepository->getModel()::query()->whereIn('task_id', $subTaskIds)->update($update);
            }
            $task->attachment_files()->update($update);
            $task->update($update);
            $trash->delete();
            DB::commit();

            $result['status']  = config('apps.general.success');
            $result['message'] = [ trans('message.SUCCESS') ];
            $result['data']    = [];
            return $result;
        } catch (\Exception $exception) {
            DB::rollBack();
            $result['status']     = config('apps.general.error');
            $result['message']    = [ trans('message.FAIL') ];
            $result['error_code'] = config('apps.general.error_code');
            return $result;
        }
    }

    public function permanentlyDeleteFile(Trash $trash, $userId)
    {
        $result = [];
        $file = $this->attachMentRepository->getByCol('attachment_file_id', $trash->attachment_file_id);
        try {
            DB::beginTransaction();
            $update = [
                'delete_flg' => config('apps.general.is_deleted'),
                'update_user_id' => $userId
            ];
            $file->update($update);
            $trash->delete();
            DB::commit();
            $result['status']  = config('apps.general.success');
            $result['message'] = [ trans('message.SUCCESS') ];
            $result['data']    = [];
            return $result;
        } catch (\Exception $exception) {
            DB::rollBack();
            $result['status']     = config('apps.general.error');
            $result['message']    = [ trans('message.FAIL') ];
            $result['error_code'] = config('apps.general.error_code');
            return $result;
        }
    }
}
