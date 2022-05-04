<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\Contact\DeleteContactResponseRequest;
use App\Http\Requests\Contact\DeleteContactSendRequest;
use App\Http\Requests\Contact\GetListContactResponseRequest;
use App\Http\Requests\Contact\GetListContactSendRequest;
use App\Http\Requests\Contact\SetContactIsReadRequest;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    private $contactService;

    public function __construct(
        ContactService $contactService
    ) {
        $this->contactService = $contactService;
    }

    /**
     * Set contact is read
     *
     * @return JsonResponse
     */
    public function setContactIsRead(SetContactIsReadRequest $request)
    {
        // 2. call setContactIsRead with params in contactService
        $data = $this->contactService->setContactIsRead($request->contact_id, $request->type);
        // 3. Return Response
        return $this->getResponse($data);
    }

    /**
     * Delete Contact Send
     * @param DeleteContactSendRequest $request
     * @return array|JsonResponse
     */
    public function deleteContactSend(DeleteContactSendRequest $request)
    {
        // 2. call deleteContactSend with params in contactService
        $data = $this->contactService->deleteContactSend($request->contact_id);
        // 3. Return Response
        return $this->getResponse($data);
    }

    /**
     * Delete Contact Response
     * @param DeleteContactResponseRequest $request
     * @return array|JsonResponse
     */
    public function deleteContactResponse(DeleteContactResponseRequest $request)
    {
        // 2. call deleteContactSend with params in contactService
        $data = $this->contactService->deleteContactResponse($request->contact_id);
        // 3. Return Response
        return $this->getResponse($data);
    }


    /**
     * Get List Contact Send
     * @param GetListContactSendRequest $request
     * @return array|JsonResponse
     */
    public function getListContactSend(GetListContactSendRequest $request)
    {
        // 1. Get all query parameters
        $params = [];
        if ($request->has('filter')) {
            $params['consent_classification'] = $request->filter;
        }
        $params['sender_company_id'] = Auth::user()->company_id;
        // 2. call deleteContactSend with params in contactService
        $data = $this->contactService->getListContactSend($params);
        // 3. Return Response
        return $this->getResponse($data);
    }

    /**
     * Get List Contact Response
     * @param GetListContactResponseRequest $request
     * @return array|JsonResponse
     */
    public function getListContactResponse(GetListContactResponseRequest $request)
    {
        // 1. Get all query parameters
        $params = [];
        if ($request->has('filter')) {
            $params['consent_classification'] = $request->filter;
        }
        $params['sender_company_id'] = Auth::user()->company_id;
        // 2. call deleteContactSend with params in contactService
        $data = $this->contactService->getListContactResponse($params);
        // 3. Return Response
        return $this->getResponse($data);
    }

    /**
     * @param $data
     * @return array|JsonResponse
     */
    public function getResponse($data)
    {
        $response = [];

        if (empty($data)) {
            $response = $this->respondWithError(trans('message.NOT_COMPLETE'));
        }
        if ($data['status'] == config('apps.general.error')) {
            $response = $this->respondWithError($data['message']);
        }
        if ($data['status'] == config('apps.general.success')) {
            $response = $this->respondSuccess(trans('message.COMPLETE'), $data);
        }
        return $response;
    }
}
