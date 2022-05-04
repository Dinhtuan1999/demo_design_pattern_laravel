<?php

namespace App\Services;

use App\Models\BookmarkCompany;
use App\Repositories\CreditCardInfoRepository;
use App\Repositories\ProjectRepository;
use Carbon\Carbon;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactResponse;
use App\Models\User;
use App\Models\Kind;
use Illuminate\Http\Request;
use App\Services\BaseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Repositories\CompanyRepository;
use Illuminate\Support\Facades\Validator;
use App\Services\AppService;
use App\Services\EmailService;
use App\Repositories\UserRepository;
use App\Repositories\CompanySearchKeywordRepository;
use App\Repositories\FreePeriodUseCompanyRepository;
use App\Repositories\BlockCompanyRepository;
use App\Repositories\BookmarkCompanyRepository;
use App\Repositories\ContactRepository;
use App\Repositories\KindRepository;
use App\Repositories\NumberOfEmployeeRepository;
use App\Repositories\UserGroupRepository;
use App\Repositories\UserDispColorRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class CompanyService extends BaseService
{
    private $companyRepo;
    private $blockKeywordRepo;
    private $userRepo;
    private $emailService;
    private $kindRepository;
    private $creditCardInfoRepository;
    private $projectRepo;
    private $userGroupRepository;
    private $userDispColorRepo;
    private $companySearchKeywordRepo;
    private $contactRepository;
    private $bookmarkCompanyRepository;
    private $numberOfEmployeeRepository;
    private $freePeriodUseCompanyRepository;
    public function __construct(
        CompanyRepository        $companyRepo,
        BlockCompanyRepository   $blockKeywordRepo,
        UserRepository           $userRepo,
        EmailService             $emailService,
        KindRepository           $kindRepository,
        CreditCardInfoRepository $creditCardInfoRepository,
        ProjectRepository        $projectRepo,
        UserGroupRepository      $userGroupRepository,
        UserDispColorRepository  $userDispColorRepo,
        CompanySearchKeywordRepository $companySearchKeywordRepo,
        ContactRepository $contactRepository,
        BookmarkCompanyRepository $bookmarkCompanyRepository,
        NumberOfEmployeeRepository $numberOfEmployeeRepository,
        FreePeriodUseCompanyRepository $freePeriodUseCompanyRepository
    ) {
        $this->companyRepo = $companyRepo;
        $this->blockKeywordRepo = $blockKeywordRepo;
        $this->userRepo = $userRepo;
        $this->emailService = $emailService;
        $this->kindRepository = $kindRepository;
        $this->creditCardInfoRepository = $creditCardInfoRepository;
        $this->projectRepo = $projectRepo;
        $this->userGroupRepository = $userGroupRepository;
        $this->emailService = $emailService;
        $this->userDispColorRepo = $userDispColorRepo;
        $this->companySearchKeywordRepo = $companySearchKeywordRepo;
        $this->contactRepository = $contactRepository;
        $this->bookmarkCompanyRepository = $bookmarkCompanyRepository;
        $this->numberOfEmployeeRepository = $numberOfEmployeeRepository;
        $this->freePeriodUseCompanyRepository = $freePeriodUseCompanyRepository;
    }

    /**
     * S.A010.1 Validate register company use Task Management
     *
     * @param Request $request
     * @param Booleans $captchaRequired
     * @return Validator
     */
    public function validateRegisterTaskManagement(Request $request, $captchaRequired = true)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|max:100',
            'mail_address' => 'required|email|max:254|unique:t_company',
            'g-recaptcha-response' => $captchaRequired ? 'required|recaptcha' : '',
            'checkbox_term' => 'accepted',
        ]);
        $validator->setAttributeNames([
            'company_name' => trans('label.company.company_name'),
            'mail_address' => trans('label.company.mail_address'),
            'g-recaptcha-response' => trans('label.general.recaptcha'),
            'checkbox_term' => trans('label.general.checkbox_term')
        ]);

        return $validator;
    }

    /**
     * S.A020.1 Validate register company use Company Management
     *
     * @param Request $request
     * @return Validator
     */
    public function validateRegisterCompanyManagement(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|max:100',
            'mail_address' => 'required|email|max:254|unique:t_company',
            'county_id' => 'required|max:36',
            'city' => 'required|max:100',
            'address' => [
                'required',
                'max:300',
                // config('validate.hiragana_Katakana_fullwidth_and_kanji_alphanumeric'),
            ],
            'last_name' => [
                'required',
                'max:30',
                // config('validate.hiragana_Katakana_fullwidth_and_kanji'),
            ],
            'first_name' => [
                'required',
                'max:30',
                // config('validate.hiragana_Katakana_fullwidth_and_kanji'),
            ],
            'last_name_kana' => [
                'required',
                'max:30',
                config('validate.katakana_fullwidth'),
            ],
            'first_name_kana' => [
                'required',
                'max:30',
                config('validate.katakana_fullwidth'),
            ],
            'tel_no' => 'required|string|min:10|max:15',
            'checkbox_term' => 'accepted',
        ]);

        return $validator;
    }

    /**
     * S.A030.1 Register company use Company Management
     *
     * @param Request $request
     * @return array
     */
    public function registerCompanyManagement(Request $request)
    {
        $validator = $this->validateRegisterCompanyManagement($request);
        if ($validator->fails()) {
            return [
                'status' => config('apps.general.error'),
                'message' => $validator->messages()->all()
            ];
        }

        $companyInfo = $request->all();
        $companyInfo['task_manage_use_flg'] = config('apps.company.task_manage_use_flg_not');
        $companyInfo['company_info_search_user_flg'] = config('apps.company.company_search_open_flg');
        return $this->createCompnay($companyInfo);
    }

    /**
     * S.A030.2 Register company use Task Management
     *
     * @param Request $request
     * @return array
     */
    public function registerTaskManagement(Request $request)
    {
        $validator = $this->validateRegisterTaskManagement($request, false);
        if ($validator->fails()) {
            return [
                'status' => config('apps.general.error'),
                'message' => $validator->messages()->all()
            ];
        }

        $companyInfo = $request->all();
        $companyInfo['task_manage_use_flg'] = config('apps.company.task_manage_use_flg');
        $companyInfo['company_info_search_user_flg'] = config('apps.company.company_search_open_flg_not');
        return $this->createCompnay($companyInfo);
    }

    /**
     * Create company record
     *
     * @param array $compnayInfo
     * @return mixed
     */
    public function createCompnay($companyInfo)
    {
        $companyInfo['company_id'] = AppService::generateUUID();
        try {
            $userResult = DB::transaction(function () use ($companyInfo) {

                // create first user of company
                $companyInfo['user_id'] = AppService::generateUUID();
                $color =  $this->userDispColorRepo->getModel()::where('delete_flg', config('apps.general.not_deleted'))->first();
                $companyInfo['display_color_id'] = $color->disp_color_id;
                $companyInfo['service_contractor_auth_flg'] = config('apps.user.is_service_contractor_auth');
                $user = $this->userRepo->getInstance();
                $user->fill($companyInfo)->save();

                // initial default value for company
                $companyInfo['contract_license_num'] = 0;
                $companyInfo['temp_application_flg'] = config('apps.company.temp_application_flg');
                $companyInfo['company_status'] = config('apps.company.company_status_trial');

                $companyInfo['login_key'] = $this->generateLoginKey();
                $companyInfo['representative_id'] = $user->user_id;
                $companyInfo['create_user_id'] = $user->user_id;

                // create company
                $company = $this->companyRepo->getModel()::create($companyInfo);
                // create time free period company
                $now = Carbon::now();
                $endDate ='';
                // if it is first day of month then end date will end of month
                if ($now->day ==  config('apps.company.start_of_month')) {
                    $endDate = $now->endOfMonth();
                } else {
                    $endDate = $now->addDays(config('apps.company.free_period_use_company'));
                }
                $freePeriodUseCompany['company_id'] = $company->company_id;
                $freePeriodUseCompany['free_period_start_date'] = Carbon::now();
                $freePeriodUseCompany['free_peiod_end_date'] = $endDate;
                $freePeriodUseCompany['create_datetime'] = date('Y-m-d H:i:s');
                $freePeriodUseCompany['update_user_id'] = $user->user_id;
                $this->freePeriodUseCompanyRepository->getModel()::create($freePeriodUseCompany);
                return $user;
            }, 5);

            if ($userResult->wasRecentlyCreated) {

                // @TODO Call job send email (Batch No 2) instead of sending it directly
                $this->emailService->sendEmailVerifyRegisterCompany($userResult->mail_address, $userResult->user_id);

                return self::sendResponse(
                    [trans('message.INF_COM_0001')],
                    ['user_id' => $userResult->user_id]
                );
            }

            return self::sendError([trans('message.ERR_COM_0008')]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return self::sendError([trans('message.ERR_COM_0008')]);
        }
    }

    /**
     * Generate login key, format YYYYMM***
     *
     * @return String login key
     */
    public function generateLoginKey()
    {
        $latestLoginKey = $this->companyRepo->getModel()::orderBy('login_key', 'desc')->limit(1)->first(['login_key']);
        if (is_null($latestLoginKey)) {
            $sequenceNumber = 1;
        } else {
            $latestLoginKey = $latestLoginKey->toArray();
            $sequenceNumber = substr($latestLoginKey['login_key'], -3);
            $sequenceNumber = $sequenceNumber == '999' ? 1 : $sequenceNumber + 1;
        }

        return date('Ym') . str_pad($sequenceNumber, 3, "0", STR_PAD_LEFT);
    }

    /**
     * Get company login key by user Id
     *
     * @param String $userId
     * @return string or null
     */
    public function getLoginKeyByUserId($userId)
    {
        $result = $this->companyRepo->getModel()::join(
            't_user',
            't_user.company_id',
            '=',
            't_company.company_id'
        )
            ->where('t_user.user_id', $userId)
            ->first(['t_company.login_key']);
        if ($result) {
            return $result['login_key'];
        }

        return null;
    }

    /**
     * Get Company Information
     *
     * @param string $companyId
     * @return array
     */
    public function getCompanyInformation($companyId)
    {
        try {
            if (empty($companyId)) {
                return $this->sendError(
                    trans('message.NOT_COMPLETE')
                );
            }
            // Call getCompanyInformation function in companyRepository to get company information
            $company = $this->companyRepo->getByCol('company_id', $companyId, [Company::USER_GROUPS, Company::BLOCK_COMPANIES]);

            if (empty($company)) {
                return $this->sendError(
                    trans('message.NOT_COMPLETE')
                );
            }
            return $this->sendResponse(trans('message.COMPLETE'), $company);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.NOT_COMPLETE')
            );
        }
    }

    /**
     * Update Company Information
     *
     * @param string $companyId
     * @return array
     */
    public function updateCompanyInformation($updateData = [], $companyId)
    {
        try {
            DB::beginTransaction();

            if (isset($params['company_id'])) {
                unset($params['company_id']);
            }
            // Check empty companyId and company is exists
            if (empty($companyId) && !$this->companyRepo->isExists($companyId)) {
                return $this->sendError(trans('message.NOT_COMPLETE'));
            }

            if (isset($updateData['block_keywords'])) {
                $blockKeywordsCreate = [];
                $blockKeywordsUpdate = [];
                $blockKeywordIdsNotDelete = [];

                foreach ($updateData['block_keywords'] as $blockKeyword) {
                    // Create data
                    if (empty($blockKeyword['block_keyword_id'])) {
                        // Make string id for block_keyword
                        $blockKeyword['block_keyword_id'] = AppService::generateUUID();
                        // Set to current company_id
                        $blockKeyword['company_id'] = $companyId;
                        $blockKeyword['create_datetime'] = Carbon::now();
                        $blockKeywordsCreate[] = $blockKeyword;
                    } // Update data
                    else {
                        $blockKeyword['company_id'] = $companyId;
                        $blockKeywordsUpdate[] = $blockKeyword;
                        $blockKeywordIdsNotDelete[] = $blockKeyword['block_keyword_id'];
                    }
                }
                // Delete Block Keywords
                $this->blockKeywordRepo->deleteMissingBlockKeywords($blockKeywordIdsNotDelete);
                // Create Block keywords
                if ($blockKeywordsCreate) {
                    $this->blockKeywordRepo->insertMultiRecord($blockKeywordsCreate);
                }
                // Update Block Keywords
                if ($blockKeywordsUpdate) {
                    foreach ($blockKeywordsUpdate as $blockKeyword) {
                        $this->blockKeywordRepo->updateByField('block_keyword_id', $blockKeyword['block_keyword_id'], $blockKeyword);
                    }
                }
            }

            // update company_blocks
            $company = $this->companyRepo->findByField('company_id', $companyId);
            if ($company) {
                if (isset($updateData['company_search_open_flg']) && $updateData['company_search_open_flg'] == config('apps.company.company_search_open_flg')) {
                    $company->block_companies()->delete();
                }
                if (!empty($updateData['company_blocks'])) {
                    $company->block_companies()->createMany(generateDataCompanyBlockHelper(array_filter($updateData['company_blocks'], function ($item) {
                        return !empty($item) || strlen($item);
                    })));
                }
            }

            // // Update company by updateByField from companyRepo
            // $updateResult = $this->companyRepo->updateByField('company_id', $companyId, $updateData);
            // Update company by updateByField from companyRepo
            $updateResult = $this->companyRepo->updateByField('company_id', $companyId, $updateData);

            if (!$updateResult) {
                DB::rollBack();
                return $this->sendError(
                    trans('message.NOT_COMPLETE')
                );
            }

            DB::commit();
            return $this->sendResponse(trans('message.COMPLETE'));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.NOT_COMPLETE')
            );
        }
    }

    /**
     * Search Information of Company
     *
     * @return array
     */
    public function searchCompanyInformation(
        $company_id,
        $keyword = null,
        $group_project_atrr_id = null,
        $include_contact_receive = 0,
        $include_contact_send = 0,
        $sort_by = null
    ) {
        try {
            $companyNameCurrent = $this->companyRepo->getByCol('company_id', $company_id);
            $getListKeyWordCompany = $this->companySearchKeywordRepo->all(['company_search_keyword' => $companyNameCurrent->company_name], [], [], ['company_id'])->pluck('company_id')->toArray();

            $companies = $this->companyRepo->getInstance()->query()
                ->leftjoin('m_county', 'm_county.county_id', '=', 't_company.county_id')
                ->select('t_company.company_id', 't_company.company_name', 't_company.company_name_open_flg', 't_company.city', 't_company.pr_information', 't_company.hp_url', 'm_county.county_name')
                ->where('t_company.company_search_open_flg', config('apps.company.company_search_open_flg'))
                ->where('t_company.company_id', '!=', $company_id)
                ->where('t_company.company_status', config('apps.company.company_status_payed'))
                ->withCount(Company::CONTACT_RESPONSES . ' AS is_reception')
                ->withCount(Company::CONTACT_SENDS . ' AS is_delivery')
                ->withCount(Company::PROJECTS . ' AS total_project')
                ->withCount(Company::TASKS . ' AS total_task');

            // search by keywork
            if ($keyword) {
                $companies = $companies->where(function ($query) use ($keyword) {
                    $query->orWhere('t_company.company_name', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('t_company.pr_information', 'LIKE', '%' . $keyword . '%');
                });
            }

            if ($getListKeyWordCompany && !empty($getListKeyWordCompany)) {
                $companies = $companies->whereNotIn('t_company.company_id', $getListKeyWordCompany);
            }

            // filter by t_project_owned_attribute
            if ($group_project_atrr_id && is_array($group_project_atrr_id)) {
                $companies = $companies->whereHas(Company::PROJECTS, function ($q) use ($group_project_atrr_id) {
                    $q->join('t_project_owned_attribute', 't_project.project_id', '=', 't_project_owned_attribute.project_id')
                        ->whereIn('t_project_owned_attribute.project_attribute_id', $group_project_atrr_id);
                });
            }

            // filter by include company contact resposes
            if ($include_contact_receive == 0  && $include_contact_send == 0) {
                $companies->doesntHave(Company::CONTACT_RESPONSES);
            } elseif ($include_contact_receive == 1) {
                $companies->whereHas(Company::CONTACT_RESPONSES, function ($query) use ($company_id) {
                    $query->where('t_contact.sender_company_id', '!=', $company_id);
                })
                    ->having('is_reception', '>', 0);
            }

            // filter by include company contact sends
            if ($include_contact_send == 0 && $include_contact_receive == 0) {
                $companies->doesntHave(Company::CONTACT_SENDS);
            } elseif ($include_contact_send == 1) {
                $companies->whereHas(Company::CONTACT_SENDS, function ($query) use ($company_id) {
                    $query->where('t_contact.sender_company_id', '!=', $company_id);
                })
                    ->having('is_delivery', '>', 0);
            }

            // Sort by
            if (is_null($sort_by) || $sort_by == 'update_datetime') {
                $companies = $companies->orderBy('t_company.update_datetime', 'DESC');
            } else {
                $companies = $companies->orderBy($sort_by, 'DESC');
            }

            // execute query
            $companies = $companies->get()->toArray();

            // replace is_delivery, is_reception, company_name
            foreach ($companies as &$item) {
                $item['is_delivery'] = isset($item['is_delivery']) && $item['is_delivery'] > 0 ? true : false;
                $item['is_reception'] = isset($item['is_reception']) && $item['is_reception'] > 0 ? true : false;
                $item['company_name'] = $item['company_name_open_flg'] == config('apps.company.company_name_open_flg') ? $item['company_name'] : trans('message.COMPANY_NAME_NOT_PUBLIC');
            }

            return $this->sendResponse(trans('message.COMPLETE'), $companies);
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());
            return $this->sendError(
                trans('message.ERR_EXCEPTION')
            );
        }
    }

    /**
     * get list member user by company id
     * G010
     * @param string $companyId
     * @param string|null $keyword
     * @param array|null $userGroupIds
     * @param integer $deleteFlg
     * @param integer $page
     * @return collection
     */
    public function getListMembersByCompanyId(string $companyId, ?string $keyword, ?array $userGroupIds = [], int $deleteFlg = 0, int $page = 1)
    {
        $query = User::query()->with([
            'user_group',
            'all_projects' => function ($q) {
                $q->orderBy('project_name', 'asc');
            },
            'all_projects.task_groups' => function ($q) {
                $q->orderBy('group_name', 'asc');
            },
            'all_projects.task_groups.disp_color',
        ])->select(
            't_user.user_id',
            't_user.disp_name',
            't_user.icon_image_path',
            't_user.mail_address',
            't_user_group.user_group_name',
            't_user_group.user_group_id'
        )->where(
            't_user.company_id',
            $companyId
        )->leftJoin('t_user_group', function ($joinUG) {
            $joinUG->on('t_user.user_group_id', '=', 't_user_group.user_group_id');
        });

        // where by delete flg
        $query->where('t_user.delete_flg', $deleteFlg);

        // filter by keyword
        if (!empty($keyword)) {
            $query->where(function ($q) use ($keyword) {
                $q->where('t_user.disp_name', 'like', '%' . $keyword . '%')
                    ->orWhere('t_user.mail_address', 'like', '%' . $keyword . '%');
            });
        }

        // filter by user group id
        if (!empty($userGroupIds)) {
            $query->whereIn('t_user.user_group_id', $userGroupIds);
        }
        // filter by user project attribute id
        // if (!empty($projectAttributeIds)) {
        //     $query->whereHas('projects', function ($queryP) use ($projectAttributeIds) {
        //         $queryP->whereHas('project_attributes', function ($queryPA) use ($projectAttributeIds) {
        //             $queryPA->whereIn('m_project_attribute.project_attribute_id', $projectAttributeIds);
        //         });
        //     });
        // }

        $query->orderBy('t_user.disp_name', 'ASC');

        return $query->paginate(config('apps.company.member.per_page'));
    }

    /**
     * H010 Get List Company Bookmark
     *
     * @return array
     */
    public function listCompanyBookmark($user_id)
    {
        try {
            $listCompanyBookmark = $this->companyRepo->getInstance()->query()
                ->leftjoin('m_county', 'm_county.county_id', '=', 't_company.county_id')
                ->join('t_bookmark_company', 't_bookmark_company.company_id', '=', 't_company.company_id')
                ->where('t_bookmark_company.user_id', $user_id)
                ->where('t_bookmark_company.delete_flg', config('apps.general.not_deleted'))
                ->select('t_company.company_id', 't_bookmark_company.display_order', 't_company.company_name', 't_company.company_name_open_flg', 't_company.pr_information', 't_bookmark_company.create_datetime', 'm_county.county_name')
                ->orderBy('t_bookmark_company.display_order', 'ASC')
                ->get()->toArray();

            // replace company_name
            foreach ($listCompanyBookmark as $key => &$item) {
                $item['company_name'] = $item['company_name_open_flg'] == config('apps.company.company_name_open_flg') ? $item['company_name'] : trans('message.COMPANY_NAME_NOT_PUBLIC');
                unset($item['company_name_open_flg']);
            }

            return $this->sendResponse(trans('message.COMPLETE'), $listCompanyBookmark);
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());
            return $this->sendError(
                trans('message.ERR_EXCEPTION')
            );
        }
    }

    public function getListKindsByCompanyId(string $companyId)
    {
        $kinds = $this->kindRepository->getInstance()->query()
            ->select('m_kinds.kinds_id', 'm_kinds.kinds_name')
            ->with(['project_attributes' => function ($q) use ($companyId) {
                $q->select('kinds_id', 'project_attribute_id', 'project_attribute_name');
                $q->orderBy('project_attribute_name', 'asc');
                $q->whereHas('projects', function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                });
            }])
            ->orderBy('m_kinds.kinds_id', 'asc')
            ->get();

        return $kinds;
    }

    /**
     * get member detail by user id (with graph)
     *
     * @param string $userId
     * @param array $projectAttributeIds
     * @param array $orderBy
     * @return void
     */
    public function getMemberDetailByUserId(string $userId, $projectAttributeIds = [], $orderBy = [])
    {
        $orderByDefault = ['project_name', 'asc'];
        $orderBy = validateOrderByHelper($orderBy, $orderByDefault);
        $memberDetail = $this->userRepo->getInstance()->query()
            ->leftJoin('t_user_group', function ($joinUG) {
                $joinUG->on('t_user.user_group_id', '=', 't_user_group.user_group_id');
            })
            ->select(
                't_user.user_id',
                't_user.disp_name',
                't_user.mail_address',
                't_user_group.user_group_name',
                't_user.user_group_id',
                't_user.icon_image_path'
            )
            ->with([
                'all_projects' => function ($q) use ($orderBy, $projectAttributeIds) {
                    if (!empty($projectAttributeIds) && is_array($projectAttributeIds)) {
                        $q->whereHas('project_attributes', function ($queryPA) use ($projectAttributeIds) {
                            $queryPA->whereIn('m_project_attribute.project_attribute_id', $projectAttributeIds);
                        });
                    }
                    $q->orderBy($orderBy[0], $orderBy[1]);
                },
                'all_projects.task_groups' => function ($q) {
                    $q->orderBy('group_name', 'asc');
                },
                'all_projects.task_groups.disp_color',
                'all_projects.task_groups.tasks',
                'all_projects.project_attributes.kind'
            ])
            ->where('t_user.user_id', $userId)
            ->first();
        return $memberDetail;
    }

    public function updateMultiUser($dataUserRequest, $companyId)
    {
        DB::beginTransaction();

        try {
            foreach ($dataUserRequest as $user) {

                // update
                if (!empty($user['user_id'])) {
                    $this->userRepo->updateByField('user_id', $user['user_id'], $user);
                }
                // add user
                if (empty($user['user_id']) && !empty($user['mail_address'])) {
                    $user['user_id'] = AppService::generateUUID();
                    $user['disp_name'] = $user['mail_address'] ?? '';
                    $user['company_id'] = $companyId;
                    $user['login_password'] = Hash::make(config('apps.general.default_pass'));
                    // set color default
                    $color =  $this->userDispColorRepo->getModel()::where('delete_flg', config('apps.general.not_deleted'))->first();
                    $user['display_color_id'] = $color->disp_color_id;
                    $this->userRepo->getModel()::create($user);
                    // send email
                    /**
                     * .
                     * .
                     */
                    $this->emailService->sendEmailVerifyRegisterCompany($user['mail_address'], $user['user_id']);
                }
                // delete
                if (!empty($user['user_id']) && !empty($user['delete'])) {
                    $this->userRepo->updateByField('user_id', $user['user_id'], ['delete_flg' => valueDeleteFlg()]);
                }
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            set_log_error('updateMultiUser', $th->getMessage());
            return false;
        }
        DB::commit();
        return true;
    }

    /**
     * add payment method for company
     *
     * @param array $data
     * @return array
     */
    public function addUserPayment(array $data)
    {
        try {
            $paymentMethod = $this->creditCardInfoRepository->findByField('company_id', $data['company_id']);

            if ($paymentMethod) {
                $this->creditCardInfoRepository->update($paymentMethod, $data);
            } else {
                $this->creditCardInfoRepository->store($data);
            }

            return $this->sendResponse(trans('message.COMPLETE'));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.ERR_EXCEPTION')
            );
        }
    }

    /**
     * get info company with user groups, block company
     *
     * @param string $companyId
     * @return object
     */
    public function getCompanyInfoByCompanyId(string $companyId)
    {
        return $this->companyRepo->findByField('company_id', $companyId, [Company::USER_GROUPS, Company::BLOCK_COMPANIES]);
    }

    public function getDisclosureStatus($request)
    {
        if (empty($request->company_id) && !$this->companyRepo->isExists($request->company_id)) {
            return $this->sendError(trans('message.NOT_COMPLETE'));
        }

        try {
            $company = $this->companyRepo->getModel()::where('t_company.company_id', $request->company_id)
                ->select('t_company.company_id', 't_company_info_reference_num.company_info_reference_num', )
                ->join('t_company_info_reference_num', 't_company_info_reference_num.company_id', '=', 't_company.company_id')
                ->withCount([
                    'bookmark_companies as number_of_bookmark',
                    'contact_responses as number_contact_receive' => function ($query) {
                        $query->where('t_contact.delete_flg', config('apps.general.not_deleted'))->orWhereNull('t_contact.delete_flg');
                        ;
                    },
                    'contact_responses_reject as number_contact_reject',
                    'contact_responses_approve as number_contact_approve',
                    'contact_responses_not_answer as number_contact_not_answer',
                    'contact_sends as number_contact_send' => function ($query) {
                        $query->where('t_contact.delete_flg', config('apps.general.not_deleted'))->orWhereNull('t_contact.delete_flg');
                        ;
                    },
                ])
                ->addSelect('t_company_info_reference_num.company_info_reference_num')
                ->with([
                    'projects'
                    => function ($q) {
                        $q->select('project_id', 'project_name', 'project_name_public', 'actual_start_date', 'actual_end_date', 'project_overview_public', 'company_id')
                            ->withCount([
                                'project_participants as number_of_person' => function ($query) {
                                    $query->where('delete_flg', config('apps.general.not_deleted'));
                                },
                                'tasks as number_of_tasks' => function ($query) {
                                    $query->where('delete_flg', config('apps.general.not_deleted'));
                                },
                            ])
                            ->where('company_search_target_flg', config('apps.general.company_search_flg'))
                            ->where('delete_flg', config('apps.general.not_deleted'))
                            ->orderBy('create_datetime', 'desc');
                    }
                ])
                ->First();
            return $this->sendResponse(trans('message.COMPLETE'), $company);
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());
            return $this->sendError(
                trans('message.ERR_EXCEPTION')
            );
        }
    }

    public function getListUserGroupByCompanyId($companyIds)
    {
        try {
            // get all user group
            if (empty($companyIds)) {
                $userGroups = $this->userGroupRepository->getModel()::get();
                return $this->sendResponse(trans('message.COMPLETE'), $userGroups);
            }
            // get user group by company id
            $userGroups = $this->userGroupRepository->getModel()::whereIn('company_id', $companyIds)
                ->orderBy('user_group_name', 'desc')
                ->get();
            if ($userGroups->count() == 0) {
                return $this->sendError([trans('message.ERR_COM_0011', ['attribute' => 't_user_group'])]);
            }
            return $this->sendResponse(trans('message.COMPLETE'), $userGroups);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.ERR_EXCEPTION')
            );
        }
    }


    public function getDetailsInformation(string $companyId)
    {
        try {
            $userId = Auth::user()->user_id;
            // 1. Get company details, Count number of response mails, number of send mails
            $company = $this->companyRepo->getDetailsInformation($companyId, $userId);

            if (empty($company)) {
                return $this->sendError(trans('message.INF_COM_0003'));
            }
            $company->number_of_projects = 0;
            $company->number_of_tasks = 0;
            $company->projects = [];
            $company->is_bookmarked =  $this->bookmarkCompanyRepository->getInstance()::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('delete_flg', '=', config('apps.general.not_deleted'))
            ->exists();
            $company->is_delivery = $company->number_send_contact > 0 ? true : false;
            $company->is_reception = $company->number_response_contact > 0 ? true : false;
            $userCompanyId = $this->numberOfEmployeeRepository
                ->getInstance()::where('number_of_employees_id', $company->number_of_employees_id)
                ->where('delete_flg', config('apps.general.not_deleted'))->first();
            $number_of_employees = ($userCompanyId) ? $userCompanyId->number_of_employees : 0;
            $contactSend = $this->contactRepository->getInstance()::where('sender_company_id', $companyId)
            ->where('delete_flg', config('apps.general.not_deleted'))->get();
            $contactDes = $this->contactRepository->getInstance()::where('destination_company_id', $companyId)
            ->where('delete_flg', config('apps.general.not_deleted'))->get();
            $contactSend = count($contactSend);
            $contactDes =  count($contactDes);
            // 2. Get all projects of company with number of members, number of tasks
            $companyProjects = $this->projectRepo->getAllProjectOfCompanyWithTasks($companyId);


            $companyCurent = $this->companyRepo->getInstance()->query()
                ->leftjoin('m_county', 'm_county.county_id', '=', 't_company.county_id')
                ->select('t_company.company_id', 't_company.company_name', 't_company.company_name_open_flg', 't_company.city', 't_company.pr_information', 't_company.hp_url', 'm_county.county_name')
                ->where('t_company.company_search_open_flg', config('apps.company.company_search_open_flg'))
                ->where('t_company.company_id', '=', $companyId)
                ->withCount(Company::CONTACT_RESPONSES . ' AS is_reception')
                ->withCount(Company::CONTACT_SENDS . ' AS is_delivery')
                ->withCount(Company::PROJECTS . ' AS total_project')
                ->withCount(Company::TASKS . ' AS total_task')->first();

            // 3. Calculate number of projects, number of all tasks
            if ($companyProjects->isNotEmpty()) {
                $company->number_of_projects = $companyCurent->total_project;
                $company->number_of_tasks = $companyCurent->total_task;
                $company->projects = $companyProjects->toArray();
                $company->contactSend = $contactSend;
                $company->countUserInCompanyId =  $number_of_employees;
                $company->contactDes = $contactDes;
                $company->county_name = $companyCurent->county_name;
            }
            return $this->sendResponse(trans('message.COMPLETE'), $company->toArray());
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.ERR_EXCEPTION')
            );
        }
    }
    public function getGraphByCompany(string $companyId)
    {
        try {
            $dataGraph = [];
            $kindList = $this->kindRepository->getAll();
            // 1. Get company details, Count number of response mails, number of send mails
            $company = $this->companyRepo->getByCol('company_id', $companyId);
            if (empty($company)) {
                return $this->sendError(trans('message.ERR_COM_0011', ['attribute' => 'Company']));
            }
            // 2. Get all projects of company
            $companyProjects = $this->projectRepo->getAllProjectOfCompanyWithAttributes($companyId);

            $kinds = $this->kindRepository->getInstance()::orderBy('display_order', 'ASC')->
            where('delete_flg', config('apps.general.not_deleted'))
            ->get()->pluck('kinds_name', 'kinds_id')->toArray();

            // 3. Calculate number of projects, number of all tasks
            if ($companyProjects->isEmpty()) {
                return $this->sendError(trans('message.INF_COM_0003'));
            }
            foreach ($companyProjects as $project) {
                if (!empty($project->project_attributes) && $project->project_attributes->isNotEmpty()) {
                    foreach ($project->project_attributes as $projectAttributes) {
                        foreach ($kinds as $key => $kind) {
                            if (isset($dataGraph[$key]['array_item'][$key])) {
                                // Increase data
                                $dataGraph[$key]['array_item'][$key]['number_attribute'] += 1;
                            } else {

                                // Kind name

                                $dataGraph[$key]['kinds_name'] = !empty($kinds[$key]) ? $kinds[$key] : null;
                                // Data graph

                                foreach ($kindList as $kind) {
                                    if ($kinds[$key] == $kind->kinds_name) {
                                        $dataGraph[$key]['color_code'] = $kind->color_code;
                                        $dataGraph[$key]['display_order'] = $kind->display_order;
                                    }
                                }
                                $dataGraph[$projectAttributes->kinds_id]['array_item'][$projectAttributes->project_attribute_id] = [
                                    'project_attribute_name' => $projectAttributes->project_attribute_name,
                                    'number_attribute' => 1
                                ];
                                // Graph type
                                $dataGraph[$projectAttributes->kinds_id]['graph_type'] = 'pie';
                            }
                        }
                    }
                }
            }
            return $this->sendResponse(trans('message.COMPLETE'), array_values($dataGraph));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError(
                trans('message.ERR_EXCEPTION')
            );
        }
    }

    public function validateDetailsInformation(Request $request)
    {
        return Validator::make(
            $request->all(),
            [
                'company_id' => [
                    'required',
                    Rule::exists('t_company', 'company_id')->where(function ($query) {
                        return $query->where('delete_flg', config('apps.general.not_deleted'));
                    }),
                ],
            ],
            [
                'company_id.required' => trans('message.ERR_COM_0001', ['attribute' => trans('label.company.company_id')]),
                'company_id.exists' => trans('message.ERR_COM_0011', ['attribute' => trans('label.company.company_id')]),
            ]
        );
    }
}
