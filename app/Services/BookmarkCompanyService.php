<?php

namespace App\Services;

use App\Models\BookmarkCompany;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Repositories\CompanyRepository;
use App\Repositories\BookmarkCompanyRepository;

class BookmarkCompanyService extends BaseService
{
    private $bookmarkCompanyRepo;
    private $companyRepo;
    private $companyService;

    public function __construct(
        CompanyService $companyService,
        BookmarkCompanyRepository $bookmarkCompanyRepo,
        CompanyRepository $companyRepo
    ) {
        $this->bookmarkCompanyRepo = $bookmarkCompanyRepo;
        $this->companyRepo = $companyRepo;
        $this->companyService = $companyService;
    }

    /**
     * Sort company bookmark
     *
     * @param  string $userId
     * @param  string $currentPosition
     * @param  string $desiredPosition
     * @return void
     */
    public function sortBookmarkCompany($userId, $currentPosition, $desiredPosition)
    {
        try {
            DB::beginTransaction();

            $this->bookmarkCompanyRepo->getModel()::where([
                'user_id' => $userId,
                'display_order' => $currentPosition
            ])->update(['display_order' => -1]);

            $move = $desiredPosition > $currentPosition ? 'down' : 'up';
            if ($move == 'up') {
                $this->bookmarkCompanyRepo->getModel()::where('display_order', '>=', $desiredPosition)
                    ->where('display_order', '<', $currentPosition)
                    ->increment('display_order');
            } else {
                $this->bookmarkCompanyRepo->getModel()::where('display_order', '<=', $desiredPosition)
                    ->where('display_order', '>', $currentPosition)
                    ->decrement('display_order');
            }

            $this->bookmarkCompanyRepo->getModel()::where([
                'user_id' => $userId,
                'display_order' => -1
            ])->update(['display_order' => $desiredPosition]);

            DB::commit();
            return $this->sendResponse(trans('message.COMPLETE'));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return $this->sendError(trans('message.NOT_COMPLETE'));
        }
    }

    /**
     * Delete Company Bookmark
     *
     * @return array
     */
    public function deleteCompanyBookmark($companyId, $userId)
    {
        try {
            $this->bookmarkCompanyRepo->getInstance()->query()
                ->where([
                    'user_id' => $userId,
                    'company_id' => $companyId
                ])->update([
                    'display_order' => null,
                    'delete_flg' => config('apps.general.is_deleted')
                ]);
            $listCompanyBookmark = $this->companyService->listCompanyBookmark($userId);
            return $this->sendResponse(trans('message.COMPLETE'), $listCompanyBookmark);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(trans('message.NOT_COMPLETE'));
        }
    }

    /**
     * ADD Company Bookmark
     *
     * @return array
     */
    public function addCompanyBookmark($companyId, $userId)
    {
        try {
            DB::beginTransaction();

            $company = $this->companyRepo->getInstance()::select('t_user.*', 't_company.*')
        ->where('t_user.user_id', $userId)
        ->join('t_user', 't_company.company_id', 't_user.company_id')->first();

            if ($company->company_status === config('apps.company.company_status_trial')) {
                $listCompanyBookmark = $this->bookmarkCompanyRepo->getInstance()::where('user_id', $userId)->where('delete_flg', 0)->get();

                if (count($listCompanyBookmark) >= config('apps.company.max_company_status_0_bookmark')) {
                    $listCompanyBookmark = $this->companyService->listCompanyBookmark($userId);

                    return $this->sendError(trans("message.ERR_H020_0001"), $listCompanyBookmark);
                }
            }
            $displayOrder = $this->getLastDisplayOrder($userId);
            $result = $this->bookmarkCompanyRepo->getInstance()->query()
            ->where([
                'user_id' => $userId,
                'company_id' => $companyId
            ])
            ->update([
                'display_order' => $displayOrder,
                'delete_flg' => config('apps.general.not_deleted'),
                'create_user_id' => $userId,
                'create_datetime' => Carbon::now(),
                'update_datetime' => Carbon::now()
            ]);

            if (!$result) {
                $this->bookmarkCompanyRepo->getInstance()->insert([
                'user_id' => $userId,
                'company_id' => $companyId,
                'display_order' => -1,
                'create_datetime' => Carbon::now(),
                'update_datetime' => Carbon::now(),
            ]);
            }
            $listCompanyBookmark = $this->companyService->listCompanyBookmark($userId);

            DB::commit();
            return $this->sendResponse(trans('message.COMPLETE'), $listCompanyBookmark);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return $this->sendError(trans('message.NOT_COMPLETE'));
        }
    }

    /**
     * Get last display order
     *
     * @param  string $userId
     * @return int
     */
    public function getLastDisplayOrder($userId)
    {
        $latestDisplayOrder = $this->bookmarkCompanyRepo->getModel()::where([
            'user_id' => $userId,
            'delete_flg' => config('apps.general.not_deleted')
        ])->orderBy('display_order', 'DESC')->first(['display_order']);

        if (is_null($latestDisplayOrder)) {
            $sequenceNumber = 1;
        } else {
            $sequenceNumber = $latestDisplayOrder['display_order'] + 1;
        }

        return $sequenceNumber;
    }
}
