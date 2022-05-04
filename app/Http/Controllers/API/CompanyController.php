<?php

namespace App\Http\Controllers\API;

use App\Exceptions\DeniedAccountAvaiable;
use App\Http\Requests\Company\AddLicenceRequest;
use App\Http\Controllers\API\Controller;
use App\Http\Requests\Company\AddUserPaymentRequest;
use App\Http\Requests\Company\GetDetailsInformationRequest;
use App\Http\Requests\Company\GetGraphByCompanyRequest;
use App\Http\Requests\Company\ListCompanyBookmarkRequest;
use App\Http\Requests\Company\SearchCompanyInformationRequest;
use App\Http\Requests\Company\DeleteLicenceRequest;
use App\Http\Requests\Company\GetDisclosureStatusCompanyRequest;
use App\Http\Requests\Company\SetBookmarkCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyInformationRequest;
use App\Services\BookmarkCompanyService;
use App\Services\CompanyService;
use App\Services\BaseService;
use App\Services\LicenceManagementService;
use App\Services\PaymentHistoryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompanyController extends Controller
{
    private $companyService;
    private $bookmarkCompanyService;
    private $licenceManagementService;
    private $paymentHistoryService;
    private $baseService;
    protected $user;

    public function __construct(
        CompanyService $companyService,
        LicenceManagementService $licenceManagementService,
        PaymentHistoryService $paymentHistoryService,
        BaseService $baseService,
        BookmarkCompanyService $bookmarkCompanyService
    ) {
        $this->companyService = $companyService;
        $this->licenceManagementService = $licenceManagementService;
        $this->paymentHistoryService = $paymentHistoryService;
        $this->baseService = $baseService;
        $this->bookmarkCompanyService = $bookmarkCompanyService;

        $this->middleware('can:company-available');
    }

    /**
     * Get company information
     *
     * @return json
     */
    public function getCompanyInformation()
    {
        // get companyId by currentUser
        $currentUser = Auth::user();
        $companyId = $currentUser->company_id;
        // call getCompanyInformation in companyService by companyId
        $data = $this->companyService->getCompanyInformation($companyId);
        // return response
        if ($data['status'] == config("apps.general.error")) {
            return $this->respondWithError(trans('message.NOT_COMPLETE'));
        }

        return $this->respondSuccess(
            trans('message.COMPLETE'),
            $data['data']
        );
    }

    /**
     * Get Number Licences of Company
     *
     * @return json
     */
    public function getNumberLicences()
    {
        // get companyId by currentUser
        $currentUser = Auth::user();
        $companyId = $currentUser->company_id;
        // call getNumberLicences in licenceManagementService by companyId
        $data = $this->licenceManagementService->getNumberLicences($companyId);
        // return response
        if ($data['status'] == config("apps.general.error")) {
            return $this->respondWithError(trans('message.NOT_COMPLETE'));
        }
        return $this->respondSuccess(
            trans('message.COMPLETE'),
            $data
        );
    }

    /**
     * Update Information of Company
     *
     * @return json
     */
    public function updateCompanyInformation(UpdateCompanyInformationRequest $request)
    {
        //1. get companyId by currentUser
        $currentUser = Auth::user();
        $companyId = $currentUser->company_id;
        //2. validate input data by use UpdateCompanyInformationRequest
        $params = $request->all();
        //3. call updateCompanyInformation with params in companyService
        $data = $this->companyService->updateCompanyInformation($params, $companyId);
        // 4. Return Response
        if (empty($data) || $data['status'] == config('apps.general.error')) {
            return $this->respondWithError(trans('message.NOT_COMPLETE'));
        }
        return $this->respondSuccess(trans('message.COMPLETE'));
    }

    /**
     * Get List Company Bookmark
     *
     * @return json
     */
    public function listCompanyBookmark(ListCompanyBookmarkRequest $request)
    {
        $result = $this->companyService->listCompanyBookmark($request->user_id, $request->page);

        return $this->respondSuccess(
            trans('message.COMPLETE'),
            $result
        );
    }

    /** Search Information of Company
     *
     * @return json
     */
    public function searchInformation(SearchCompanyInformationRequest $request)
    {
        $result = $this->companyService->searchCompanyInformation(
            $request->user_id,
            $request->keyword,
            $request->group_project_atrr_id,
            $request->include_contact_receive,
            $request->include_contact_send,
            $request->sort_by,
            $request->page,
        );

        return $this->respondSuccess(
            trans('message.COMPLETE'),
            $result
        );
    }

    /**
     * Add more licence for company
     *
     * @return JsonResponse
     */
    public function addLicence(AddLicenceRequest $request)
    {
        //1. get companyId by currentUser
        $companyId = Auth::user()->company_id;
        //2. call addLicense with params in licenceManagementService
        $data = $this->licenceManagementService->addLicence($request->buy_licence_num, $companyId);
        // 3. return response
        if (empty($data) || $data['status'] == config("apps.general.error")) {
            return $this->respondWithError(trans('message.NOT_COMPLETE'));
        }
        return $this->respondSuccess(
            trans('message.COMPLETE')
        );
    }

    /**
     * Delete licence for company
     *
     * @return JsonResponse
     */
    public function deleteLicence(DeleteLicenceRequest $request)
    {
        //1. get companyId by currentUser
        $companyId = Auth::user()->company_id;
        //2. call deleteLicence with params in licenceManagementService
        $data = $this->licenceManagementService->deleteLicence($request->del_licence_num, $companyId);
        // 3. return response
        if (empty($data) || $data['status'] == config("apps.general.error")) {
            return $this->respondWithError(trans('message.NOT_COMPLETE'));
        }
        return $this->respondSuccess(
            trans('message.COMPLETE')
        );
    }

    /**
     * Get payment histories of company
     *
     * @return JsonResponse
     */
    public function getPaymentHistories()
    {
        //1. get companyId by currentUser
        $companyId = Auth::user()->company_id;
        //2. call updateCompanyInformation with params in companyService
        $data = $this->paymentHistoryService->getPaymentHistories($companyId);
        //3. Return Response
        if (empty($data) || $data['status'] == config('apps.general.error')) {
            return $this->respondWithError(trans('message.NOT_COMPLETE'));
        }
        return $this->respondSuccess(trans('message.COMPLETE'), $data);
    }

    /**
     * Add user payment
     *
     * @return JsonResponse
     */
    public function addUserPayment(AddUserPaymentRequest $request)
    {
        $params = $request->only(array_keys($request->rules()));
        //1. get companyId by currentUser
        $params['date_of_expiry'] = Carbon::createFromFormat('m/y', $params['date_of_expiry'])->format('Y-m-d');
        $params['update_user_id'] = $params['create_user_id'] = $params['company_id'] = Auth::user()->company_id;
        //2. call addUserPayment with params in paymentHistoryService
        $data = $this->companyService->addUserPayment($params);
        //3. Return Response
        if (empty($data) || $data['status'] == config('apps.general.error')) {
            return $this->respondWithError(trans('message.NOT_COMPLETE'));
        }
        return $this->respondSuccess(trans('message.COMPLETE'));
    }

    public function getDisclosureStatus(GetDisclosureStatusCompanyRequest $request)
    {
        $data = $this->companyService->getDisclosureStatus($request);

        if (empty($data) || $data['status'] == config('apps.general.error')) {
            $this->respondWithError(trans('message.NOT_COMPLETE'));
        }
        return $this->respondSuccess(trans('message.COMPLETE'), $data);
    }

    /**
     * Get details company information
     * @param GetDetailsInformationRequest $request
     * @return JsonResponse
     */
    public function getDetailsInformation(GetDetailsInformationRequest $request)
    {
        //2. call addUserPayment with params in paymentHistoryService
        $data = $this->companyService->getDetailsInformation($request->company_id);
        //3. Return Response
        if (empty($data) || $data['status'] == config('apps.general.error')) {
            $this->respondWithError(trans('message.NOT_COMPLETE'));
        }
        return $this->respondSuccess(trans('message.COMPLETE'), $data['data']);
    }

    /**
     * Get graph by company
     * @param GetGraphByCompanyRequest $request
     * @return JsonResponse
     */
    public function getGraphByCompany(GetGraphByCompanyRequest $request)
    {
        //2. call getGraphByCompany with params in companyService
        $data = $this->companyService->getGraphByCompany($request->company_id);
        //3. Return Response
        if (empty($data) || $data['status'] == config('apps.general.error')) {
            return $this->respondWithError(trans('message.FAIL'));
        }
        return $this->respondSuccess(trans('message.SUCCESS'), $data['data']);
    }

    /**
     * Set bookmark company
     * @param SetBookmarkCompanyRequest $request
     * @return JsonResponse
     */
    public function setBookmarkCompany(SetBookmarkCompanyRequest $request)
    {
        $currentUser = $request->user();
        if (!$currentUser) {
            return $this->baseService->sendError([trans('message.FAIL')], [], config('apps.general.error_code', 600));
        }
        //2. call getGraphByCompany with params in companyService
        $data = [];
        if ($request->is_bookmark == config('apps.general.is_not_bookmark')) {
            $data = $this->bookmarkCompanyService->deleteCompanyBookmark($request->company_id, $currentUser->user_id);
        } elseif ($request->is_bookmark == config('apps.general.is_bookmark')) {
            $data = $this->bookmarkCompanyService->addCompanyBookmark($request->company_id, $currentUser->user_id);
        }
        //3. Return Response
        if (empty($data) || $data['status'] == config('apps.general.error')) {
            return $this->respondWithError(trans('message.FAIL'));
        }
        return $this->respondSuccess(trans('message.SUCCESS'), $data['data']);
    }
}
