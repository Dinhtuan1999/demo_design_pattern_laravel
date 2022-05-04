<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Repositories\UserRepository;
use App\Services\BaseService;
use App\Services\EmailService;

class PasswordService extends BaseService
{
    private $emailService;

    public function __construct(UserRepository $userRepo, EmailService $emailService)
    {
        $this->userRepo = $userRepo;
        $this->emailService = $emailService;
    }

    /**
     * S.A060.4_Send_Email_Reset_Password
     *
     * @param  Request $request
     * @return mixed
     */
    public function sendEmailResetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login_key' => 'required|digits:9',
            'mail_address' => 'required|email|max:254',
        ]);
        $validator->setAttributeNames([
            'login_key' => trans('label.company.company_id'),
            'mail_address' => trans('label.company.mail_address')
        ]);
        if ($validator->fails()) {
            return self::sendError($validator->messages()->all());
        }

        // Check exist
        $user = $this->userRepo->getUserByLoginKeyAndPasssword($request->login_key, $request->mail_address);
        if (is_null($user)) {
            return self::sendError([trans('message.ERR_PASSWORD_00001')]);
        }

        return $this->emailService->sendEmailResetPassword($user->mail_address, $user->user_id, $request->login_key);
    }

    /**
     * S.A070.2_Reset_Password_User
     *
     * @param  Request $request
     * @return mixed
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login_key' => 'required|digits:9',
            'mail_address' => 'required|email|max:254',
            'reset_password' => 'required|min:8|max:100',
        ]);
        $validator->setAttributeNames([
            'login_key' => trans('label.company.company_id'),
            'mail_address' => trans('label.company.mail_address'),
            'reset_password' => trans('label.user.login_password')
        ]);
        if ($validator->fails()) {
            return self::sendError($validator->messages()->all());
        }

        $user = $this->userRepo->getUserByLoginKeyAndPasssword($request->login_key, $request->mail_address);
        if ($user) {
            $password = Hash::make($request->get('reset_password'));
            $user->update([
                'login_password'=> $password,
                'update_user_id'=> $user->user_id
            ]);

            return self::sendResponse([trans('message.ERR_COM_0009')]);
        }

        return self::sendError([trans('message.ERR_COM_0009')]);
    }
}
