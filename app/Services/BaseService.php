<?php

namespace App\Services;

class BaseService
{
    public function sendError($messages, $data = [], $errorCode = null)
    {
        return [
            'status' => config('apps.general.error'),
            'message' => $messages,
            // 'data' => $data,
            'error_code' => $errorCode ?? config('apps.general.error_code')
        ];
    }

    public function sendResponse($messages, $data = [])
    {
        return [
            'status' => config('apps.general.success'),
            'message' => $messages,
            'data' => $data
        ];
    }

    public function exceptionError()
    {
        return [
            'status' => config('apps.general.error'),
            'message' => [trans('message.ERR_EXCEPTION') ],
            'error_code' => config('apps.general.error_code'),
            'message_id' => ['ERR_EXCEPTION']
        ];
    }

    public function initResponse()
    {
        return [
            'status'        => config('apps.general.success'),
            'data'          => null,
            'message'       => [trans('message.SUCCESS')],
        ];
    }
}
