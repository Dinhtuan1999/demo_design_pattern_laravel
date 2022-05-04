<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\API\Controller;
use App\Services\PasswordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\UrlAuthenticationVerify;
use App\Services\UrlAuthenticationVerifyService;

class PasswordController extends Controller
{
    protected $passwordService;
    protected $urlAuthenticationVerifyService;

    public function __construct(
        PasswordService $passwordService,
        UrlAuthenticationVerifyService $urlAuthenticationVerifyService
    ) {
        $this->passwordService = $passwordService;
        $this->urlAuthenticationVerifyService = $urlAuthenticationVerifyService;
    }

    /**
     * Send email reset password
     *
     * @param  Request $request
     * @return json
     */
    public function sendEmailResetPassword(Request $request)
    {
        $result = $this->passwordService->sendEmailResetPassword($request);

        return response()->json($result);
    }

    /**
     * Reset password
     *
     * @param  Request $request
     * @return json
     */
    public function resetPassword(Request $request)
    {
        // step 1 : validate request
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'mail_address' => 'required|email|max:254',
        ]);
        $validator->setAttributeNames([
            'token' => trans('label.user.token'),
            'mail_address' => trans('label.company.mail_address')
        ]);
        if ($validator->fails()) {
            return $this->respondWithError($validator->messages()->all());
        }

        // step 2 : verify token $ email
        $emailKind = UrlAuthenticationVerify::MAIL_KIND_PASSWORD_RESET;
        $result = $this->urlAuthenticationVerifyService->verifyUrl($request->token, $request->mail_address, $emailKind);
        if ($result['status'] == config('apps.general.success')) {
            // step 3 : reset password
            $result = $this->passwordService->resetPassword($request);
        }

        return response()->json($result);
    }
}
