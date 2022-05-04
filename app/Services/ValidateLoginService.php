<?php

namespace App\Services;

use App\Http\Requests\Auth\ValidateUserLoginPcRequest;
use App\Http\Requests\Auth\ValidateUserLoginRequest;
use App\Providers\RouteServiceProvider;
use App\Traits\Auth\RedirectsUsers;
use App\Traits\Auth\ThrottlesLogins;
use App\Values\JwtToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ValidateLoginService
{
    use RedirectsUsers;
    use ThrottlesLogins;

    public const USERNAME = "mail_address";
    public const PASSWORD = "login_password";
    public const REMEMBER = "remember_me";

    protected $redirectTo = RouteServiceProvider::HOME;
    private $credentials = [];

    public function username()
    {
        return self::USERNAME;
    }
    public function password()
    {
        return self::PASSWORD;
    }
    public function remember()
    {
        return self::REMEMBER;
    }

    public function freshTokenApi()
    {
        $token =  auth('api')->refresh();
        if (!$token) {
            return false;
        }
        return new JwtToken($token);
    }

    public function validateLoginApi(ValidateUserLoginRequest $request, string $companyId)
    {
        $result = false;
        $token = auth('api')->attempt($this->getCredentials($request, $companyId));

        if (!$token) {
            return $result;
        }
        return new JwtToken($token);
    }

    /**
     * get credentials login jwt
     *
     * @param ValidateUserLoginRequest $request
     * @return array
     */
    private function getCredentials(ValidateUserLoginRequest $request, $companyId)
    {
        return array_merge($this->credentials, $request->only([
           $this->username(),
           $this->password(),
       ]), ['company_id' => $companyId]);
    }

    public function validateLoginPc(ValidateUserLoginPcRequest $request, $companyId)
    {

        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if (method_exists($this, 'hasTooManyLoginAttempts') &&
            $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        if ($this->attemptLogin($request, $companyId)) {
            // Login Success => check liscense with user guest before redirect
            $user = Auth::user();
            if ($user && $user->isGuest() && !Gate::allows('company-available', $user)) {
                //Login
                Auth::guard()->logout();
                Session::flush();
                //throw validate
                throw ValidationException::withMessages([
                    $this->username() => [trans('message.INF_A060_0002')],
                ]);
            }
            // user member & company not 'company-available' => popup message INF_A060_0001
            if ($user && !Gate::allows('company-available', $user)) {
                session()->flash("loginWithCompanyNotAvailable", trans('message.INF_A060_0001'));
            } else {
                session()->flash("loginSuccess", __("Login success"));
            }

            return $this->sendLoginResponse($request);
        }

        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        $this->incrementLoginAttempts($request);
        return $this->sendFailedLoginResponse($request);
    }



    /**
     * Attempt to log the user into the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function attemptLogin(Request $request, $companyId)
    {
        return $this->guard()->attempt(
            $this->credentials($request, $companyId),
            $request->filled($this->remember())
        );
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard();
    }

    /**
     * Get the failed login response instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        throw ValidationException::withMessages([
            $this->username() => [trans('auth.failed')],
        ]);
    }

    /**
     * The user has been authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(Request $request, $user)
    {
        //
    }

    /**
     * Get the needed authorization credentials from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function credentials(Request $request, $companyId)
    {
        return array_merge(['company_id' => $companyId], $request->only($this->username(), $this->password()));
    }

    /**
     * Send the response after the user was authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    protected function sendLoginResponse(Request $request)
    {
        $request->session()->regenerate();

        $this->clearLoginAttempts($request);

        if ($response = $this->authenticated($request, $this->guard()->user())) {
            return $response;
        }

        return $request->wantsJson()
                    ? new JsonResponse([], 204)
                    : redirect()->intended($this->redirectPath());
    }
}
