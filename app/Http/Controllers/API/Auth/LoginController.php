<?php

namespace App\Http\Controllers\API\Auth;

use App\Helpers\Transformer;
use App\Http\Controllers\API\Controller;
use App\Http\Requests\Auth\ValidateUserLoginRequest;
use App\Repositories\CompanyRepository;
use App\Repositories\UserRepository;
use App\Services\CompanyService;
use App\Services\ValidateLoginService;
use App\Transformers\User\UserTransfomer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class LoginController extends Controller
{
    protected $validateLoginService;
    protected $companyRepository;
    protected $userRepository;

    public function __construct(ValidateLoginService $validateLoginService, CompanyRepository $companyRepository, UserRepository $userRepository)
    {
        $this->validateLoginService = $validateLoginService;
        $this->companyRepository = $companyRepository;
        $this->userRepository = $userRepository;
    }


    public function login(ValidateUserLoginRequest $request)
    {
        $loginKey = $request->get('login_key');

        // check login_key => get comapany_id to login
        $company = $this->companyRepository->findByField('login_key', $loginKey);
        if (!$company) {
            return $this->respondWithError(trans("auth.failed"));
        }
        $companyId= $company->company_id;
        $tokenJwt = $this->validateLoginService->validateLoginApi($request, $companyId);

        if ($tokenJwt === false) {
            return $this->respondWithError(trans("auth.failed"));
        }
        // get User;
        $user = $this->userRepository->findByFields(['mail_address' => $request->get('mail_address'), 'company_id' => $companyId], ['free_period_use_company']);

        return $this->respondSuccess('', [
            'object'       => 'JwtToken',
            'access_token' => $tokenJwt->getToken(),
            'expires_in'   => $tokenJwt->getExpires() * 60,
            'token_type'   => 'Bearer',
            'user' => Transformer::item(new UserTransfomer(), $user)
        ]);
    }

    public function logout()
    {
        try {
            Auth::guard('api')->logout();
            Session::flush();
        } catch (\Throwable $th) {
            return  $this->respondWithError(trans("auth.token-empty"));
        }

        return  $this->respondSuccess(trans("message.SUCCESS"));
    }
}
