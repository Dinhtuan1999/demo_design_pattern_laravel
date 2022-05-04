<?php

namespace App\Services;

use App\Models\Project;
use App\Repositories\AttachmentFileRepository;
use App\Repositories\CheckListRepository;
use App\Repositories\CommentRepository;
use App\Repositories\ProjectOwnedAttributeRepository;
use App\Repositories\ProjectParticipantRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\TaskGroupRepository;
use App\Repositories\TaskRepository;
use Illuminate\Support\Facades\DB;

class CopyProjectService
{
    public function __construct(
        ProjectRepository $projectRepo,
        ProjectOwnedAttributeRepository $projectOwnAttRepo,
        TaskRepository $taskRepo,
        TaskGroupRepository $taskGroupRepo,
        CheckListRepository $checklistRepo,
        ProjectParticipantRepository $projectParticipantRepo,
        AttachmentFileRepository $attachmentFileRepo,
        CommentRepository $commentRepo
    ) {
        $this->projectRepo       = $projectRepo;
        $this->projectOwnAttRepo = $projectOwnAttRepo;
        $this->taskRepo          = $taskRepo;
        $this->taskGroupRepo     = $taskGroupRepo;
        $this->checklistRepo     = $checklistRepo;
        $this->projectParticipantRepo = $projectParticipantRepo;
        $this->attachmentFileRepo = $attachmentFileRepo;
        $this->commentRepo       = $commentRepo;
    }

