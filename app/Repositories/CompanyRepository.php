<?php

namespace App\Repositories;

use App\Models\Company;
use App\Services\AppService;
use Illuminate\Support\Facades\DB;

class CompanyRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(Company::class);
        $this->fields = Company::FIELDS ;
    }

    public function isExists($companyId)
    {
        return $this->getInstance()::where('company_id', $companyId)->exists();
    }

    public function syncBlockKeyWords($companyId, $blockKeywords = [])
    {
        return $this->getInstance()::find($companyId)->block_companies()->sync($blockKeywords);
    }

    public function getDetailsInformation(string $companyId, string $userId = '')
    {
        return $this->getInstance()::where('company_id', "$companyId")
            ->leftJoin('t_contact as contact_send', 't_company.company_id', '=', 'contact_send.sender_company_id')
            ->leftJoin('t_contact as contact_response', 't_company.company_id', '=', 'contact_response.destination_company_id')
            ->leftJoin('m_industry', 't_company.industry_id', '=', 'm_industry.industry_id')
//            ->leftJoin('t_bookmark_company', function ($query) use ($userId) {
//                $query->on('t_bookmark_company.company_id', '=', 't_company.company_id')
//                      ->on('t_bookmark_company.user_id', '=', $userId);
//            })
            ->select(
                't_company.*',
                'm_industry.industry_name',
                DB::raw('count(contact_send.contact_id) as number_send_contact'),
                DB::raw('count(contact_response.contact_id) as number_response_contact'),
            )
            ->groupBy(
                't_company.company_id',
                't_company.company_name',
                't_company.login_key',
                't_company.number_of_employees_id',
                't_company.industry_id',
                't_company.hp_url',
                't_company.pr_information',
                't_company.mail_address',
                't_company.tel_no',
                't_company.post_code',
                't_company.county_id',
                't_company.city',
                't_company.address',
                't_company.representative_id',
                't_company.last_name',
                't_company.first_name',
                't_company.last_name_kana',
                't_company.first_name_kana',
                't_company.position',
                't_company.company_search_open_flg',
                't_company.company_name_open_flg',
                't_company.payment_reference_date',
                't_company.contract_start_date',
                't_company.contract_license_num',
                't_company.task_manage_use_flg',
                't_company.company_info_search_user_flg',
                't_company.temp_application_flg',
                't_company.company_status',
                't_company.delete_flg',
                't_company.create_datetime',
                't_company.create_user_id',
                't_company.update_datetime',
                't_company.update_user_id',
                't_company.contract_end_date',
                'm_industry.industry_name'
            )
            ->first();
    }
}
