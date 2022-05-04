<?php

namespace App\Http\Controllers\API\User;

use App\Helpers\Transformer;
use App\Http\Controllers\API\Controller;
use App\Http\Requests\API\ApiGetUserLicenseRequest;
use App\Http\Requests\Auth\UpdateAccountSettingApiRequest;
use App\Http\Requests\User\DeleteAvatarByUserIdRequest;
use App\Http\Requests\User\ListTrashTaskRequest;
use App\Http\Requests\User\GetDetailAccountUserSettingRequest;
use App\Http\Requests\User\DeleteUserLicenseRequest;
use App\Http\Requests\User\UploadAvatarFormatBase64Request;
use App\Http\Requests\User\UserChangeColorRequest;
use App\Repositories\UserRepository;
use App\Services\UserService;
use App\Transformers\DetailAccountUserSettingTransformer;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateMultipleUserLicenseRequest;
use App\Http\Requests\User\searchProjectByUserRequest;
use App\Http\Requests\User\UpdateSettingNotificationManagementRequest;
use App\Http\Requests\User\UpdateUserInfoBasicRequest;
use App\Transformers\User\UserNotificationManagementTransfomer;
use App\Transformers\User\UserTransfomer;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    private $userService;
    private $userRepository;

    public function __construct(UserService $userService, UserRepository $userRepository)
    {
        $this->userService = $userService;
        $this->userRepository = $userRepository;
    }

    public function register(Request $request)
    {
        return $this->userService->register($request);
    }

    public function index(Request $request)
    {
        //dd(Session::get('website_language'));
        $data = $this->userService->getListUser($request);
        return response()->json([
            'data' => $data
        ]);
    }

    public function changeColor(UserChangeColorRequest $request)
    {
        $colorId = $request->input('display_color_id');
        $user = Auth::user();
        $result = $this->userService->changeColorUser($colorId, $user->user_id);

        return response($result);
    }

    public function getUserLicense(ApiGetUserLicenseRequest $request)
    {
        // 1. Validate param from requests
        // Validated in ApiGetUserLicenseRequest class
        $params = $request->all();

        // 1. Get current companyID => use Auth::user()
        $user = Auth::user();
        $companyId = $user->company_id;

        // 2. Call to user service with getUserLicense function
        $params['company_id'] = $companyId;
        $data = $this->userService->getUserLicense($params);

        // 3. Return response base on service's status
        if ($data['status'] == config('apps.general.error')) {
            return $this->respondWithError(trans("message.NOT_COMPLETE"));
        }

        // example
        return $this->respondSuccess(trans("message.COMPLETE"), $data['data']);
    }

    public function getCurrentUser()
    {
        return auth('api')->user();
    }

    public function uploadAvatar(UploadAvatarFormatBase64Request $request)
    {
        $result = $this->userService->uploadAvatarBase64($request);

        if (!$result) {
            return $this->respondWithError(trans("message.FAIL"));
        }

        return $this->respondSuccess(
            trans('message.SUCCESS'),
            [
                                        "image_file" => $result
                                    ]
        );
    }

    public function deleteAvatar(DeleteAvatarByUserIdRequest $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->respondWithError(trans('message.ERR_S.C_0001'));
        }
        $result = $this->userService->deleteAvatarByUserId($user->user_id);
        if (!$result) {
            return $this->respondWithError(trans('message.FAIL'));
        }
        return $this->respondSuccess(trans('message.SUCCESS'));
    }

    public function getListTrashTask(ListTrashTaskRequest $request)
    {
        $curr_user = Auth::user();
        if (!$curr_user) {
            return $this->respondWithError(trans('message.ERR_S.C_0001'));
        }

        $result = $this->userService->getListTrashTask($curr_user->user_id, $request->page);

        if ($result['status'] == config('apps.general.error')) {
            return $this->respondWithError($result['message']);
        }

        return response($result);
    }

    public function getDetailAccountUserSetting(GetDetailAccountUserSettingRequest $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->respondWithError(trans('message.ERR_S.C_0001'));
        }
        $user = $this->userService->getDetailAccountUserSettingByUserId($user->user_id);
        if (!$user) {
            return $this->respondWithError(trans('message.NOT_COMPLETE'));
        }

        return $this->respondSuccess(null, Transformer::item(new DetailAccountUserSettingTransformer(), $user));
    }

    public function deleteUserLicense($id)
    {
        //1. Call deleteUserLicense() function from userService by id
        $result = $this->userService->deleteUserLicense($id);
        //2. return respond
        if ($result['status'] == config('apps.general.error')) {
            return $this->respondWithError($result['message']);
        }

        return $this->respondSuccess($result['message']);
    }

    public function updateMultipleUserLicense(UpdateMultipleUserLicenseRequest $request)
    {
        $companyId = Auth::user()->company_id;
        // 1. Call to updateMultipleUserLicense() function from userService
        $data = $this->userService->updateMultipleUserLicense($request->users, $companyId);
        // 2. Return Response
        if (empty($data) || $data['status'] == config('apps.general.error')) {
            return $this->respondWithError(trans('message.NOT_COMPLETE'));
        }
        return $this->respondSuccess(trans('message.COMPLETE'));
    }

    public function updateAccountSetting(UpdateAccountSettingApiRequest $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->respondWithError(trans('message.ERR_S.C_0001'));
        }

        $dataUser = $request->only([
            'icon_image_path',
            'disp_name',
        ]);

        $dataNotificationManagement = $request->get('settings');

        $dataNotificationManagement = generateDataNotificationManagement($dataNotificationManagement);

        $result = $this->userService->updateUserNotificationManagementByUserId($user->user_id, $dataUser, $dataNotificationManagement);

        if (!$result) {
            return $this->respondWithError(trans('message.NOT_COMPLETE'));
        }
        // return data get-detail-account-user-setting
        $user = $this->userService->getDetailAccountUserSettingByUserId($user->user_id);
        return $this->respondSuccess(trans('message.COMPLETE'), Transformer::item(new DetailAccountUserSettingTransformer(), $user));
    }

    public function searchProjectByUser(searchProjectByUserRequest $request)
    {
        $result = $this->userService->searchProjectByUser($request->user_id, $request->project_status, $request->text_search, $request->page);

        return response($result);
    }

    public function updateUserInfoBasic(UpdateUserInfoBasicRequest $request)
    {
        $dataUpdate = $request->validated();
        $user = Auth::user();

        if (!$user) {
            return $this->respondWithError(trans('message.ERR_S.C_0001'));
        }
        $result = $this->userService->updateUserBasicByUserId($user->user_id, $dataUpdate);
        if (!$result) {
            return $this->respondWithError(trans('message.NOT_COMPLETE'));
        }
        return $this->respondSuccess(trans('message.COMPLETE'), Transformer::item(new UserTransfomer(), $user));
    }

    public function updateSettingNotificationManagement(UpdateSettingNotificationManagementRequest $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->respondWithError(trans('message.ERR_S.C_0001'));
        }
        $dataSettingNotificationManagement = $request->get('settings');
        $dataSettingNotificationManagement = generateDataNotificationManagement($dataSettingNotificationManagement);

        $result = $this->userService->syncNoticeKinds($user, $dataSettingNotificationManagement);
        if (!$result) {
            return $this->respondWithError(trans('message.NOT_COMPLETE'));
        }
        return $this->respondSuccess(trans('message.COMPLETE'), Transformer::item(new UserNotificationManagementTransfomer(), $user));
    }
}