    public function copyProject($projectId, $newProjectName, $options = [], $createTempFlg, $currentUserId)
    {
        $result = [];
        try {
            DB::beginTransaction();
            $rootProject  = $this->projectRepo->getByCol(
                'project_id',
                $projectId,
                [ Project::PROJECT_ATTRIBUTES, Project::TASK_GROUPS, Project::TASKS ]
            );
            $newProjectId = AppService::generateUUID();
            $now          = date('Y-m-d H:i:s');
            $copyAttributes = [];
            $copyTaskGroups = [];
            $copyTasks      = [];
            $copyChecklists = [];
            $copyFiles      = [];
            $copyComments   = [];

            $newProject                    = [];
            $newProject['project_id']      = $newProjectId;
            $newProject['company_id']      = $rootProject->company_id;
            $newProject['project_name']    = $newProjectName;
            $newProject['project_status']  = config('apps.task.status_key.in_progress');
            $newProject['create_datetime'] = $now;
            $newProject['create_user_id']  = $currentUserId;
            $newProject['update_datetime'] = $now;
            $newProject['update_user_id']  = $currentUserId;
            if (!empty($options['basic_info']) && $options['basic_info'] === 'on') {
                $newProject['project_overview']     = $rootProject->project_overview;
                $newProject['template_flg']         = $createTempFlg === 1
                    ? config('apps.project.template_flg.on') : config('apps.project.template_flg.off');
                $newProject['scheduled_start_date'] = $rootProject->scheduled_start_date;
                $newProject['scheduled_end_date']   = $rootProject->scheduled_end_date;
                $newProject['actual_start_date']    = $rootProject->actual_start_date;
                $newProject['actual_end_date']      = $rootProject->actual_end_date;
                $newProject['develop_scale']        = $rootProject->develop_scale;
                $newProject['user_num']             = $rootProject->user_num;
            }
            if (!empty($options['target_company_search']) && $options['target_company_search'] === 'on') {
                $newProject['company_search_target_flg'] = $rootProject->company_search_target_flg;
                $newProject['company_search_keyword']    = $rootProject->company_search_keyword;
            }
            if (!empty($options['template_open_flg']) && $options['template_open_flg'] === 'on') {
                $newProject['template_open_flg']    = config('apps.project.template_open_flg.on');
            }
            $this->projectRepo->store($newProject);

            if (!empty($options['project_attribute']) && $options['project_attribute'] === 'on') {
                $attributes = $rootProject->project_owned_attributes;
                if (count($attributes)) {
                    foreach ($attributes as $attribute) {
                        $newAtt                         = [];
                        $newAtt['project_id']           = $newProjectId;
                        $newAtt['project_attribute_id'] = $attribute->project_attribute_id;
                        $newAtt['others_message']       = $attribute->others_message;
                        $newAtt['create_datetime']      = $now;
                        $newAtt['create_user_id']       = $currentUserId;
                        $newAtt['update_datetime']      = $now;
                        $newAtt['update_user_id']       = $currentUserId;
                        $copyAttributes[]               = $newAtt;
                    }
                    $this->projectOwnAttRepo->insertMultiRecord($copyAttributes);
                }
            }

            if (!empty($options['task_group']) && $options['task_group'] === 'on') {
                $taskGroups = $rootProject->task_groups;
                if (count($taskGroups)) {
                    foreach ($taskGroups as $group) {
                        if ($group->delete_flg == config('apps.general.not_deleted')) {
                            $newTaskGroup                    = [];
                            $newTaskGroupId                  = AppService::generateUUID();
                            $newTaskGroup['task_group_id']   = $newTaskGroupId;
                            $newTaskGroup['project_id']      = $newProjectId;
                            $newTaskGroup['group_name']      = $group->group_name;
                            $newTaskGroup['disp_color_id']   = $group->disp_color_id;
                            $newTaskGroup['create_datetime'] = $now;
                            $newTaskGroup['create_user_id']  = $currentUserId;
                            $newTaskGroup['update_datetime'] = $now;
                            $newTaskGroup['update_user_id']  = $currentUserId;

                            $copyTaskGroups[] = $newTaskGroup;

                            $tasks = $group->tasks()->whereNull('parent_task_id')->get();
                            if (count($tasks)) {
                                foreach ($tasks as $task) {
                                    if ($task->delete_flg == config('apps.general.not_deleted')) {
                                        $newTask = $this->copyTask($task, $createTempFlg, $newTaskGroupId, $newProjectId, $now, $currentUserId, null);
                                        $copyTasks[] = $newTask;

                                        $CKTask = $task->check_lists;
                                        if (count($CKTask)) {
                                            foreach ($CKTask as $checklist) {
                                                if ($checklist->delete_flg == config('apps.general.not_deleted')) {
                                                    $newCK = $this->copyCheckList($newTask['task_id'], $createTempFlg, $checklist, $now, $currentUserId);
                                                    $copyChecklists[] = $newCK;
                                                }
                                            }
                                        }
                                        if ($createTempFlg == config('apps.project.template_flg.off')) {
                                            $taskFiles = $task->attachment_files;
                                            $taskComments = $task->comments;
                                            if (count($taskFiles) > 0) {
                                                foreach ($taskFiles as $taskFile) {
                                                    if ($taskFile->delete_flg == config('apps.general.not_deleted')) {
                                                        $newFile = $this->copyAttachmentFile($newTask['task_id'], $taskFile, $now, $currentUserId);
                                                        $copyFiles[] = $newFile;
                                                    }
                                                }
                                            }

                                            if (count($taskComments) > 0) {
                                                foreach ($taskComments as $taskComment) {
                                                    if ($taskComment->delete_flg == config('apps.general.not_deleted')) {
                                                        $newComment = $this->copyComment($newTask['task_id'], $taskComment, $now, $currentUserId);
                                                        $copyComments[] = $newComment;
                                                    }
                                                }
                                            }
                                        }

                                        $subTasks = $task->sub_tasks;
                                        if (count($subTasks)) {
                                            foreach ($subTasks as $subTask) {
                                                if ($subTask->delete_flg == config('apps.general.not_deleted')) {
                                                    $newSubTask = $this->copyTask($subTask, $createTempFlg, $newTaskGroupId, $newProjectId, $now, $currentUserId, $newTask['task_id']);
                                                    $copyTasks[] = $newSubTask;

                                                    $CKSubTask = $subTask->check_lists;
                                                    if (count($CKSubTask)) {
                                                        foreach ($CKSubTask as $checklist) {
                                                            if ($checklist->delete_flg == config('apps.general.not_deleted')) {
                                                                $newSubTaskCK = $this->copyCheckList($newSubTask['task_id'], $createTempFlg, $checklist, $now, $currentUserId);
                                                                $copyChecklists[] = $newSubTaskCK;
                                                            }
                                                        }
                                                    }

                                                    if ($createTempFlg == config('apps.project.template_flg.off')) {
                                                        $subTaskFiles = $subTask->attachment_files;
                                                        $subTaskComments = $subTask->comments;

                                                        if (count($subTaskFiles) > 0) {
                                                            foreach ($subTaskFiles as $subTaskFile) {
                                                                if ($subTaskFile->delete_flg == config('apps.general.not_deleted')) {
                                                                    $newSubTaskFile = $this->copyAttachmentFile($newSubTask['task_id'], $subTaskFile, $now, $currentUserId);
                                                                    $copyFiles[] = $newSubTaskFile;
                                                                }
                                                            }
                                                        }

                                                        if (count($subTaskComments) > 0) {
                                                            foreach ($subTaskComments as $subTaskComment) {
                                                                if ($subTaskComment->delete_flg == config('apps.general.not_deleted')) {
                                                                    $newSubTaskComment = $this->copyComment($newSubTask['task_id'], $subTaskComment, $now, $currentUserId);
                                                                    $copyComments[] = $newSubTaskComment;
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $this->taskGroupRepo->insertMultiRecord($copyTaskGroups);
                if (!empty($options['task']) && $options['task'] === 'on') {
                    $this->taskRepo->insertMultiRecord($copyTasks);
                    $this->checklistRepo->insertMultiRecord($copyChecklists);
                    if ($createTempFlg == config('apps.project.template_flg.off')) {
                        $this->attachmentFileRepo->insertMultiRecord($copyFiles);
                        $this->commentRepo->insertMultiRecord($copyComments);
                    }
                }
            }
            // add user current is member
            $projectParticipant = $this->projectParticipantRepo->getByCols(['project_id' => $newProject['project_id'], 'user_id' => $currentUserId]);
            if (!$projectParticipant) {
                $newPU = [];
                $newPU['user_id']        = $currentUserId;
                $newPU['project_id']     = $newProject['project_id'];
                $newPU['role_id']        = null;
                $newPU['update_user_id'] = $currentUserId;
                $newPU['create_user_id'] = $currentUserId;
                $newPU['create_datetime'] = $now;
                $newPU['update_datetime'] = $now;
                $this->projectParticipantRepo->store($newPU);
            }

            $rootProject->object_copy_num = (empty($rootProject->object_copy_num) ? 0 : +$rootProject->object_copy_num) + 1;
            $rootProject->save();

            DB::commit();
            $result['status']  = config('apps.general.success');
            $result['message'] = [ $createTempFlg === 1 ? trans('message.CREATE_TEMPLATE_SUCCESS', ['attribute' => $newProjectName]) : trans('message.SUCCESS') ];
            $result['data']    = [];
            return $result;
        } catch (\Exception $exception) {
            DB::rollBack();
            $result['status']     = config('apps.general.error');
            $result['error_code'] = config('apps.general.error_code');
            $result['message']    = [ trans('message.FAIL') ];
            return $result;
        }
    }

    private $projectRepo;
    private $projectOwnAttRepo;
    private $taskRepo;
    private $taskGroupRepo;
    private $checklistRepo;
    private $projectParticipantRepo;
    private $attachmentFileRepo;
    private $commentRepo;

    private function copyTask($task, $createTempFlg, $newTaskGroupId, $newProjectId, $now, $currentUserId, $parentTaskId)
    {
        $newTask                        = [];
        $newTask['task_id']             = AppService::generateUUID();
        $newTask['task_group_id']       = $newTaskGroupId;
        $newTask['project_id']          = $newProjectId;
        $newTask['task_name']           = $task->task_name;
        $newTask['priority_id']         = $task->priority_id;
        $newTask['disclosure_range_id'] = $task->disclosure_range_id;
        $newTask['task_status_id']      = $createTempFlg == config('apps.project.template_flg.on')
            ? config('apps.task.status_key.not_started') : $task->task_status_id;
        $newTask['user_id']             = $createTempFlg == config('apps.project.template_flg.on') ? null : $task->user_id;
        $newTask['task_memo']           = $task->task_memo;
        $newTask['start_plan_date']     = $task->start_plan_date;
        $newTask['end_plan_date']       = $task->end_plan_date;
        $newTask['start_date']          = $createTempFlg == config('apps.project.template_flg.on') ? null : $task->start_date;
        $newTask['end_date']            = $createTempFlg == config('apps.project.template_flg.on') ? null : $task->end_date;
        $newTask['parent_task_id']      = $parentTaskId;
        $newTask['create_datetime']     = $now;
        $newTask['create_user_id']      = $currentUserId;
        $newTask['update_datetime']     = $now;
        $newTask['update_user_id']      = $currentUserId;

        return $newTask;
    }

    private function copyCheckList($newTaskId, $createTempFlg, $checklist, $now, $currentUserId)
    {
        $newCK                    = [];
        $newCK['check_list_id']   = AppService::generateUUID();
        $newCK['task_id']         = $newTaskId;
        $newCK['check_name']      = $checklist->check_name;
        $newCK['complete_flg']    = $createTempFlg == config('apps.project.template_flg.on')
            ? config('apps.checklist.not_completed') : $checklist->complete_flg;
        $newCK['create_datetime'] = $now;
        $newCK['create_user_id']  = $currentUserId;
        $newCK['update_datetime'] = $now;
        $newCK['update_user_id']  = $currentUserId;

        return $newCK;
    }

    private function copyAttachmentFile($newTaskId, $taskFile, $now, $currentUserId)
    {
        $newFile = [];
        $newFile['attachment_file_id'] = AppService::generateUUID();
        $newFile['task_id'] = $newTaskId;
        $newFile['attachment_file_name'] = $taskFile->attachment_file_name;
        $newFile['attachment_file_path'] = $taskFile->attachment_file_path;
        $newFile['file_size'] = $taskFile->file_size;
        $newFile['create_datetime'] = $now;
        $newFile['create_user_id'] = $currentUserId;
        $newFile['update_datetime'] = $now;
        $newFile['update_user_id'] = $currentUserId;

        return $newFile;
    }

    private function copyComment($newTaskId, $taskComment, $now, $currentUserId)
    {
        $newComment = [];
        $newComment['comment_id'] = AppService::generateUUID();
        $newComment['task_id'] = $newTaskId;
        $newComment['contributor_id'] = $taskComment->contributor_id;
        $newComment['comment'] = $taskComment->comment;
        $newComment['attachment_file_id'] = $taskComment->attachment_file_id;
        $newComment['create_datetime'] = $now;
        $newComment['create_user_id'] = $currentUserId;
        $newComment['update_datetime'] = $now;
        $newComment['update_user_id'] = $currentUserId;

        return $newComment;
    }
}
