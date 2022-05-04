<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Controller;
use App\Http\Requests\BookmarkCompany\SortBookmarkCompanyRequest;
use App\Http\Requests\Company\DeleteCompanyBookmarkRequest;
use App\Models\BookmarkCompany;
use App\Services\AppService;
use App\Services\BookmarkCompanyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookmarkCompanyController extends Controller
{
    private $bookmarkCompanyService;

    public function __construct(BookmarkCompanyService $bookmarkCompanyService)
    {
        $this->bookmarkCompanyService = $bookmarkCompanyService;
    }

    /**
     * Sort Company Bookmark
     *
     * @return json
     */
    public function sortBookmarkCompany(SortBookmarkCompanyRequest $request)
    {
        $result = $this->bookmarkCompanyService->sortBookmarkCompany($request->user_id, $request->arr_company_id, $request->arr_display_order);
        // return response
        if ($result['status'] == config("apps.general.error")) {
            return $this->respondWithError(trans('message.NOT_COMPLETE'));
        }
        return $this->respondSuccess(trans('message.COMPLETE'));
    }

    /**
     *  Delete Company Bookmark
     *
     * @return json
     */
    public function deleteCompanyBookmark(DeleteCompanyBookmarkRequest $request)
    {
        $result = $this->bookmarkCompanyService->deleteCompanyBookmark($request->company_id, $request->user_id);

        // return response
        if ($result['status'] == config("apps.general.error")) {
            return $this->respondWithError(trans('message.NOT_COMPLETE'));
        }
        return $this->respondSuccess(trans('message.COMPLETE'));
    }
}
