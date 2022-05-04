<?php

namespace App\Http\Controllers\PC;

use App\Http\Controllers\Controller;
use App\Services\CompanyService;
use App\Services\BookmarkCompanyService;
use App\Services\ContactPurposeService;
use App\Services\ProjectAttributeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CompanySearchController extends Controller
{
    private $companyService;
    private $bookmarkCompanyService;
    private $contactPurposeService;
    private $projectAttributeService;

    public function __construct(
        CompanyService $companyService,
        BookmarkCompanyService $bookmarkCompanyService,
        ContactPurposeService $contactPurposeService,
        ProjectAttributeService $projectAttributeService
    ) {
        $this->companyService = $companyService;
        $this->bookmarkCompanyService = $bookmarkCompanyService;
        $this->contactPurposeService = $contactPurposeService;
        $this->projectAttributeService = $projectAttributeService;
    }

    /**
     * H010 index
     *
     * @param  Request $request
     * @return view
     */
    public function index(Request $request)
    {
        $userId = Auth::user()->user_id;

        if (Gate::denies('accountMember')) {
            abort(403);
        }

        $keyword = $request->get('keyword');
        $projectAttributeIds = $request->get('project_attribute_id', null);
        $includeContactReceive = $request->get('include_contact_receive', 0);
        $includeContactSend = $request->get('include_contact_send', 0);

        $listCompanyBookmark = $this->companyService->listCompanyBookmark($userId);
        $searchCompanyInformation = $this->companyService->searchCompanyInformation(
            Auth::user()->company_id,
            $keyword,
            $projectAttributeIds,
            $includeContactReceive,
            $includeContactSend
        );
        $listContacPurpose = $this->contactPurposeService->getContactPurpose();
        $projectAttributes = $this->projectAttributeService->getProjectAttributes();
        $projectAttributes = isset($projectAttributes["data"]) ? $projectAttributes["data"] : "";

        $totalCompanyInforItem = isset($searchCompanyInformation['data']) ? count($searchCompanyInformation['data']) : 0;

        return view('company_search.index')
            ->with([
                'listCompanyBookmark' => $listCompanyBookmark['data'],
                'searchCompanyInformation' => $searchCompanyInformation['data'],
                'listContacPurpose' => $listContacPurpose,
                'projectAttributes' => $projectAttributes,
                'keyword' => $keyword,
                'totalCompanyInforItem' => $totalCompanyInforItem
            ]);
    }

    /**
     * H010 Search company info Ajax
     *
     * @param  mixed $request
     * @return html
     */
    public function searchCompanyInfoAjax(Request $request)
    {
        $searchCompanyInformation = $this->companyService->searchCompanyInformation(
            Auth::user()->company_id,
            $request->keyword,
            $request->project_attribute_id,
            $request->include_contact_receive,
            $request->include_contact_send,
            $request->sort,
            $request->page
        );

        return $searchCompanyInformation;
    }

    /**
     * H010 Delete bookmark company
     *
     * @param  mixed $request
     * @return void
     */
    public function deleteBookmarkCompany(Request $request)
    {
        $currentUser = auth()->user();
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|string',
        ]);
        if ($validator->fails()) {
            return [
                'status'  => config('apps.general.error'),
                'message' => $validator->messages()->all()
            ];
        }
        return $this->bookmarkCompanyService->deleteCompanyBookmark($request->company_id, $currentUser->user_id);
    }

    /**
     * H010 Sort bookmark company
     *
     * @param  mixed $request
     * @return void
     */
    public function sortBookmarkCompany(Request $request)
    {
        $currentUser = auth()->user();
        $validator = Validator::make($request->all(), [
            'current_position' => 'required|int',
            'desired_position' => 'required|int'
        ]);
        if ($validator->fails()) {
            return [
                'status'  => config('apps.general.error'),
                'message' => $validator->messages()->all()
            ];
        }
        return $this->bookmarkCompanyService->sortBookmarkCompany(
            $currentUser->user_id,
            $request->current_position,
            $request->desired_position
        );
    }

    /**
     * H010 Get list CompanyBookmark ajax
     *
     * @param  mixed $request
     * @return html
     */
    public function getListCompanyBookmarkAjax(Request $request)
    {
        $userId = Auth::user()->user_id;
        $listCompanyBookmark = $this->companyService->listCompanyBookmark(
            $userId,
            $request->input('page', '1')
        );
        if ($listCompanyBookmark['data']['total'] == 0) {
            return '';
        }

        return view('company_search.item_bookmark')->with('listCompanyBookmark', $listCompanyBookmark['data']);
    }
}
