<?php

namespace App\Http\Controllers\PC\Auth;

use App\Http\Controllers\PC\Controller;
use App\Http\Requests\Auth\ValidateUserLoginPcRequest;
use App\Http\Requests\Auth\ValidateUserLoginRequest;
use App\Repositories\CompanyRepository;
use App\Services\CompanyService;
use App\Services\UserService;
use App\Services\ValidateLoginService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class LoginController extends Controller
{
    protected $validateLoginService;
    protected $companyRepository;

    public function __construct(ValidateLoginService $validateLoginService, CompanyRepository $companyRepository)
    {
        $this->validateLoginService = $validateLoginService;
        $this->companyRepository = $companyRepository;
    }

    /**
     * Show login form
     *
     * @return view
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return \Redirect::route('pc.home');
        }

        return view('auth.login');
    }

    /**
     * Login
     *
     * @param  ValidateUserLoginPcRequest $request
     * @return mixed
     */
    public function login(ValidateUserLoginPcRequest $request)
    {
        $loginKey = $request->get('login_key');
        // check login_key => get comapany_id to login
        $company = $this->companyRepository->findByField('login_key', $loginKey);
        if (!$company) {
            $this->setSessionFlashError(trans("auth.failed"));
            return redirect()->back();
        }

        return $this->validateLoginService->validateLoginPc($request, $company->company_id);
    }

    /**
     * Logout
     *
     * @return view
     */
    public function logout()
    {
        Auth::guard()->logout();
        Session::flush();
        return redirect()->route('pc.login');
    }
}
