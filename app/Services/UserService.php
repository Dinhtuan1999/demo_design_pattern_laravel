<?php

namespace App\Services;

use Carbon\Carbon;
use App\Helpers\UploadImageHelper;
use App\Http\Requests\Auth\UpdateAccountSettingRequest;
use App\Http\Requests\User\UploadAvatarFormatBase64Request;
use App\Http\Requests\User\UploadAvatarRequest;
use App\Models\User;
use App\Repositories\NotificationManagementRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\TaskRepository;
use App\Repositories\TrashRepository;
use App\Repositories\UserRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\UserDispColorRepository;
use App\Services\BaseService;
use App\Services\ValidateLoginService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserService extends BaseService
{
    protected $notificationManagementRepository;
    protected $projectRepository;

    public function __construct(
        UserRepository $userRepo,
        TaskRepository $taskRepo,
        NotificationManagementRepository $notificationManagementRepository,
        ProjectRepository $projectRepository,
        TrashRepository $trashRepo,
        CompanyRepository $companyRepository,
        ValidateLoginService $validateLoginService,
        UserDispColorRepository $userDispColorRepo
    ) {
        $this->userRepo = $userRepo;
        $this->taskRepo = $taskRepo;
        $this->notificationManagementRepository = $notificationManagementRepository;
        $this->projectRepository = $projectRepository;
        $this->trashRepo = $trashRepo;
        $this->companyRepository = $companyRepository;
        $this->validateLoginService = $validateLoginService;
        $this->userDispColorRepo = $userDispColorRepo;
    }

    /**
     * Validate register
     *
     * @param  Request $request
     * @return Validator
     */
    public function validateRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|max:36',
            'mail_address' => 'required|email|max:254|unique:t_user',
            'disp_name' => 'required|max:60',
            'login_password' => 'required|min:8|max:100',
        ]);
        $validator->setAttributeNames([
            'company_id' => trans('label.company.company_id'),
            'mail_address' => trans('label.company.mail_address'),
            'disp_name' => trans('label.user.disp_name'),
            'login_password' => trans('label.user.login_password')
        ]);

        return $validator;
    }

    /**
     * S.A050.2 register user
     *
     * @param  mixed $request
     * @return mixed
     */
    public function register(Request $request)
    {
        $validator = $this->validateRegister($request);
        if ($validator->fails()) {
            return [
                'status'  => config('apps.general.error'),
                'message' => $validator->messages()->all()
            ];
        }

        return $this->userRepo->create($request->all());
    }

    /**
     * A050 Create name and password
     *
     * @param  Request $request
     * @return mixed
     */
    public function updateUserNameAndPasswordFromEmail(Request $request)
    {
        return $this->userRepo->getModel()::join(
            't_company',
            't_user.company_id',
            '=',
            't_company.company_id'
        )
            ->where('t_company.login_key', $request->login_key)
            ->where('t_user.mail_address', $request->mail_address)
            ->update([
                'disp_name' => $request->disp_name,
                'login_password' => Hash::make($request->login_password)
            ]);
    }

    /**
     * A050 Auto login
     *
     * @param  Request $request
     * @return bool
     */
    public function autoLogin(Request $request)
    {
        // check login_key => get comapany_id to login
        $company = $this->companyRepository->findByField('login_key', $request->get('login_key'));

        if (is_null($company)) {
            return false;
        }
        return $this->validateLoginService->attemptLogin($request, $company->company_id);
    }

    public function getListUser(Request $request)
    {
        $data = $this->userRepo->get(
            [],
            config('apps.general.per_page'),
            ['by' => 'disp_name', 'type' => 'asc']
        );
        return $data;
    }

    public function changeColorUser($dispColorId, $userId)
    {
        $response = [];

        try {
            // update color code of user
            $user = $this->userRepo->getByCol('user_id', $userId);
            $user->display_color_id = $dispColorId;
            $user->save();
            // get color code
            $color = $this->getColorByUser($user);
            if (!$color) {
                // set color default
                $color =  $this->userDispColorRepo->getModel()::where('delete_flg', config('apps.general.not_deleted'))->first();
            }
            $response['status']     = config('apps.general.success');
            $response['message']    = [trans('message.SUCCESS')];
            $response['data'] = $color;
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());
            $response['status']     = config('apps.general.error');
            $response['message']    = [trans('message.INF_COM_0010')];
        }

        return $response;
    }
    // get color code by user id
    public function getColorByUser($user)
    {
        $color = $this->userDispColorRepo->getByCol('disp_color_id', $user->display_color_id);
        return $color;
    }
    public function getColors($user)
    {
        $response = [];
        try {
            $model = $this->userDispColorRepo->getModel();
            $colors = $model::where('delete_flg', config('apps.general.not_deleted'))->get();
            $colorActive = $user->display_color_id;
            // get color code
            $color = $this->getColorByUser($user);
            if (!$color) {
                // set color default
                $color =  $this->userDispColorRepo->getModel()::where('delete_flg', config('apps.general.not_deleted'))->first();
                $colorActive = $color->disp_color_id;
            }
            $response['status']     = config('apps.general.success');
            $response['message']    = [trans('message.SUCCESS')];
            $response['data'] = [$colors, $colorActive, $color];
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());
            $response['status']     = config('apps.general.error');
            $response['message']    = [trans('message.INF_COM_0010')];
        }

        return $response;
    }


    public function uploadAvatarBase64(UploadAvatarFormatBase64Request $request): string
    {
        return UploadImageHelper::uploadImageFormatBase64($request->image_file);
    }

    public function uploadAvatarFile(UploadAvatarRequest $request): string
    {
        return UploadImageHelper::UploadImageFormatFile($request->image_file);
    }

    public function updatePasswordByUserId(string $userId, string $passwordNew): bool
    {
        if (empty($userId) || empty($passwordNew)) {
            return false;
        }
        return $this->userRepo->updateByField('user_id', $userId, ['login_password' => Hash::make($passwordNew)]);
    }

    public function getUserLicense($params = [])
    {

        // 2. Make array $queryParams base on $params and companyID (Search)
        $queryParams = [];
        // 2.1. Search by email
        if (!empty($params['mail_address'])) {
            $queryParams[] = ['mail_address', 'like', "%" . $params['mail_address'] . "%"];
        }
        // 2.2. Filter by super_user_auth_flg
        if (isset($params['super_user_auth_flg'])) {
            $queryParams['super_user_auth_flg'] = $params['super_user_auth_flg'];
        }
        // 2.3. Filter by service_contractor_auth_flg
        if (isset($params['service_contractor_auth_flg'])) {
            $queryParams['service_contractor_auth_flg'] = $params['service_contractor_auth_flg'];
        }
        // 2.4. Filter by company_search_flg
        if (isset($params['company_search_flg'])) {
            $queryParams['company_search_flg'] = $params['company_search_flg'];
        }
        // 2.5. Get user by companyId
        if (!empty($params['company_id'])) {
            $queryParams['company_id'] = $params['company_id'];
        }

        if (!empty($params['user_groups']) && is_array($params['user_groups'])) {
            $queryParams['user_groups'] = $params['user_groups'];
        }
        // get members
        $queryParams['guest_flg'] = config('apps.general.not_guest');
        // 3. Use UserRepository to get|search userLicenses,
        try {
            $userLicenses = $this->userRepo->getUserLicense($queryParams);
            // 3.1. return [] | $userLicenses
            if ($userLicenses) {
                return $this->sendResponse(
                    'success',
                    $userLicenses
                );
            }
        } catch (\Exception $e) {
            // 3.3. Throw $exception
            set_log_error('getUserLicense Error:', $e->getMessage());

            return $this->sendError(
                trans('message.NOT_COMPLETE')
            );
        }
    }

    public function deleteAvatarByUserId(string $userId): bool
    {
        $update = $this->userRepo->updateByField('user_id', $userId, ['icon_image_path' => '']);
        if (!$update) {
            return false;
        }
        return true;
    }

    public function getListTrashTask($user_id, $page)
    {
        $response = [];

        try {
            $data = $this->trashRepo->getListTrashTaskByUserID($user_id, $page);

            $response['data']       = $data;
            $response['status']     = config('apps.general.success');
            $response['message']    = trans('message.SUCCESS');
            $response['message_id'] = 'SUCCESS';
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());

            $response = $this->exceptionError();
        }

        return $response;
    }

    /**
     * Get Detail Account User Setting By UserId
     *
     * @param  string $userId
     * @return mixed
     */
    public function getDetailAccountUserSettingByUserId(string $userId)
    {
        $user = $this->userRepo->getInstance()->query()
            ->where('user_id', $userId)
            ->with([
                'notification_managements' => function ($q) {
                    $q->select(['user_id', 'notice_kinds_id', 'inapp_notification_flg', 'desktop_notification_flg', 'mail_notification_flg']);
                },
                'notice_kinds'
            ])
            ->select('user_id', 'disp_name', 'mail_address', 'icon_image_path')
            ->first();
        return $user;
    }

    public function deleteUserLicense($id)
    {
        try {
            //1. get user by id
            $user = $this->userRepo->getById($id);
            //2. get user logged by Auth::user
            $userLogged = Auth::user();

            //3. check empty user
            if (empty($user)) {
                return $this->sendError(trans('message.NOT_COMPLETE'));
            }
            //4. check empty companyID , compare userCompanyId and userLoggedCompanyId, compare user and userLogged
            if (empty($user->company_id) || $user->company_id != $userLogged->company_id || $userLogged->user_id == $user->user_id) {
                return  $this->sendError(
                    trans('message.NOT_COMPLETE')
                );
            }
            // 5. delete user by update   delete_flg = 1
            $update = $this->userRepo->update($user->user_id, ['delete_flg' => config('apps.general.is_deleted')]);
            if (!$update) {
                return $this->sendError(
                    trans('message.NOT_COMPLETE')
                );
            }
            //6. return status
            return $this->sendResponse('success');
        } catch (\Exception $e) {
            return $this->sendError(
                trans('message.NOT_COMPLETE')
            );
        }
    }


    public function updateMultipleUserLicense($userDatas, $companyId)
    {
        try {
            $usersNeedUpdate = [];
            $usersNeedCreate = [];

            foreach ($userDatas as $userData) {
                if (empty($userData['user_id'])) {
                    $userData['user_id'] = AppService::generateUUID();
                    $userData['company_id'] = $companyId;
                    $userData['create_datetime'] = Carbon::now();
                    $usersNeedCreate[] = $userData;
                } else {
                    $usersNeedUpdate[] = $userData;
                }
            }

            DB::beginTransaction();

            // Update users
            foreach ($usersNeedUpdate as $userData) {
                $this->userRepo->updateById($userData['user_id'], $userData);
            }

            // Create users
            $this->userRepo->insertMultiRecord($usersNeedCreate);

            DB::commit();
            return $this->sendResponse(trans('message.COMPLETE'));
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
            Log::error($e->getMessage());
            return $this->sendError(trans('message.NOT_COMPLETE'));
        }
    }

    /**
     * update account setting by user id
     *
     * @param string $userId
     * @return bool
     */
    public function updateAccountSettingByUserId(string $userId, UpdateAccountSettingRequest $request): bool
    {
        DB::beginTransaction();
        // update info in user
        $updateUserInfo = $this->userRepo->updateByField('user_id', $userId, $request->only(['disp_name', 'icon_image_path']));
        if (!$updateUserInfo) {
            return false;
        }
        // update setting in notification management;
        $updateNotificationManagement = $this->notificationManagementRepository->updateByField('user_id', $userId, $request->only('inapp_notification_flg', 'desktop_notification_flg', 'mail_notification_flg'));
        if (!$updateNotificationManagement) {
            // rollback DB
            DB::rollBack();
            return false;
        }
        DB::commit();
        return true;
    }

    public function searchProjectByUser($user_id, $project_status, $text_search, $page = null, $take = 5)
    {
        $response = [];

        try {
            $projects = $this->projectRepository->getModel()::select(
                't_project.project_name',
                't_project.actual_start_date',
                't_project.actual_end_date',
                't_project.project_status',
                DB::raw('(SELECT COUNT(*) FROM t_task AS task_compl WHERE task_compl.project_id = t_project.project_id
                                                            AND task_compl.task_status = ' . config('apps.task.status_key.complete') . ')/(SELECT COUNT(*) FROM t_task AS task_total WHERE task_total.project_id = t_project.project_id) AS percent_complete')
            )
                ->join('t_project_participant', 't_project_participant.project_id', '=', 't_project.project_id')
                ->where(['t_project_participant.user_id' => $user_id]);

            // filter status
            if ($project_status) {
                $projects = $projects->where('t_project.project_status', $project_status);
            }
            // search text
            if ($text_search) {
                $projects = $projects->where('t_project.project_name', 'LIKE', '%' . $text_search . '%');
            }

            $projects = $projects->orderBy('t_project.project_name', 'ASC')->paginate($take);


            $response['data']       = $projects->toArray();
            $response['status']     = config('apps.general.success');
            $response['message']    = trans('message.SUCCESS');
            $response['message_id'] = 'SUCCESS';
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());

            $response = $this->exceptionError();
        }

        return $response;
    }

    public function updateUserNotificationManagementByUserId($userId, $dataUser, $dataNotificationManagement)
    {
        DB::beginTransaction();

        //set avatar default if empty
        if (empty($dataUser['icon_image_path'])) {
            $dataUser['icon_image_path'] = config('apps.general.avatar_image_default');
        }
        //get basename if icon_image_path contain url
        if (str_contains($dataUser['icon_image_path'], Storage::url(''))) {
            $dataUser['icon_image_path'] = 'images/'.basename($dataUser['icon_image_path']);
        }
        // update info in user
        $updateUserInfo = $this->userRepo->updateByField('user_id', $userId, $dataUser);
        if (!$updateUserInfo) {
            return false;
        }
        $user = $this->userRepo->getInstance()->where('user_id', $userId)->first();
        if (!$user) {
            DB::rollBack();
            return false;
        }
        // update setting in notification management;
        try {
            $user->notice_kinds()->sync($dataNotificationManagement);
        } catch (\Throwable $th) {
            set_log_error('updateUserNotificationManagementByUserId', $th->getMessage());
            // rollback DB
            DB::rollBack();
            return false;
        }
        DB::commit();
        return true;
    }

    public function getUserByProject($projectId, $filter)
    {
        $response = $this->initResponse();

        try {
            $response['data'] = $this->userRepo->getUserByProject($projectId, $filter);
            $response['last_page'] = $response['data']->lastPage();
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response = $this->exceptionError();
        }

        return $response;
    }

    public function fetchTaskManagerByProject($projectId, $filter, $flagFilter)
    {
        $response = $this->initResponse();

        try {
            $data = $this->userRepo->getUserByProjectV5($projectId, $filter, $flagFilter);
            $response['data'] = $data;
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            $response = $this->exceptionError();
        }

        return $response;
    }

    private function refactorDataManager($data)
    {
        if (count($data) == 0) {
            return null;
        }

        $responseItems = [];
        $managers = [];
        $parents = [];

        foreach ($data as $item) {
            // current record is empty group
            if (empty($item->task_id) && empty($item->parent_task_id)) {
                $item->level = 1; // check display in client
                $item->child = 0; // check display arrow
                $item->object_clone = false;
            } else {
                // check group contain current record or no ?
                if (!in_array($item->user_id, $managers, true)) {

                    // Create outside group
                    $newItem = clone $item;
                    $newItem->task_id = null;
                    $newItem->task_name = null;
                    $newItem->parent_task_id = null;

                    $newItem->level = 1;
                    $newItem->child = 1;
                    $newItem->object_clone = true;

                    $responseItems[] = $newItem;

                    // Add group to array group
                    $managers[] = $item->user_id;
                }

                // current record is sub task
                if (!empty($item->parent_task_id)) {
                    // check parent task contain current record or no ?
                    if (!in_array($item->parent_task_id, $parents, true)) {

                        // Create parent task
                        $newItem = clone $item;
                        $newTask = $this->taskRepo->getTaskInfo($item->parent_task_id);

                        if (empty($newTask)) {
                            continue;
                        }

                        $newItem->task_id = $newTask->task_id;
                        $newItem->task_name = $newTask->task_name;
                        $newItem->sub_task_info = $newTask->sub_tasks_complete_count . '/' .$newTask->sub_tasks_count;
                        $newItem->check_list_info = $newTask->check_lists_complete_count . '/' .$newTask->check_lists_count;
                        $newItem->scheduled_period = formatShowDate($newTask->start_plan_date) . ' - '.formatShowDate($newTask->end_plan_date);
                        $newItem->achievement_period = formatShowDate($newTask->start_date) . ' - '.formatShowDate($newTask->end_date);
                        $newItem->manager = $newTask->user ? $newTask->user->disp_name : '';
                        $newItem->task_status_name = $newTask->task_status ? $newTask->task_status->task_status_name : '';
                        $newItem->priority_name = $newTask->priority_mst ? $newTask->priority_mst->priority_name : '' ;
                        $newItem->group_name = $newTask->task_group ? $newTask->task_group->group_name : '' ;

                        $newItem->parent_task_id = null;

                        $newItem->level = 2;
                        $newItem->child = 1;
                        $newItem->object_clone = true;

                        $responseItems[] = $newItem;

                        $parents[] = $item->parent_task_id;
                    }

                    $item->level = 3;
                    $item->child = 0;
                    $item->object_clone = false;
                } else {
                    $item->level = 2;
                    $item->child = 0;
                    $item->object_clone = false;
                }

                $task = $this->taskRepo->getTaskInfo($item->task_id);

                $item->task_id = $task->task_id;
                $item->task_name = $task->task_name;
                $item->sub_task_info = $task->sub_tasks_complete_count . '/' .$task->sub_tasks_count;
                $item->check_list_info = $task->check_lists_complete_count . '/' .$task->check_lists_count;
                $item->scheduled_period = formatShowDate($task->start_plan_date) . ' - '.formatShowDate($task->end_plan_date);
                $item->achievement_period = formatShowDate($task->start_date) . ' - '.formatShowDate($task->end_date);
                $item->manager = $task->user ? $task->user->disp_name : '';
                $item->task_status_name = $task->task_status ? $task->task_status->task_status_name : '';
                $item->priority_name = $task->priority_mst ? $task->priority_mst->priority_name : '' ;
                $item->group_name = $task->task_group ? $task->task_group->group_name : '' ;
            }

            $responseItems[] = $item;
        }

        return new Collection($responseItems);
    }

    public function updateUserBasicByUserId($userId, $dataUser)
    {
        return $this->userRepo->updateByField('user_id', $userId, $dataUser);
    }

    public function syncNoticeKinds(User $user, $dataSettingNotificationManagement)
    {
        try {
            $result = $user->notice_kinds()->sync($dataSettingNotificationManagement);
            if (!$result) {
                return false;
            }
            return true;
        } catch (\Throwable $th) {
            set_log_error('syncNoticeKinds', $th->getMessage());
            return false;
        }
    }

    /**
     * get getManagersByProjectId
     *
     * @param [type] $projectId
     * @return colelction
     */
    public function getManagersByProjectId($projectId)
    {
        try {
            $query = $this->userRepo->getInstance()->query();
            return $query->join('t_task', 't_task.user_id', '=', 't_user.user_id')
                        ->join('t_project', 't_project.project_id', '=', 't_task.project_id')
                        ->where('t_project.project_id', $projectId)
                        ->distinct('t_user.user_id')
                        ->select(['t_user.user_id','t_user.disp_name', 't_user.icon_image_path'])
                        ->get();
        } catch (\Throwable $th) {
            set_log_error('getManagersByProjectId', $th->getMessage());
        }
        return collect();
    }
    /**
     * get getAuthorsByProjectId
     *
     * @param [type] $projectId
     * @return colelction
     */
    public function getAuthorsByProjectId($projectId)
    {
        try {
            $query = $this->userRepo->getInstance()->query();
            return $query->join('t_task', 't_task.create_user_id', '=', 't_user.user_id')
                        ->join('t_project', 't_project.project_id', '=', 't_task.project_id')
                        ->where('t_project.project_id', $projectId)
                        ->distinct('t_user.user_id')
                        ->select(['t_user.user_id','t_user.disp_name', 't_user.icon_image_path'])
                        ->get();
        } catch (\Throwable $th) {
            set_log_error('getManagersByProjectId', $th->getMessage());
        }
        return collect();
    }


    private $userRepo;
}
