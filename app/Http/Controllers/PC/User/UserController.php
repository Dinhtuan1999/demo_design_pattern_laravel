<?php

namespace App\Http\Controllers\PC\User;

use App\Helpers\Transformer;
use App\Helpers\UploadImageHelper;
use App\Http\Controllers\PC\Controller;
use App\Http\Requests\Auth\UpdateAccountSettingRequest;
use App\Http\Requests\User\UserChangeColorRequest;
use App\Http\Requests\PC\GetUserLicenseRequest;
use App\Http\Requests\User\DeleteAvatarByUserIdRequest;
use App\Http\Requests\User\ListTrashTaskRequest;
use App\Http\Requests\User\GetDetailAccountUserSettingRequest;
use App\Http\Requests\User\SendEmailVerifyRegisterUserRequest;
use App\Http\Requests\User\UploadAvatarRequest;
use App\Repositories\NoticeKindRepository;
use App\Repositories\UserRepository;
use App\Services\EmailService;
use App\Services\UserService;
use App\Services\PasswordService;
use App\Transformers\DetailAccountUserSettingTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Webpatser\Uuid\Uuid;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    protected $userRepository;
    protected $noticeKindRepository;
    protected $emailService;
    protected $passwordService;

    public function __construct(
        UserService $userService,
        UserRepository $userRepository,
        NoticeKindRepository $noticeKindRepository,
        EmailService $emailService,
        PasswordService $passwordService
    ) {
        $this->userService = $userService;
        $this->userRepository = $userRepository;
        $this->noticeKindRepository = $noticeKindRepository;
        $this->emailService = $emailService;
        $this->passwordService = $passwordService;
    }

    public function index(Request $request)
    {
        $data = $this->userService->getListUser($request);
        return view('welcome')->with([
            'data' => $data
        ]);
    }

    public function register(Request $request)
    {
        $validate = $this->userService->validateRegister($request);
        Uuid::generate()->string;
    }

    public function changeColor(UserChangeColorRequest $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => trans('message.ERR_S.C_0001')], 401);
        }

        $result = $this->userService->changeColorUser($request->display_color_id, $user->user_id);


        if ($result['status'] === config('apps.general.success')) {
            return response()->json($result, 200);
        } else {
            return response()->json(['error' => $result['message']], 404);
        }
    }
    public function getColors()
    {
        $user = Auth::user();
        $result = $this->userService->getColors($user);

        return $result;
    }

    public function getCurrentUser()
    {
        return auth()->user();
    }

    public function uploadAvatar(UploadAvatarRequest $request)
    {
        $result = $this->userService->uploadAvatarFile($request);

        if (empty($result)) {
            session()->flash('errors', trans('message.NOT_COMPLETE'));
            return redirect()->back();
        }
        session()->flash('success', trans('message.COMPLETE'));
        return redirect()->back();
    }

    public function getUserLicense(GetUserLicenseRequest $request)
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

        // return view('users.license', compact('data'));
    }

    public function deleteAvatar(DeleteAvatarByUserIdRequest $request)
    {
        $user = Auth::user();
        if (!$user) {
            session()->flash('error', trans('message.ERR_S.C_0001'));
            return redirect()->back();
        }
        $result = $this->userService->deleteAvatarByUserId($user->user_id);
        if (!$result) {
            session()->flash('error', trans('message.NOT_COMPLETE'));
            return redirect()->back();
        }

        session()->flash('success', trans('message.COMPLETE'));
        return redirect()->back();
    }

    public function getListTrashTask(ListTrashTaskRequest $request)
    {
        $result = $this->userService->getListTrashTask($request);
    }

    public function getDetailAccountUserSetting(GetDetailAccountUserSettingRequest $request)
    {
        $user = Auth::user();
        if (!$user) {
            session()->flash('error', trans('message.ERR_S.C_0001'));
            return redirect()->back();
        }
        $user = $this->userService->getDetailAccountUserSettingByUserId($user->user_id);

        session()->flash('success', trans('message.COMPLETE'));
        return redirect()->back()->with(compact('user'));
    }


    public function deleteUserLicense($id)
    {
        //1. Call deleteUserLicense() function from userService by id
        $result = $this->userService->deleteUserLicense($id);
        //2. return respond
        if ($result['status'] == config('apps.general.error')) {
            session()->flash('error', trans('message.NOT_COMPLETE'));
            return redirect()->back();
        }

        session()->flash('success', trans('message.COMPLETE'));
        return redirect()->back();
    }

    public function updateAccountSetting(UpdateAccountSettingRequest $request)
    {
        $user = Auth::user();
        if (!$user) {
            $this->setSessionFlashError(trans('message.ERR_S.C_0001'));
            return redirect()->back();
        }
        $dataUser = $request->only([
            'icon_image_path',
            'disp_name',
        ]);
        // if ($request->hasFile('avatar_file')) {
        //     $avatarPath = UploadImageHelper::UploadImageFormatFile($request->file('avatar_file'));
        //     if ($avatarPath) {
        //         $dataUser['icon_image_path'] = $avatarPath;
        //     }
        // }
        if (!empty($request->get('avatar_base64'))) {
            $avatarPath = UploadImageHelper::uploadImageFormatBase64($request->get('avatar_base64'));
            if ($avatarPath) {
                $dataUser['icon_image_path'] = $avatarPath;
            }
        }

        $dataNotificationManagement = $request->get('settings');
        $result = $this->userService->updateUserNotificationManagementByUserId($user->user_id, $dataUser, $dataNotificationManagement);

        if (!$result) {
            $this->setSessionFlashError(trans('message.INF_COM_0010'));
            return redirect()->back();
        }
        $this->setSessionFlashSuccess(trans('message.INF_COM_0002'));
        return redirect()->back();
    }

    /**
     * A050 show form update User name and Password from email
     *
     * @param  string $email
     * @param  string $loginKey
     * @return view
     */
    public function showFormUpdateUserNameAndPasswordFromEmail($email, $loginKey)
    {
        return view('user.create_name_and_password')
            ->with('mail_address', $email)
            ->with('login_key', $loginKey);
    }

    /**
     * A050 update user name and password from email
     *
     * @param  mixed $request
     * @return mixed
     */
    public function updateUserNameAndPasswordFromEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login_key' => 'required|max:9',
            'mail_address' => 'required|email|max:254',
            'disp_name' => 'required|max:60',
            'login_password' => 'required|min:8|max:100',
        ]);
        if ($validator->fails()) {
            return view('user.create_name_and_password')->withErrors($validator)->with($request->all());
        }

        $this->userService->updateUserNameAndPasswordFromEmail($request);

        // auto login and redirect to home
        $resultLogin = $this->userService->autoLogin($request);
        if ($resultLogin) {
            session()->flash("loginSuccess", __("Login success"));
            return \Redirect::route('pc.user-task.get-my-project');
        }

        $this->setSessionFlashError(trans('message.NOT_COMPLETE'));
        return view('user.create_name_and_password')
            ->with('mail_address', $request->mail_address)
            ->with('login_key', $request->login_key);
    }

    /**
     * A070 show form reset password from email
     *
     * @param  string $email
     * @param  string $loginKey
     * @return view
     */
    public function showFormResetPassword($email, $loginKey)
    {
        return view('user.reset_password')
            ->with('mail_address', $email)
            ->with('login_key', $loginKey);
    }

    /**
     * A070 Reset password
     *
     * @param  Request $request
     * @return mixed
     */
    public function resetPasswordFromEmail(Request $request)
    {
        $resetPasswordResult = $this->passwordService->resetPassword($request);

        if ($resetPasswordResult['status'] === config('apps.general.success')) {
            // auto login and redirect to home
            $loginPassword = ['login_password' => $request->reset_password];
            $request->merge($loginPassword);
            $resultLogin = $this->userService->autoLogin($request);
            if ($resultLogin) {
                return \Redirect::route('pc.user-task.get-my-project');
            }
        }

        return view('user.reset_password')
            ->with('mail_address', $request->mail_address)
            ->with('login_key', $request->login_key)
            ->with('message', $resetPasswordResult['message']);
    }

    /**
     * B010 user setting Index
     *
     * @return view
     */
    public function userSettingIndex()
    {
        $user = Auth::user();
        if (!$user) {
            session()->flash('error', trans('message.ERR_S.C_0001'));
            return redirect()->back();
        }
        $userSettings = $this->userService->getDetailAccountUserSettingByUserId($user->user_id);
        $userSettings = Transformer::item(new DetailAccountUserSettingTransformer(), $userSettings);
        return view('users.user-settings.index', compact('user', 'userSettings'));
    }

    /**
     * Send email verify user when add new user to company
     *
     * @param SendEmailVerifyRegisterUserRequest $request
     * @return void
     */
    public function sendEmailVerifyRegisterUserAjax(SendEmailVerifyRegisterUserRequest $request)
    {
        $user = $this->userRepository->findByField('user_id', $request->user_id);

        if (!$user) {
            return $this->respondWithError(trans('message.ERR_COM_0087', ['attribute' => trans('label.user.user_id')]));
        }

        if ($user->isVerifyEmail()) {
            return $this->respondWithError(trans('message.ERR_COM_0181'));
        }

        $result = $this->emailService->sendEmailVerifyRegisterCompany($user->mail_address, $user->user_id);

        if (empty($result['status']) || $result['status'] == config('apps.general.error')) {
            return $this->respondWithError(trans('message.ERR_COM_0182'));
        }
        return $this->respondSuccess(trans('message.INF_COM_0007'));
    }

    private $userService;
}
