<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Webpatser\Uuid\Uuid;

class AppService
{
    public function changeLanguage(Request $request)
    {
        Session::put('website_language', $request->input('lang'));
        return 1;
    }

    public static function generateUUID()
    {
        return Uuid::generate()->string;
    }

    public static function transMessageIDs(array $messageIDs)
    {
        $listMessage = [];
        foreach ($messageIDs as $messageID) {
            $listMessage[] = trans("message.$messageID");
        }
        return $listMessage;
    }
}
