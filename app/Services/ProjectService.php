<?php

namespace App\Services;

use App\Models\AttachmentFile;
use App\Models\Project;
use App\Models\User;
use App\Repositories\AttachmentFileRepository;
use App\Repositories\CheckListRepository;
use App\Models\Task;
use App\Repositories\ProjectParticipantRepository;
use App\Repositories\ProjectOwnedAttributeRepository;
use App\Repositories\ProjectAttributeRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\TaskRepository;
use App\Repositories\TrashRepository;
use App\Repositories\UserRepository;
use App\Services\BaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Repositories\CompanyRepository;
use App\Repositories\TaskGroupRepository;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class ProjectService extends BaseService
{
    protected $projectLogService;

    public function __construct(
        ProjectRepository $projectRepo,
        UserRepository $userRepo,
        ProjectParticipantRepository $projectParticipantRepo,
        TaskRepository $taskRepo,
        TrashRepository $trashRepo,
        CompanyRepository $companyRepo,
        ProjectOwnedAttributeRepository $projectOwnedAttributeRepo,
        ProjectAttributeRepository $projectAttributeRepo,
        TaskGroupRepository $taskGroupRepo,
        CheckListRepository $checkListRepo,
        AttachmentFileRepository $attachmentFileRepo,
        ProjectLogService $projectLogService,
        EmailService      $emailService
    ) {
        $this->projectRepo            = $projectRepo;
        $this->userRepo               = $userRepo;
        $this->projectParticipantRepo = $projectParticipantRepo;
        $this->taskRepo               = $taskRepo;
        $this->trashRepo              = $trashRepo;
        $this->companyRepo            = $companyRepo;
        $this->projectOwnedAttributeRepo  = $projectOwnedAttributeRepo;
        $this->projectAttributeRepo  = $projectAttributeRepo;
        $this->taskGroupRepo = $taskGroupRepo;
        $this->checkListRepo = $checkListRepo;
        $this->attachmentFileRepo = $attachmentFileRepo;
        $this->projectLogService = $projectLogService;
        $this->emailService = $emailService;
    }

    public function getDetailProject($project_id)
    {
        $response = [];

        try {
            $project = $this->projectRepo->getById($project_id);
            if ($project) {
                $data = [
                    'project_name'         => $project->project_name,
                    'project_name_public'  => $project->project_name_public,
                    'project_overview' => $project->project_overview,
                    'project_overview_public' => $project->project_overview_public,
                    'scheduled_start_date' => $project->scheduled_start_date,
                    'scheduled_end_date' => $project->scheduled_end_date,
                    'actual_start_date' => $project->actual_start_date,
                    'actual_end_date' => $project->actual_end_date,
                    'develop_scale' => $project->develop_scale,
                    'user_num' => $project->user_num,
                    'company_search_keyword' => $project->company_search_keyword,
                ];

                $response['data']       = $data;
                $response['status']     = config('apps.general.success');
                $response['message']    = [trans('message.SUCCESS')];
                $response['message_id'] = 'SUCCESS';
            } else {
                $response['status']     = config('apps.general.error');
                $response['error_code'] = config('apps.general.error_code');
                $response['message']    = [trans('validation.object_not_exist', ['attribute' => trans('validation_attribute.project_id')])];
                $response['message_id'] = 'ERR_COM_0011';
            }
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());

            $response = $this->exceptionError();
        }

        return $response;
    }

    public function detailProjectWithUser($projectId)
    {
        try {
            $response = [];
            $project  = $this->projectRepo->getByCol('project_id', $projectId, [Project::USERS]);
            if (empty($project->project_id)) {
                $response['status']     = config('apps.general.error');
                $response['error_code'] = config('apps.general.error_code');
                $response['message']    = [trans('validation.object_not_exist', ['object' => trans('validation_attribute.project_id')])];
                $response['message_id'] = 'ERR_COM_0011';
                return $response;
            }
            $model = $this->projectParticipantRepo->getModel();
            $model = $model::join('t_user', 't_user.user_id', '=', 't_project_participant.user_id');
            $model = $model->where('t_project_participant.project_id', $projectId);
            $members = clone $model;
            $guests =  clone $model;

            $members = $members->with("role_mst")->where([
                't_user.guest_flg' => config('apps.user.not_guest'),
                't_user.delete_flg' => config('apps.general.not_deleted'),
                't_project_participant.delete_flg' => config('apps.general.not_deleted')
            ])->get(['t_user.user_id', 't_user.disp_name', 't_user.mail_address', 't_user.icon_image_path', 't_project_participant.role_id']);
            $members = $members->transform(function ($member) {
                $member->icon_image_path = getFullPathFile($member->icon_image_path);
                return $member;
            });
            $guests = $guests->where([
                't_user.guest_flg' => 1,
                't_user.delete_flg' => config('apps.general.not_deleted'),
                't_project_participant.delete_flg' => config('apps.general.not_deleted')
            ])->get(['t_user.user_id', 't_user.disp_name', 't_user.mail_address']);
            $attributes = $this->projectOwnedAttributeRepo->getModel()::with('project_attribute')->where([
                "project_id" => $project->project_id,
                "delete_flg" => config('apps.general.not_deleted')
            ])->get();
            $project->guests  = $guests;
            $project->members = $members;
            $project->attributes = $attributes;
            $response['data']       = $project;
            $response['status']     = config('apps.general.success');
            $response['message']    = [trans('message.SUCCESS')];
            return $response;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            $response['status']     = config('apps.general.error');
            $response['error_code']     = config('apps.general.error_code');
            $response['message']    = [trans('message.FAIL')];
            return $response;
        }
    }
    // format date
    public function formatDate($date)
    {
        return  !empty($date) ? Carbon::parse($date)->format('Y/m/d') : $date;
    }
    public function create(Request $request, $currentUser)
    {
        $result           = [];
        $result['status'] = config('apps.general.error');

        try {
            DB::beginTransaction();
            $currentUserId           = $currentUser->user_id;
            $data                    = $request->only([
                'project_name', 'project_overview', 'develop_scale', 'user_num',
                'scheduled_start_date', 'scheduled_end_date', 'actual_start_date', 'actual_end_date', 'project_name_public',
                'project_overview_public', 'company_search_keyword',
            ]);
            $data['project_id']      = AppService::generateUUID();
            $data['create_datetime'] = date('Y-m-d');
            $data['update_datetime'] = date('Y-m-d');
            $data['create_user_id']  = $currentUserId;
            $data['update_user_id']  = $currentUserId;
            $data['company_id']      = $currentUser->company_id;
            $data['project_status']  = $this->checkStatusProject($request->actual_start_date, $request->actual_end_date);
            if ($data['project_status'] == config('apps.project.status_key.complete')) {
                // if actual start date null then assigned actual end date
                $data['actual_start_date'] = $request->actual_start_date ? $request->actual_start_date : $request->actual_end_date;
            }
            $data['company_search_target_flg']  = $request->company_search_target_flg;
            $data['template_open_flg']  = $request->template_open_flg ? config('apps.project.template_open_flg.on') : config('apps.project.template_open_flg.off');
            $newProject              = $this->projectRepo->store($data);

            if (!$newProject) {
                return $this->exceptionError();
            }

            $projectId    = $newProject->project_id;
            $projectUsers = [];
            $userIds     = $request->input('user_id', []);
            $roleIds      = $request->input('role_id', []);
            $guestEmails       = $request->input('guest_emails', []);
            $projectAttributeIds       = $request->input('project_attribute_id', []);
            $othersMessage       = $request->input('other_message', []);
            $now          = date('Y-m-d H:i:s');
            $projectAttributes = [];
            $length = count($projectAttributeIds);
            if ($length > 0) {
                for ($i = 0; $i < $length; $i++) {
                    $projectAttribute                    = [];
                    $projectAttribute['project_id']      = $projectId;
                    $projectAttribute['update_user_id']  = $currentUserId;
                    $projectAttribute['project_attribute_id']  = $projectAttributeIds[$i];
                    $projectAttribute['others_message']  = $othersMessage[$i];
                    $projectAttribute['create_datetime'] = $now;
                    $projectAttribute['update_datetime'] = $now;
                    $projectAttribute['create_user_id']  = $currentUserId;
                    $projectAttribute['update_user_id']  = $currentUserId;
                    $projectAttributes[]                 = $projectAttribute;
                }
            }
            $this->projectOwnedAttributeRepo->insertMultiRecord($projectAttributes);

            $length = count($userIds);
            if ($length > 0) {
                for ($i = 0; $i < $length; $i++) {
                    $projectUser                    = [];
                    $projectUser['project_id']      = $projectId;
                    $projectUser['user_id']         = $userIds[$i];
                    $projectUser['role_id']         = $roleIds[$i];
                    $projectUser['create_datetime'] = $now;
                    $projectUser['update_datetime'] = $now;
                    $projectUser['create_user_id']  = $currentUserId;
                    $projectUser['update_user_id']  = $currentUserId;
                    $projectUsers[]                 = $projectUser;
                }
            }
            $length = count($guestEmails);
            $currentUserCompanyId = $currentUser->company_id;
            if ($length > 0) {
                for ($i = 0; $i < $length; $i++) {
                    // check exit guest in in table user
                    $guest = $this->userRepo->getByCols(['mail_address' => $guestEmails[$i], 'company_id' => $currentUserCompanyId]);
                    $guestId='';
                    if ($guest) {
                        if ($guest->delete_flg != config('apps.general.is_deleted')) {
                            // restore account
                            $guest->delete_flg = config('apps.general.not_deleted');
                            $guest->save();
                        }
                        $guestId = $guest->user_id;
                    } else {
                        // create account guest
                        $guest = $this->createGuest($guestEmails[$i], $currentUserCompanyId);
                        if (!$guest) {
                            return $this->exceptionError();
                        }
                        $guestId = $guest->user_id;
                    }
                    $projectUser                    = [];
                    $projectUser['project_id']      = $projectId;
                    $projectUser['user_id']         = $guestId;
                    $projectUser['role_id']         = null;
                    $projectUser['create_datetime'] = $now;
                    $projectUser['update_datetime'] = $now;
                    $projectUser['create_user_id']  = $currentUserId;
                    $projectUser['update_user_id']  = $currentUserId;
                    $projectUsers[]                 = $projectUser;
                }
            }
            $this->projectParticipantRepo->insertMultiRecord($projectUsers);

            // add user current is member
            $projectParticipant = $this->projectParticipantRepo->getByCols(['project_id' => $projectId, 'user_id' => $currentUser->user_id]);
            if (!$projectParticipant) {
                $newPU = [];
                $newPU['user_id']        = $currentUser->user_id;
                $newPU['project_id']     = $projectId;
                $newPU['role_id']        = null;
                $newPU['update_user_id'] = $currentUser->user_id;
                $newPU['create_user_id'] = $currentUser->user_id;
                $newPU['create_datetime'] = $now;
                $newPU['update_datetime'] = $now;
                $this->projectParticipantRepo->store($newPU);
            }
            // get users in project
            $model = $this->projectParticipantRepo->getModel();
            $model = $model::join('t_user', 't_user.user_id', '=', 't_project_participant.user_id');
            $model = $model->where('t_project_participant.project_id', $projectId);
            $users = $model->where([
                't_user.delete_flg' => config('apps.general.not_deleted'),
                't_project_participant.delete_flg' => config('apps.general.not_deleted')
            ])->get(['t_user.user_id', 't_user.disp_name', 't_user.mail_address']);
            $link = route('web.project.group', [ 'id' => $projectId ]);
            // send mail add user to project
            foreach ($users as $user) {
                $this->emailService->sendEmailAddUserToProject($user, $newProject->project_name, $link);
                if ($newProject->project_status == config('apps.project.status_key.complete')) {
                    // send email complete project
                    $this->emailService->sendEmailCompleteProject($user, $newProject->project_name, $link);
                }
            }

            DB::commit();
            $result['status']  = config('apps.general.success');
            $result['message'] = trans('message.SUCCESS');
            $result['data'] = $projectId;
            return $result;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            DB::rollBack();
            return $this->exceptionError();
        }
    }
    /**
    *  check status project
    *
    * @param $actualStartDate, $actualEndDate
    * @return $status
    */
    public function checkStatusProject($actualStartDate, $actualEndDate)
    {
        $status =  config('apps.project.status_key.not_started');
        if ($actualEndDate) {
            $status =  config('apps.project.status_key.complete');
        } elseif ($actualStartDate) {
            $status =  config('apps.project.status_key.in_progress');
        } else {
            $status =  config('apps.project.status_key.not_started');
        }
        return $status;
    }
    public function createGuest($email, $companyId)
    {
        try {
            $user = [];
            $user['user_id'] = AppService::generateUUID();
            $user['mail_address'] = $email;
            $user['company_id'] = $companyId;
            $user['guest_flg'] = 1;
            $user['login_password'] = Hash::make(config('apps.general.default_pass'));
            $model = $this->userRepo->getModel()::create($user);
            // send email
            $this->emailService->sendEmailVerifyRegisterCompany($user['mail_address'], $user['user_id']);
            return $model;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            return $this->exceptionError();
        }
    }
    public function update($projectId, Request $request, $currentUser)
    {
        $result = [];
        try {
            $currentUserId           = $currentUser->user_id;
            $now          = date('Y-m-d H:i:s');
            DB::beginTransaction();
            $oldProject              = $this->projectRepo->getByCol('project_id', $projectId);
            $data                    = $request->only([
                'project_name', 'project_overview', 'develop_scale', 'user_num',
                'scheduled_start_date', 'scheduled_end_date', 'actual_start_date', 'actual_end_date', 'project_name_public',
                'project_overview_public', 'company_search_keyword'
            ]);
            $data['update_datetime'] = $now;
            $data['update_user_id']  = $currentUserId;
            $data['company_search_target_flg']  = $request->company_search_target_flg;
            $data['template_open_flg']  = $request->template_open_flg ? config('apps.project.template_open_flg.on') : config('apps.project.template_open_flg.off');
            $data['project_status']  = $this->checkStatusProject($request->actual_start_date, $request->actual_end_date);
            if ($data['project_status'] == config('apps.project.status_key.complete')) {
                // if actual start date null then assigned actual end date
                $data['actual_start_date'] = $request->actual_start_date ? $request->actual_start_date : $request->actual_end_date;
            }
            $updateProject = $this->projectRepo->update($oldProject, $data);
            if (!$updateProject) {
                $result['status']     = config('apps.general.error');
                $result['error_code'] = config('apps.general.error_code');
                $result['message']    = [trans('message.FAIL')];
                return $result;
            }

            $userIds      = $request->input('user_id', []);
            $roleIds      = $request->input('role_id', []);
            $guestEmails       = $request->input('guest_emails', []);
            $othersMessage       = $request->input('other_message', []);
            $projectAttributeIds       = $request->input('project_attribute_id', []);
            $deleteUsers       = $request->input('delete_users_id', []);
            // get initial users (before adding, removing )
            $initUsers = $this->projectParticipantRepo->getModel()::join('t_user', 't_user.user_id', '=', 't_project_participant.user_id')
                ->where([
                    't_project_participant.project_id' => $projectId,
                    't_project_participant.delete_flg' => config('apps.general.not_deleted')])->pluck('t_user.user_id')->toArray();
            // attribute
            // delete all attribute
            $this->projectOwnedAttributeRepo->getModel()::where(['project_id' => $projectId, 'delete_flg' => config('apps.general.not_deleted')])->update(['delete_flg' => config('apps.general.is_deleted')]);
            $projectAttributes = [];
            $length = count($projectAttributeIds);
            if ($length > 0) {
                for ($i = 0; $i < $length; $i++) {
                    $projectAtt  = $this->projectOwnedAttributeRepo->getByCols(['project_id' => $projectId, 'project_attribute_id' => $projectAttributeIds[$i]]);
                    // check projectAtt exit, only update
                    if ($projectAtt) {
                        $projectAtt->delete_flg = config('apps.general.not_deleted');
                        $projectAtt->update_datetime = $now;
                        $projectAtt->others_message = $othersMessage[$i];
                        $projectAtt->update_user_id = $currentUserId;
                        $projectAtt->save();
                    } else {
                        $projectAttribute                    = [];
                        $projectAttribute['project_id']      = $projectId;
                        $projectAttribute['project_attribute_id']  = $projectAttributeIds[$i];
                        $projectAttribute['others_message']  = $othersMessage[$i];
                        $projectAttribute['create_datetime'] = $now;
                        $projectAttribute['update_datetime'] = $now;
                        $projectAttribute['create_user_id']  = $currentUserId;
                        $projectAttribute['update_user_id']  = $currentUserId;
                        $projectAttributes[]                 = $projectAttribute;
                    }
                }
            }
            $this->projectOwnedAttributeRepo->insertMultiRecord($projectAttributes);

            // member
            // find user have in task
            $usersNotDelete = [];
            foreach ($userIds as $userId) {
                $taskUserIds = $this->taskRepo->getByCols([
                'project_id' => $projectId,
                'delete_flg' => config('apps.general.not_deleted'),
                'user_id' => $userId]);
                if ($taskUserIds) {
                    array_push($usersNotDelete, $taskUserIds->user_id);
                }
            }
            // find project creator
            $projectCreator = $this->projectParticipantRepo->getByCols(['project_id' => $projectId, 'delete_flg' => config('apps.general.not_deleted')]);
            if ($projectCreator && $projectCreator->create_user_id) {
                // don't delete project creator
                array_push($usersNotDelete, $projectCreator->create_user_id);
            }
            // get members
            $models = $this->projectParticipantRepo->getModel()::join('t_user', 't_user.user_id', '=', 't_project_participant.user_id')
            ->where([
             't_project_participant.project_id' => $projectId,
             't_project_participant.delete_flg' => config('apps.general.not_deleted'),
             't_user.delete_flg' => config('apps.general.not_deleted'),
             't_user.guest_flg' => config('apps.general.not_guest')]);
            if (count($usersNotDelete) > 0) {
                // delete member have not task
                $members = clone $models;
                $members->whereNotIn('t_user.user_id', $usersNotDelete)->update(['t_project_participant.delete_flg' => config('apps.general.is_deleted')]);
            } else {
                // delete all user is member
                $members = clone $models;
                $members->update(['t_project_participant.delete_flg' => config('apps.general.is_deleted')]);
            }

            $length = count($userIds);
            if ($length > 0) {
                for ($i = 0; $i < $length; $i++) {
                    $projectParticipant = $this->projectParticipantRepo->getByCols(['project_id' => $projectId, 'user_id' => $userIds[$i]]);
                    // if it exists then update role_id, else create
                    if ($projectParticipant) {
                        $projectParticipant->role_id = $roleIds[$i];
                        $projectParticipant->update_user_id = $currentUser->user_id;
                        $projectParticipant->update_datetime = $now;
                        $projectParticipant->delete_flg = config('apps.general.not_deleted');
                        $projectParticipant->save();
                    } else {
                        $newPU = [];
                        $newPU['user_id']        = $userIds[$i];
                        $newPU['project_id']     = $projectId;
                        $newPU['role_id']        = $roleIds[$i];
                        $newPU['update_user_id'] = $currentUser->user_id;
                        $newPU['create_user_id'] = $currentUser->user_id;
                        $newPU['create_datetime'] = $now;
                        $newPU['update_datetime'] = $now;
                        $this->projectParticipantRepo->store($newPU);
                    }
                }
            }

            // guest
            // delete all user is guest
            $this->projectParticipantRepo->getModel()::join('t_user', 't_user.user_id', '=', 't_project_participant.user_id')
                ->where([
                    't_project_participant.project_id' => $projectId,
                    't_project_participant.delete_flg' => config('apps.general.not_deleted'),
                    't_user.guest_flg' => config('apps.general.is_guest'),
                ])->whereNull('role_id')->update(['t_project_participant.delete_flg' => config('apps.general.is_deleted')]);
            $length = count($guestEmails);
            $currentUserCompanyId = $currentUser->company_id;
            $guestIds = [];
            if ($length > 0) {
                for ($i = 0; $i < $length; $i++) {
                    // check exit guest
                    $guest = $this->userRepo->getByCols(['mail_address' => $guestEmails[$i], 'company_id' => $currentUserCompanyId]);
                    $guestId='';
                    if ($guest) {
                        if ($guest->delete_flg == config('apps.general.is_deleted')) {
                            // restore account
                            $guest->delete_flg = config('apps.general.not_deleted');
                            $guest->save();
                            // send email
                            $this->emailService->sendEmailVerifyRegisterCompany($guest->mail_address, $guest->user_id);
                        }
                        $guestId = $guest->user_id;
                    } else {
                        // create account guest
                        $guest = $this->createGuest($guestEmails[$i], $currentUserCompanyId);
                        if (!$guest) {
                            return $this->exceptionError();
                        }
                        $guestId = $guest->user_id;
                    }
                    array_push($guestIds, $guestId);
                    // check exit guest in project
                    $projectParticipant = $this->projectParticipantRepo->getByCols(['project_id' => $projectId, 'user_id' => $guestId]);
                    // if it is not exists then create
                    if (!$projectParticipant) {
                        $newPU = [];
                        $newPU['user_id']        = $guestId;
                        $newPU['project_id']     = $projectId;
                        $newPU['role_id']        = null;
                        $newPU['update_user_id'] = $currentUser->user_id;
                        $newPU['create_user_id'] = $currentUser->user_id;
                        $this->projectParticipantRepo->store($newPU);
                    } else {
                        $projectParticipant->update_user_id = $currentUser->user_id;
                        $projectParticipant->update_datetime = $now;
                        $projectParticipant->delete_flg = config('apps.general.not_deleted');
                        $projectParticipant->save();
                    }
                }
            }
            $projectUpdateData = [];
            $project = $this->projectRepo->getById($projectId);
            if ($project) {
                $projectUpdateData = [
                    'project_name'         => $project->project_name,
                    'project_overview'     => $project->project_overview,
                    'project_name_public'  => $project->project_name_public,
                    'project_overview_public' => $project->project_overview_public,
                    'scheduled_start_date' => $project->scheduled_start_date,
                    'scheduled_end_date' => $project->scheduled_end_date,
                    'actual_start_date' => $project->actual_start_date,
                    'actual_end_date' => $project->actual_end_date,
                    'develop_scale' => $project->develop_scale,
                    'user_num' => $project->user_num,
                    'company_search_keyword' => $project->company_search_keyword,
                ];
            }
            $project  = $this->projectRepo->getByCol('project_id', $projectId, [Project::USERS]);
            $model = $this->projectParticipantRepo->getModel();
            $model = $model::join('t_user', 't_user.user_id', '=', 't_project_participant.user_id');
            $model = $model->where('t_project_participant.project_id', $projectId);
            $members = clone $model;
            $guests =  clone $model;
            $members = $members->where([
                't_user.guest_flg' => config('apps.user.not_guest'),
                't_project_participant.delete_flg' => config('apps.general.not_deleted')
            ])->get(['t_user.user_id', 't_project_participant.role_id']);
            $guests = $guests->where([
                't_user.guest_flg' => config('apps.user.is_guest'),
                't_project_participant.delete_flg' => config('apps.general.not_deleted')
            ])->get(['t_user.user_id']);
            $projectUpdateData['members'] = $members;
            $projectUpdateData['guests'] = $guests;
            $projectUpdateData['project_id'] = $projectId;
            // send mail to user deleted
            $length = count($deleteUsers);
            if ($length > 0) {
                for ($i = 0; $i < $length; $i++) {
                    $user  = $this->userRepo->getByCols(['user_id' => $deleteUsers[$i], 'delete_flg' => config('apps.general.not_deleted')]);
                    if ($user) {
                        $link = route('pc.login');
                        $this->emailService->sendEmailDeleteUserToProject($user, $project->project_name, $link);
                    }
                }
            }
            // merge member and guest
            $clientUsers = array_merge($userIds, $guestIds);
            foreach ($clientUsers as $userId) {
                $link = route('web.project.group', [ 'id' => $projectId ]);
                // if user does not exist then send mail
                if (!in_array($userId, $initUsers, true)) {
                    $user = $this->userRepo->getByCols(['user_id' => $userId, 'delete_flg' => config('apps.general.not_deleted')]);
                    if ($user) {
                        $this->emailService->sendEmailAddUserToProject($user, $project->project_name, $link);
                    }
                }
                if ($project->project_status == config('apps.project.status_key.complete')) {
                    // send email complete project
                    $user = $this->userRepo->getByCols(['user_id' => $userId, 'delete_flg' => config('apps.general.not_deleted')]);
                    $this->emailService->sendEmailCompleteProject($user, $project->project_name, $link);
                }
            }

            DB::commit();
            $result['status']  = config('apps.general.success');
            $result['message'] = [trans('message.SUCCESS')];
            $result['data'] = $projectUpdateData;

            return $result;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            DB::rollBack();
            return $this->exceptionError();
        }
    }

    public function moveToTrash($projectId, $currentUser)
    {
        $result = [];
        try {
            DB::beginTransaction();
            $newTrash                     = [];
            $newTrash['trash_id']         = AppService::generateUUID();
            $newTrash['identyfying_code'] = config('apps.trash.identyfying_code.project');
            $newTrash['project_id']       = $projectId;
            $newTrash['delete_date']      = date('Y-m-d');
            $newTrash['delete_user_id']   = $currentUser->user_id;
            $newTrash['create_user_id']   = $currentUser->user_id;
            $newTrash['update_user_id']   = $currentUser->user_id;
            $trash = $this->trashRepo->store($newTrash);

            $project             = $this->projectRepo->getByCol('project_id', $projectId);
            $project->delete_flg = config('apps.general.is_deleted');
            $project->save();
            // get members
            $users = $this->projectParticipantRepo->getModel()::join('t_user', 't_user.user_id', '=', 't_project_participant.user_id')
            ->where([
             't_project_participant.project_id' => $projectId,
             't_project_participant.delete_flg' => config('apps.general.not_deleted'),
             't_user.delete_flg' => config('apps.general.not_deleted'),
            ])->get();
            // send email
            foreach ($users as $user) {
                $link = route('pc.login');
                $this->emailService->sendEmailDeleteProject($user, $project->project_name, $link);
            }
            DB::commit();

            $result['status']  = config('apps.general.success');
            $result['message'] = [trans('message.SUCCESS')];
            $result["data"]["trash_id"] = $trash->trash_id;
            return $result;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            return $this->exceptionError();
        }
    }

    public function validateProjectForm($request)
    {
        return Validator::make(
            $request->all(),
            [
                'project_name'              => ['required', 'max:100'],
                'project_overview'          => ['max:500'],
                'develop_scale'             => ['nullable', 'digits_between:1,10'],
                'user_num'                  => ['nullable', 'digits_between:1,10'],
                'scheduled_start_date'         => ['nullable', 'date'],
                'scheduled_end_date'           => ['nullable', 'date', 'after_or_equal:scheduled_start_date'],
                'company_search_target_flg' => ['nullable', Rule::in([config('apps.general.company_search_flg'), config('apps.general.company_search_flg_not')])],
                'project_name_public'       => ['max:100'],
                'project_overview_public'   => ['max:500'],
                'company_search_keyword'    => ['max:500'],
            ],
            [
                'project_name.required'        => trans('message.ERR_COM_0001', ['attribute' => trans('label.project.name')]),
                'project_name.max'             => trans('message.ERR_COM_0002', ['attribute' => trans('label.project.name'), 'max' => '100']),
                'project_overview.max'         => trans('message.ERR_COM_0002', ['attribute' => trans('label.project.overview'), 'max' => '500']),
                'develop_scale.numeric' => trans('message.INF_COM_0005', ['attribute' => trans('label.project.develop_scale')]),
                'develop_scale.max' => trans('message.ERR_COM_0002', ['attribute' => trans('label.project.develop_scale'), 'max' => '10']),
                'user_num.numeric' => trans('message.INF_COM_0005', ['attribute' => trans('label.project.user_num')]),
                'user_num.max' => trans('message.ERR_COM_0002', ['attribute' => trans('label.project.user_num'), 'max' => '10']),
                'scheduled_start_date.date'       => trans('message.INF_COM_0006', ['attribute' => trans('label.project.scheduled_start_date')]),
                'scheduled_end_date.date'         => trans('message.INF_COM_0006', ['attribute' => trans('label.project.scheduled_end_date')]),
                'scheduled_end_date.after_or_equal' => trans('validation.after_or_equal', ['attribute' => trans('label.project.scheduled_end_date'), 'date' => trans('label.project.scheduled_start_date')]),
                'company_search_target_flg.in' => trans('message.INF_COM_0007', ['attribute' => trans('label.project.company_search_target')]),
                'project_name_public.max'      => trans('message.ERR_COM_0002', ['attribute' => trans('label.project.public_name'), 'max' => '100']),
                'project_overview_public.max'  => trans('message.ERR_COM_0002', ['attribute' => trans('label.project.public_overview'), 'max' => '500']),
                'company_search_keyword.max'   => trans('message.ERR_COM_0002', ['attribute' => trans('label.project.company_search_keyword'), 'max' => '500']),
            ]
        );
    }

    public function getListProjectInProgress(Request $request)
    {
        $company_id = $request->input('company_id');
        if (!isset($company_id)) {
            return self::sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
        }

        $company = $this->companyRepo->getByCols(['company_id' => $company_id, 'delete_flg' => config('apps.general.not_deleted')]);
        if (empty($company->company_id)) {
            return self::sendError(
                [trans('message.ERR_COM_0011', ['attribute' => trans('label.role.company_id')])],
                [],
                config('apps.general.error_code', 600)
            );
        }

        $listProjectInProgress = $this->projectRepo->getListProjectInProgress($company->company_id);

        return self::sendResponse(
            [trans('message.SUCCESS')],
            $listProjectInProgress
        );
    }
    public function searchMember($companyId, $keyword = '', $guestFlag, $projectId = null)
    {
        try {
            $query = $this->userRepo->getModel()::where([
                'company_id' => $companyId,
                'guest_flg' => $guestFlag
            ]);

            if ($projectId) {
                $query = $query->whereHas(User::PROJECTS, function ($q) use ($projectId) {
                    $q->where('t_project.project_id', $projectId);
                });
            }

            if (!empty($keyword)) {
                $query->where(function ($query) use ($keyword) {
                    $query->where('disp_name', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('mail_address', 'LIKE', '%' . $keyword . '%');
                });
            }

            $users = $query->get(['user_id', 'disp_name', 'mail_address', 'icon_image_path']);
            $users = $this->userRepo->transformImagePath($users);

            return self::sendResponse(
                [trans('message.SUCCESS')],
                $users
            );
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError([
                [trans('message.ERR_EXCEPTION')]
            ]);
        }
    }
    public function updateStatusProject($userId, $project, $projectStatus, $actualStartDate, $actualEndDate)
    {
        try {
            $project->project_status = $projectStatus;
            $project->update_user_id = $userId;
            $project->actual_start_date = $actualStartDate;
            $project->actual_end_date = $actualEndDate;
            $project->save();
            return self::sendResponse(
                [trans('message.SUCCESS')],
            );
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError([
                [trans('message.ERR_EXCEPTION')]
            ]);
        }
    }

    public function checkRecord($id)
    {
        $response = [
            'status'        => config('apps.general.success'),
            'message'       => [trans('message.SUCCESS')]
        ];

        try {
            $record   = $this->projectRepo->getByCols(
                [
                    'project_id' => $id,
                    'delete_flg' => config('apps.general.not_deleted')
                ]
            );

            if (!$record) {
                $response['status'] = config('apps.general.error');
                $response['message'] = [trans(
                    'message.ERR_COM_0011',
                    ['attribute' => trans('validation_attribute.t_project')]
                )];
                $response['error_code'] = config('apps.general.error_code');
            } else {
                $response['data'] = $record;
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response['status']     = config('apps.general.error');
            $response['message'] = [trans('message.ERR_EXCEPTION')];
            $response['error_code'] = config('apps.general.error_code');
        }

        return $response;
    }

    public function getListLog($projectId, $identifyCode = null)
    {
        try {
            // Check empty projectId is exists
            if (empty($projectId) && !$this->projectRepo->isExists($projectId)) {
                return $this->sendError(trans('message.NOT_COMPLETE'));
            }
            //  Call getListLog function in Project Repository to get Get List Log
            $data =  $this->projectLogService->getLog($projectId, $identifyCode);
            return $this->sendResponse(trans('message.COMPLETE'), $data);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.NOT_COMPLETE')
            );
        }
    }
    public function getProjectByUser($userId)
    {
        try {
            $model = $this->projectParticipantRepo->getModel();
            $model = $model::join('t_project', 't_project.project_id', '=', 't_project_participant.project_id');
            $projects = $model->where([
                't_project.delete_flg' => config('apps.general.not_deleted'),
                't_project_participant.delete_flg' => config('apps.general.not_deleted'),
                't_project_participant.user_id' => $userId,
            ])->where('t_project.project_status', '!=', config('apps.task.status_key.complete'))
                ->get(['t_project.project_name','t_project.project_id']);
            return $this->sendResponse(trans('message.COMPLETE'), $projects);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.NOT_COMPLETE')
            );
        }
    }
    private $projectRepo;
    private $userRepo;
    private $projectParticipantRepo;
    private $taskRepo;
    private $trashRepo;
    private $companyRepo;
    private $projectOwnedAttributeRepo;
    private $projectAttributeRepo;
    private $taskGroupRepo;
    private $checkListRepo;
    private $attachmentFileRepo;
    private $emailService;

    public function searchProjectByUser($currentUser, $params)
    {
        try {
            if ($currentUser->guest_flg == config('apps.user.is_guest')) {
                return $this->sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
            }
            $projects = $this->projectRepo->getModel();
            $projects = $projects::join('t_project_participant', 't_project.project_id', '=', 't_project_participant.project_id')
                ->select(
                    't_project.project_id',
                    't_project.project_name',
                    't_project.project_status',
                    't_project.template_flg',
                    't_project.template_open_flg',
                );
            $templateFlagQuery2 = [
                't_project.company_id' => $currentUser->company_id,
                't_project.template_flg' => config('apps.project.template_flg.on'),
                't_project.template_open_flg' => config('apps.project.template_open_flg.on')
            ];
            $templateFlagQuery3 = [
                't_project.company_id' => $currentUser->company_id,
                't_project.template_flg' => config('apps.project.template_flg.on'),
                't_project.template_open_flg' => config('apps.project.template_open_flg.off'),
                't_project.create_user_id' => $currentUser->user_id
            ];
            if ($currentUser->super_user_auth_flg == config('apps.user.is_super_user')) {
                $templateFlagQuery1 = [
                    't_project.company_id' => $currentUser->company_id,
                    't_project.template_flg' => config('apps.project.template_flg.off')
                ];
                if (is_null($params['template_flg'])) {
                    $projects = $projects->where(function ($query) use ($templateFlagQuery1, $templateFlagQuery2, $templateFlagQuery3) {
                        $query->where($templateFlagQuery1)->orWhere(function ($query) use ($templateFlagQuery2) {
                            $query->where($templateFlagQuery2);
                        })->orWhere(function ($query) use ($templateFlagQuery3) {
                            $query->where($templateFlagQuery3);
                        });
                    });
                }
                if (!is_null($params['template_flg']) && +$params['template_flg'] == config('apps.project.template_flg.off')) {
                    $projects = $projects->where($templateFlagQuery1);
                }
            } else {
                $templateFlagQuery1 = [
                    't_project.delete_flg' => config('apps.general.not_deleted'),
                    't_project_participant.delete_flg' => config('apps.general.not_deleted'),
                    't_project_participant.user_id' => $currentUser->user_id,
                ];
                if (is_null($params['template_flg'])) {
                    $projects = $projects->where(function ($query) use ($templateFlagQuery1, $templateFlagQuery2) {
                        $query->where($templateFlagQuery1)->orWhere(function ($query) use ($templateFlagQuery2) {
                            $query->where($templateFlagQuery2);
                        });
                    });
                }
                if (!is_null($params['template_flg']) && +$params['template_flg'] == config('apps.project.template_flg.off')) {
                    $projects = $projects->where(function ($query) use ($templateFlagQuery1) {
                        $query->where($templateFlagQuery1)->where([
                            't_project.template_flg' => config('apps.project.template_flg.off')
                        ]);
                    });
                }
            }
            if (+$params['template_flg'] == config('apps.project.template_flg.on')) {
                $projects = $projects->where(function ($query) use ($templateFlagQuery2, $templateFlagQuery3) {
                    $query->where($templateFlagQuery2)->orWhere(function ($query) use ($templateFlagQuery3) {
                        $query->where($templateFlagQuery3);
                    });
                });
            }
            if (!is_null($params['key_word']) || !is_null($params['project_attribute']) || !is_null($params['other_message'])) {
                $projects->join('t_project_owned_attribute', 't_project_owned_attribute.project_id', '=', 't_project.project_id')
                    ->join('m_project_attribute', 'm_project_attribute.project_attribute_id', '=', 't_project_owned_attribute.project_attribute_id');
                if (!is_null($params['key_word'])) {
                    $projects = $projects->where(function ($query) use ($params) {
                        $query->where('t_project.project_overview', 'LIKE', '%' . $params['key_word'] . '%')
                            ->orWhere('t_project.project_name', 'LIKE', '%' . $params['key_word'] . '%')
                            ->orWhere('m_project_attribute.project_attribute_name', 'LIKE', '%' . $params['key_word'] . '%')
                            ->orWhere('t_project_owned_attribute.others_message', 'LIKE', '%' . $params['key_word'] . '%');
                    });
                }
                if (!is_null($params['project_attribute'])) {
                    $projects = $projects->whereIn('m_project_attribute.project_attribute_name', $params['project_attribute']);
                }
                if (!is_null($params['other_message'])) {
                    $projects = $projects->whereIn('t_project_owned_attribute.others_message', $params['other_message']);
                }
            }
            if (!is_null($params['project_status'])) {
                $projects = $projects->whereIn('t_project.project_status', $params['project_status']);
            }

            $projects = $projects
                ->orderBy('t_project.template_flg')
                ->orderBy('t_project.template_open_flg')
                ->distinct('t_project.project_id')
                ->paginate(config('apps.notification.record_per_page'));

            return $this->sendResponse(trans('message.COMPLETE'), $projects);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.NOT_COMPLETE')
            );
        }
    }

    /**
     *  check user is guest or member
     *
     * @param #keyword
     * @return boolean
     */
    public function checkUserIsGuest($email, $currentUser)
    {
        try {
            $user = $this->userRepo->getByCols(['mail_address' => $email, 'company_id' => $currentUser->company_id]);
            if ($user) {
                if ($user->guest_flg == config('apps.general.is_guest')) {
                    return self::sendResponse(
                        trans('message.COMPLETE'),
                        true
                    );
                } else {
                    return self::sendResponse(
                        trans('message.COMPLETE'),
                        false
                    );
                }
            }
            return self::sendResponse(
                trans('message.COMPLETE'),
                true
            );
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return self::sendResponse(
                trans('message.ERR_EXCEPTION'),
                false
            );
        }
    }
    /** * get list project by user
     * @param $currentUser
     * @return array
     */
    public function getProjectByUserRole($currentUser)
    {
        try {
            if ($currentUser->guest_flg == config('apps.user.is_guest')) {
                return $this->sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
            }
            $projects = $this->projectRepo->getModel()::where([
                    't_project.delete_flg' => config('apps.general.not_deleted'),
                    't_project.template_flg' => config('apps.project.template_open_flg.off')
                ]);
            if ($currentUser->super_user_auth_flg == config('apps.user.is_super_user')) {
                $projects = $projects->where('company_id', $currentUser->company_id);
            } else {
                $projects = $projects->join('t_project_participant', 't_project.project_id', '=', 't_project_participant.project_id')
                    ->where([
                        't_project_participant.delete_flg' => config('apps.general.not_deleted'),
                        't_project_participant.user_id' => $currentUser->user_id,
                    ]);
            }

            return $this->sendResponse(
                trans('message.COMPLETE'),
                $projects->get(['t_project.project_id','t_project.project_name'])
            );
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.ERR_EXCEPTION')
            );
        }
    }

    /**
     * get list manager by list project id
     * @param $projectIds
     * @return array
     */
    public function getManagerByProjectIds($projectIds)
    {
        try {
            $model = $this->taskRepo->getModel();
            $model = $model::join('t_user', 't_user.user_id', '=', 't_task.user_id');
            $members = $model->whereIn('t_task.project_id', $projectIds)
                ->select('t_user.user_id', 't_user.disp_name')
                ->distinct('t_user.user_id')
                ->get();

            return $this->sendResponse(trans('message.COMPLETE'), $members);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.ERR_EXCEPTION')
            );
        }
    }
}
