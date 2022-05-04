<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Controller;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmailController extends Controller
{
    private $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * A.A040.1 send email verify register company
     *
     * @param  Request $request mail_address
     * @return json
     */
    public function sendEmailVerifyRegisterCompany(Request $request)
    {
        // validate email
        $validator = Validator::make($request->all(), [
            'mail_address' => 'required|email|max:254',
        ]);
        $validator->setAttributeNames([
            'mail_address' => trans('label.company.mail_address')
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => config('apps.general.error'),
                'message' => $validator->messages()->all()
            ]);
        }

        $result = $this->emailService->sendEmailVerifyRegisterCompany(
            $request->get('mail_address'),
            auth('api')->user()->user_id
        );

        return response()->json([
            'status' => config('apps.general.success'),
            'message' => $result['message']
        ]);
    }
}
