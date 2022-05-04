<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\UrlAuthenticationVerify;
use App\Services\AppService;
use App\Services\BaseService;
use App\Mail\ResetPasswordNotification;
use App\Mail\ProjectNotification;
use App\Mail\VerifyRegisterCompanyNotification;
use Carbon\Carbon;

class EmailService extends BaseService
{
    /**
     * Send email verify register company
     *
     * @param  string $emailAddress
     * @return mixed
     */
    public function sendEmailVerifyRegisterCompany($emailAddress, $userID = null)
    {
        try {
            $authenticationToken = $this->generateVerificationCode(
                UrlAuthenticationVerify::MAIL_KIND_USER_REGISTRATION,
                $userID
            );

            // @TODO Call job send email (Batch No 2) instead of sending it directly
            // Mail::to($emailAddress)->send(new VerifyRegisterCompanyNotification($authenticationToken, $emailAddress));
            Mail::queue((new VerifyRegisterCompanyNotification($authenticationToken, $emailAddress))->onQueue('email'));

            return self::sendResponse([trans('message.INF_A040_0001')]);
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            return self::sendError([trans('message.ERR_EXCEPTION')]);
        }
    }

    /**
     * Send email reset password
     *
     * @param  string $emailAddress
     * @param  string $userID
     * @return mixed
     */
    public function sendEmailResetPassword($emailAddress, $userID, $loginKey = null)
    {
        $authenticationToken = $this->generateVerificationCode(
            UrlAuthenticationVerify::MAIL_KIND_PASSWORD_RESET,
            $userID,
            $loginKey
        );

        Mail::to($emailAddress)->send(new ResetPasswordNotification($authenticationToken, $emailAddress, $loginKey));

        return self::sendResponse([trans('message.INF_COM_0061')]);
    }

    /**
     * Generate and store verification code
     *
     * @param  int $emailKind (0：ユーザー登録/register user、1：パスワードリセット/password reset)
     * @param  string $user_id
     * @return string $authenticationToken
     */
    public function generateVerificationCode($mailKind = 0, $userID = null)
    {
        /* generate */
        $authenticationToken = \Str::random(UrlAuthenticationVerify::TOKEN_LENGTH);

        /* store */
        $urlAuthenticationVerify = new UrlAuthenticationVerify();
        $tokenData = [
            'url_authentication_verify_id' => AppService::generateUUID(),
            'authentication_token' => $authenticationToken,
            'authentication_token_expiration' => Carbon::now()->addSeconds(env('TIME_EXPIRED', 300)),
            'mail_kinds' => $mailKind
        ];
        if ($userID) {
            $tokenData['user_id'] = $userID;
            $tokenData['create_user_id'] = $userID;
        }
        $urlAuthenticationVerify->create($tokenData);

        return $authenticationToken;
    }
    /**
     * Send email add member to project
     *
     * @param  string $emailAddress
     * @param  string $userID
     * @return mixed
     */
    public function sendEmailAddUserToProject($user, $projectName, $link)
    {
        Mail::queue((new ProjectNotification($user->disp_name, $user->mail_address, $projectName, $link, 'add_member_project'))->onQueue('email'));
        return self::sendResponse([trans('message.INF_COM_0061')]);
    }
    /**
     * Send email delete member to project
     *
     * @param  string $emailAddress
     * @param  string $userID
     * @return mixed
     */
    public function sendEmailDeleteUserToProject($user, $projectName, $link)
    {
        Mail::queue((new ProjectNotification($user->disp_name, $user->mail_address, $projectName, $link, 'delete_member_project'))->onQueue('email'));
        return self::sendResponse([trans('message.INF_COM_0061')]);
    }
    /**
     * Send email project complete
     *
     * @param  string $emailAddress
     * @param  string $userID
     * @return mixed
     */
    public function sendEmailCompleteProject($user, $projectName, $link)
    {
        Mail::queue((new ProjectNotification($user->disp_name, $user->mail_address, $projectName, $link, 'complete_project'))->onQueue('email'));
        return self::sendResponse([trans('message.INF_COM_0061')]);
    }
    /**
     * Send email project delete
     *
     * @param  string $emailAddress
     * @param  string $userID
     * @return mixed
     */
    public function sendEmailDeleteProject($user, $projectName, $link)
    {
        Mail::queue((new ProjectNotification($user->disp_name, $user->mail_address, $projectName, $link, 'delete_project'))->onQueue('email'));
        return self::sendResponse([trans('message.INF_COM_0061')]);
    }
}
