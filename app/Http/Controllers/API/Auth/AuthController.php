<?php

namespace App\Http\Controllers\API\Auth;

use App\Helpers\Transformer;
use App\Http\Controllers\API\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Repositories\UserRepository;
use App\Services\UserService;
use App\Services\ValidateLoginService;
use App\Transformers\User\UserTransfomer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    protected $userService;
    protected $userRepository;
    protected $validateLoginService;

    public function __construct(UserService $userService, UserRepository $userRepository, ValidateLoginService $validateLoginService)
    {
        $this->userService = $userService;
        $this->userRepository = $userRepository;
        $this->validateLoginService = $validateLoginService;
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $user = auth('api')->user();

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
            return $this->respondWithError(trans('message.INF_COM_0001'));
        }
        return $this->respondSuccess(trans('message.ERR_COM_0009'));
    }

    public function freshToken()
    {
        $tokenJwt = $this->validateLoginService->freshTokenApi();

        if ($tokenJwt === false) {
            return $this->respondWithError(trans("auth.failed"));
        }
        // get User;
        $user = Auth::user();
        return $this->respondSuccess('', [
            'object'       => 'JwtToken',
            'access_token' => $tokenJwt->getToken(),
            'expires_in'   => $tokenJwt->getExpires() * 60,
            'token_type'   => 'Bearer',
            'user' => Transformer::item(new UserTransfomer(), $user),
        ]);
    }
}
