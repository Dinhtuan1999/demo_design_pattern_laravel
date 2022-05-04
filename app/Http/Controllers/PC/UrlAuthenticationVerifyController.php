<?php

namespace App\Http\Controllers\PC;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\UrlAuthenticationVerifyService;
use App\Models\UrlAuthenticationVerify;

class UrlAuthenticationVerifyController extends Controller
{
    private $urlAuthenticationVerifyService;

    public function __construct(UrlAuthenticationVerifyService $urlAuthenticationVerifyService)
    {
        $this->urlAuthenticationVerifyService = $urlAuthenticationVerifyService;
    }

    /**
     * Verify register url
     *
     * @param  mixed $request
     * @return void
     */
    public function verifyRegister(Request $request)
    {
        $token = $request->route()->parameter('token');
        $email = $request->route()->parameter('email');
        $emailKind = UrlAuthenticationVerify::MAIL_KIND_USER_REGISTRATION;
        $result = $this->urlAuthenticationVerifyService->verifyUrl($token, $email, $emailKind);
        if ($result['status'] == config('apps.general.success')) {
            // show screen PC A050
            return \Redirect::route('pc.user.form-name-password', [$email, $result['data']['login_key']]);
        }

        return view('errors.errors')->with('message', $result['message']);
    }

    /**
     * Verify reset password url
     *
     * @param  mixed $request
     * @return void
     */
    public function verifyResetPassword(Request $request)
    {
        $token = $request->route()->parameter('token');
        $email = $request->route()->parameter('email');
        $emailKind = UrlAuthenticationVerify::MAIL_KIND_PASSWORD_RESET;
        $result = $this->urlAuthenticationVerifyService->verifyUrl($token, $email, $emailKind);
        if ($result['status'] == config('apps.general.success')) {
            // show screen PC A070
            return view('user.reset_password')
                ->with('mail_address', $email)
                ->with('login_key', $result['data']['login_key']);
        }

        return view('errors.errors')->with('message', $result['message']);
    }
}
