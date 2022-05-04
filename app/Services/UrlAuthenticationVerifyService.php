<?php

namespace App\Services;

use App\Repositories\UrlAuthenticationVerifyRepository;
use App\Models\UrlAuthenticationVerify;
use App\Repositories\UserRepository;
use App\Services\BaseService;
use App\Services\CompanyService;
use Carbon\Carbon;

class UrlAuthenticationVerifyService extends BaseService
{
    protected $urlAuthenticationVerifyRepo;
    protected $companyService;
    protected $userRepo;

    public function __construct(
        UrlAuthenticationVerifyRepository $urlAuthenticationVerifyRepo,
        CompanyService $companyService,
        UserRepository $userRepo
    ) {
        $this->urlAuthenticationVerifyRepo = $urlAuthenticationVerifyRepo;
        $this->companyService = $companyService;
        $this->userRepo = $userRepo;
    }

    /**
     * S.A050.1 verify URL
     *
     * @param  string $token
     * @param  string $email
     * @param  int    $emailKind (0：ユーザー登録/register user、1：パスワードリセット/password reset)
     * @return mixed
     */
    public function verifyUrl($token, $email, $emailKind)
    {
        $urlAuthen = $this->urlAuthenticationVerifyRepo->getModel()::join(
            't_user',
            't_user.user_id',
            '=',
            't_url_authentication_verify.user_id'
        )
            ->where('t_url_authentication_verify.authentication_token', $token)
            ->where('t_url_authentication_verify.mail_kinds', $emailKind)
            ->where(function ($query) {
                $query->orWhere('t_url_authentication_verify.delete_flg', '<>', config('apps.general.is_deleted'))
                ->orWhereNull('t_url_authentication_verify.delete_flg');
            })
            ->where('t_user.mail_address', $email)
            ->first([
                'url_authentication_verify_id',
                'authentication_token_expiration',
                't_url_authentication_verify.user_id'
            ]);

        if ($urlAuthen) {
            if (Carbon::parse($urlAuthen['authentication_token_expiration'])->greaterThan(Carbon::now())) {

                // If it's a registration then update the delete flag
                if ($emailKind === UrlAuthenticationVerify::MAIL_KIND_USER_REGISTRATION) {
                    $this->urlAuthenticationVerifyRepo->getModel()::where(
                        'url_authentication_verify_id',
                        $urlAuthen['url_authentication_verify_id']
                    )
                        ->update(['delete_flg' => config('apps.general.is_deleted')]);
                }

                $loginKey = $this->companyService->getLoginKeyByUserId($urlAuthen->user_id);

                $this->userRepo->getModel()::where('user_id', $urlAuthen->user_id)
                    ->update(['mail_verify_flg' => config('apps.user.mail_verify_flg')]);

                return self::sendResponse([trans('message.SUCCESS')], ['login_key' => $loginKey]);
            }
            return self::sendError(trans('message.ERR_A050_0002'));
        } else {
            return self::sendError(trans('message.ERR_A050_0001'));
        }
    }
}
