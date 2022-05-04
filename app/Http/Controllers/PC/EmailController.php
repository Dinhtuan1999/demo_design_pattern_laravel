<?php

namespace App\Http\Controllers\PC;

use App\Http\Controllers\Controller;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * EmailController send email verify register company
 */
class EmailController extends Controller
{
    private $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     *  A040 PC re-send email verify register company
     *
     * @param  Request $request mail_address
     * @return mixed
     */
    public function sendEmailVerifyRegisterCompany(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mail_address' => 'required|email|max:254',
            'user_id' => 'required|max:36|exists:t_user,user_id',
        ]);
        if ($validator->fails()) {
            if (!empty($request->ajax())) {
                return response()->json([
                    'status'  => config('apps.general.error'),
                    'message' => $validator->messages()->all()
                ]);
            }
            return view('company.register_company_complete')->withErrors($validator)->with($request->all());
        }

        $result = $this->emailService->sendEmailVerifyRegisterCompany(
            $request->get('mail_address'),
            $request->get('user_id')
        );
        if (!empty($request->ajax())) {
            return response()->json([
                'status' => config('apps.general.success'),
                'message' => $result['message']
            ]);
        }

        return view('company.register_company_complete')->with($request->all());
    }
}
