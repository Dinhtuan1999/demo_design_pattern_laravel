<?php

namespace App\Http\Controllers\PC\Auth;

use App\Http\Controllers\PC\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\SendEmailResetPasswordRequest;
use App\Repositories\CompanyRepository;
use App\Repositories\UserRepository;
use App\Services\EmailService;
use App\Services\UserService;

class AuthController extends Controller
{
    protected $userService;
    protected $userRepository;
    protected $emailService;
    protected $companyRepository;

    public function __construct(UserService $userService, UserRepository $userRepository, EmailService $emailService, CompanyRepository $companyRepository)
    {
        $this->userService = $userService;
        $this->userRepository = $userRepository;
        $this->emailService = $emailService;
        $this->companyRepository = $companyRepository;
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $user = auth()->user();

        if (!$user) {
            $this->setSessionFlashError(trans('user.ERR_S.C_0001'));
            return redirect()->back();
        }

        if (!$user->isMatchPasswordCurrent($request->get('current_password'))) {
            $this->setSessionFlashError(trans('message.ERR_070.1'));
            return redirect()->back();
        }
        if ($user->isPasswordNewSamePasswordCurrent($request->get('new_password'))) {
            $this->setSessionFlashError(trans('message.ERR_B70.1'));
            return redirect()->back();
        }

        $result = $this->userService->updatePasswordByUserId($user->user_id, $request->get('new_password'));
        if (!$result) {
            $this->setSessionFlashError(trans('message.INF_COM_0001'));
            return redirect()->back();
        }
        $this->setSessionFlashSuccess(trans('message.ERR_COM_0009'));
        return redirect()->back();
    }

    /**
     * change password call from ajax
     *
     * @param ChangePasswordRequest $request
     * @return void
     */
    public function changePasswordAjax(ChangePasswordRequest $request)
    {
        $user = auth()->user();

        if (!$user) {
            return $this->respondWithError(trans('user.ERR_S.C_0001'));
        }

        if (!$user->isMatchPasswordCurrent($request->get('current_password'))) {
            return $this->respondWithError(trans('message.ERR_070.1'));
        }
        if ($user->isPasswordNewSamePasswordCurrent($request->get('new_password'))) {
            return $this->respondWithError(trans('message.ERR_B70.1'));
        }

        $result = $this->userService->updatePasswordByUserId($user->user_id, $request->get('new_password'));
        if (!$result) {
            return $this->respondWithError(trans('message.ERR_COM_0053', ['attribute' => trans('label.user.login_password')]));
        }
        return $this->respondSuccess(trans('message.INF_A070_0001'));
    }

    public function showResetPasswordForm()
    {
        return view('auth.reset-password');
    }

    public function sendEmailResetPassword(SendEmailResetPasswordRequest $request)
    {
        $loginKey = $request->get('login_key');
        $mailAddress = $request->get('mail_address');
        // check login key
        $company = $this->companyRepository->findByField('login_key', $loginKey);
        if (!$company) {
            // $this->setSessionFlashError(trans('user.ERR_S.C_0001'));
            return redirect()->back()
                ->withErrors(['login_key' => trans('message.ERR_A060_0002')])
                ->withInput();
        }
        // Check user exist
        $user = $this->userRepository->getModel()::where('company_id', $company->company_id)
                                ->where('mail_address', $mailAddress)
                                ->first();
        if (!$user) {
            // $this->setSessionFlashError(trans('user.ERR_S.C_0001'));
            return redirect()->back()
                ->withErrors(['mail_address' => trans('message.ERR_A060_0002')])
                ->withInput();
        }

        $result = $this->emailService->sendEmailResetPassword($user->mail_address, $user->user_id, $company->login_key);
        if ($result['status'] == config('apps.general.success')) {
            $this->setSessionFlashSuccess(trans('message.INF_COM_0061'));
        } else {
            $this->setSessionFlashError(trans('message.ERR_COM_0182'));
        }
        return redirect()->route('pc.show-reset-password');
    }
}
