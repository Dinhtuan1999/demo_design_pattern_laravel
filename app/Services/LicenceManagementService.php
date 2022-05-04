<?php

namespace App\Services;

use App\Repositories\LicenceManagementRepository;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LicenceManagementService extends BaseService
{
    private $licenceManagementRepo;
    private $userRepo;

    public function __construct(LicenceManagementRepository $licenceManagementRepo, UserRepository $userRepo)
    {
        $this->licenceManagementRepo = $licenceManagementRepo;
        $this->userRepo = $userRepo;
    }

    /**
     * Get Number Licences of Company
     *
     * @param  string $companyId
     * @return array
     */
    public function getNumberLicences($companyId)
    {
        try {
            if (empty($companyId)) {
                return $this->sendError(
                    trans('message.NOT_COMPLETE')
                );
            }

            $licenceNum = $usedLicenceNum = 0;

            $queryParams = [
                'company_id' => $companyId,
                'delete_flg' => config('apps.general.not_deleted'),
            ];
            // Call getByCol function in licenceManagementRepo to get all licence management of company
            $licenceManagements = $this->licenceManagementRepo->getLicenceManagements($queryParams);

            $licenceNum = $licenceManagements->sum('licence_num');
            $usedLicenceNum = $this->userRepo->countUsedLicenceNum($queryParams);

            return $this->sendResponse(
                trans('message.COMPLETE'),
                [
                    'licence_num' => $licenceNum,
                    'used_licence_num' => $usedLicenceNum
                ]
            );
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.NOT_COMPLETE')
            );
        }
    }

    /**
     * Add licence for company
     *
     * @param  int $licenceNum
     * @param  string $companyId
     *
     * @return array
     */
    public function addLicence(int $licenceNum, string $companyId)
    {
        try {
            if (empty($companyId)) {
                return $this->sendError(
                    trans('message.NOT_COMPLETE')
                );
            }

            $data = [
                'licence_management_id' => AppService::generateUUID(),
                'company_id' => $companyId,
                'licence_num' => $licenceNum,
                'licence_num_change_date' => Carbon::now(),
                'delete_flg' => config('apps.general.not_deleted'),
            ];
            // Call addLicence function in licenceManagementRepo to add license management of company
            $this->licenceManagementRepo->addLicence($data);

            return $this->sendResponse(trans('message.COMPLETE'));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.NOT_COMPLETE')
            );
        }
    }

    /**
     * Delete licence for company
     *
     * @param  int $licenceNum
     * @param  string $companyId
     *
     * @return array
     */
    public function deleteLicence(int $licenceNum, string $companyId)
    {
        try {
            if (empty($companyId)) {
                return $this->sendError(
                    trans('message.NOT_COMPLETE')
                );
            }

            $data = [
                'licence_management_id' => AppService::generateUUID(),
                'company_id' => $companyId,
                'licence_num' => $licenceNum,
                'licence_num_change_date' => Carbon::now(),
                'delete_flg' => config('apps.general.is_deleted'),
            ];
            // Call deleteLicence function in licenceManagementRepo to add licence management of company
            $this->licenceManagementRepo->deleteLicence($data);

            return $this->sendResponse(trans('message.COMPLETE'));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.NOT_COMPLETE')
            );
        }
    }
}
