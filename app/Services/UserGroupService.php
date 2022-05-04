<?php

namespace App\Services;

use App\Http\Requests\UserGroup\CreateOrUpdateUserGroupRequest;
use App\Models\User;
use App\Models\UserGroup;
use App\Repositories\UserGroupRepository;
use App\Services\BaseService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserGroupService extends BaseService
{
    public function __construct(UserGroupRepository $userGroupRepo)
    {
        $this->userGroupRepo = $userGroupRepo;
    }

    public function getUserGroup($companyId)
    {
        $queryParam = [];
        if (!empty($companyId)) {
            $queryParam['company_id'] = $companyId;
        }

        try {
            $userGroups = $this->userGroupRepo->getUserGroup($queryParam);

            if ($userGroups) {
                return $this->sendResponse('success', $userGroups);
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return $this->sendError(
                trans('message.NOT_COMPLETE')
            );
        }
    }

    /**
     * Create Or Update multi record user group
     *
     * @param User $user
     * @param CreateOrUpdateUserGroupRequest $request
     * @return boolean
     */
    public function createOrUpdateUserGroupsByUser(User $user, CreateOrUpdateUserGroupRequest $request): bool
    {
        try {
            DB::beginTransaction();
            $data = $this->generateDataUserGroups($request->get('data', []), $user);
            foreach ($data as $item) {
                if (!empty($item['user_group_id'])) {
                    UserGroup::updateOrCreate([
                    'user_group_id' => $item['user_group_id']
                ], array_filter($item, function ($key) {
                    return $key != 'user_group_id';
                }, ARRAY_FILTER_USE_KEY));
                }
            }
            DB::commit();
            return true;
        } catch (Exception $ex) {
            DB::rollBack();
            set_log_error('CreateOrUpdateUserGroupsByUser', $ex->getMessage());
        }
        return false;
    }

    private function generateDataUserGroups(array $data = [], User $user): array
    {
        $userId = $user->user_id;
        $companyId = $user->company_id;
        $result = array_map(function ($item) use ($userId, $companyId) {
            $tempData = [];
            if (empty($item['user_group_id'])) {
                $tempData = [
                    'user_group_id' => AppService::generateUUID(),
                    'create_user_id' => $userId,
                    'update_user_id' => $userId,
                    'company_id' => $companyId,
                    'user_group_name' => $item['user_group_name'] ?? '',
                    'remarks' => $item['remarks'] ?? '',
                ];
            } elseif (!empty($item['user_group_id'])) {
                $tempData = [
                    'user_group_id' => $item['user_group_id'] ?? '',
                    'update_user_id' => $userId,
                    'user_group_name' => $item['user_group_name'] ?? '',
                    'remarks' => $item['remarks'] ?? '',
                ];
            }
            return $tempData;
        }, $data);

        return $result;
    }

    /**
     * delete user group by user_group_id
     *
     * @param string $userGroupId
     * @return boolean
     */
    public function deleteUserGroupByUserGroupId(string $userGroupId): bool
    {
        return $this->userGroupRepo->deleteByField('user_group_id', $userGroupId);
    }


    /**
     * get user by company id
     *
     * @param [type] $companyId
     * @return void
     */
    public function getUserGroupByCompanyId($companyId)
    {
        return $this->userGroupRepo->getInstance()
                    ->where('company_id', $companyId)
                    ->orderBy('user_group_name', 'asc')
                    ->get();
    }

    public function getListUserGroupByCompanyId(string $companyId, $orderBy = ['user_group_name', 'asc'])
    {
        $orderBy =validateOrderByHelper($orderBy, ['user_group_name', 'asc']);
        $model = $this->userGroupRepo->getModel();
        return  $model::where('company_id', $companyId)
                    ->orderBy($orderBy[0], $orderBy[1])
                    ->get();
    }



    private $userGroupRepo;
}
