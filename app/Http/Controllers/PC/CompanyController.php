<?php

namespace App\Http\Controllers\PC;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\UpdateCompanyInformationRequest;
use App\Services\CompanyService;
use App\Services\ListInfoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CompanyController extends Controller
{
    private $companyService;
    private $listInfoService;

    public function __construct(CompanyService $companyService, ListInfoService $listInfoService)
    {
        $this->companyService = $companyService;
        $this->listInfoService = $listInfoService;
    }

    /**
     * Register company use Task Management
     *
     * @return view
     */
    public function registerTaskManagement()
    {
        return view('company.register_task_management_step1');
    }

    /**
     * Validate register company use Task Management
     *
     * @param  Request $request
     * @return array
     */
    public function validateRegisterTaskManagement(Request $request)
    {
        $validator = $this->companyService->validateRegisterTaskManagement($request);
        if ($validator->fails()) {
            return view('company.register_task_management_step1')->withErrors($validator)->with($request->all());
        }

        return view('company.register_task_management_step2')->with($request->all());
    }

    /**
     * Create company use Task Management
     *
     * @param  Request $request
     * @return array
     */
    public function createCompanyUseTaskManagement(Request $request)
    {
        $validator = $this->companyService->validateRegisterTaskManagement($request, false);
        if ($validator->fails()) {
            return view('company.register_task_management_step1')->withErrors($validator)->with($request->all());
        }

        $result = $this->companyService->registerTaskManagement($request);
        if ($result['status'] == config('apps.general.success')) {
            return view('company.register_company_complete')->with([
                'mail_address' => $request->get('mail_address'),
                'user_id' => $result['data']['user_id']
            ]);
        }

        return view('company.register_task_management_step2')->with($request->all())->withErrors(['message' => $result['message']]);
    }

    /**
     * A020 Register company use Company Management
     *
     * @param  Request $request
     * @return array
     */
    public function registerCompanyManagement(Request $request)
    {
        $listCounty = $this->listInfoService->getListCounty();
        return view('company.register_company_management_step1')
            ->with('listCounty', $listCounty);
    }

    /**
     * Validate register company use Company Management
     *
     * @param  Request $request
     * @return array
     */
    public function validateRegisterCompanyManagement(Request $request)
    {
        $validator = $this->companyService->validateRegisterCompanyManagement($request);
        if ($validator->fails()) {
            $listCounty = $this->listInfoService->getListCounty();
            return view('company.register_company_management_step1')
                ->withErrors($validator)
                ->with($request->all())
                ->with('listCounty', $listCounty);
        }

        return view('company.register_company_management_step2')->with($request->all());
    }


    /**
     * Create company use Company Management
     *
     * @param  Request $request
     * @return array
     */
    public function createCompanyUseCompanyManagement(Request $request)
    {
        $validator = $this->companyService->validateRegisterCompanyManagement($request);
        if ($validator->fails()) {
            $listCounty = $this->listInfoService->getListCounty();
            return view('company.register_company_management_step1')
                ->withErrors($validator)
                ->with($request->all())
                ->with('listCounty', $listCounty);
        }

        $result = $this->companyService->registerCompanyManagement($request);
        if ($result['status'] == config('apps.general.success')) {
            return view('company.register_company_complete')->with([
                'mail_address' => $request->get('mail_address'),
                'user_id' => $result['data']['user_id']
            ]);
        }

        return view('company.register_company_management_step2')->with($request->all())->withErrors($result['message']);
    }

    /**
     * Validate register company use Company Management
     *
     * @return array
     */
    public function getCompanyInformation()
    {
        //1. get companyId by currentUser
        $currentUser = Auth::user();
        $companyId = $currentUser->company_id;
        //2. call getCompanyInformation in companyService by companyId
        $data = $this->companyService->getCompanyInformation($companyId);
        //3. return respond
        if ($data['status'] == config("apps.general.error")) {
            session()->flash('error', trans('message.NOT_COMPLETE'));
            return redirect()->back();
        }

        return view();
    }

    public function updateCompanyInformation(UpdateCompanyInformationRequest $request)
    {
        //1. get companyId by currentUser
        $currentUser = Auth::user();
        $companyId = $currentUser->company_id;
        //2. validate input data by use UpdateCompanyInformationRequest
        $params = $request->all();
        $params['company_id'] = $companyId;
        //3. call updateCompanyInformation with params in companyService
        $data = $this->companyService->updateCompanyInformation($params);
        // 4. Return Response
        if (empty($data) || $data['status'] == config('apps.general.error')) {
            session()->flash('error', trans('message.NOT_COMPLETE'));
            return redirect()->back();
        }
        session()->flash('success', trans('message.COMPLETE'));
        return redirect()->back();
    }

    public function getDisclosureStatus(Request $request)
    {
        if (Gate::denies('accountMember')) {
            abort(403);
        }

        $companyId = $request->company_id;
        if (isset($companyId)) {
            $request->company_id = $companyId;
        } else {
            $request->company_id = Auth::user()->company_id;
        }

        $data = $this->companyService->getDisclosureStatus($request);

        if (empty($data) || $data['status'] == config('apps.general.error')) {
            $this->respondWithError(trans('message.NOT_COMPLETE'));
        }

        return view('company.get-disclosure-status-company.index')
        ->with('disclosureStatus', $data);
    }
}
