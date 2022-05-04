<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    public const USER_REPRESENTATIVE = 'user_representative';

    public const COUNTIES = 'counties';

    public const INDUSTRIES = 'industries';

    public const USERS = "users";

    public const USER_GROUPS = "user_groups";

    public const LICENCE_MANAGEMENTS = 'licence_managements';

    public const PAYMENT_HISTORIES = 'payment_histories';

    public const PROJECTS = 'projects';

    public const TASKS = 'tasks';

    public const BOOKMARK_COMPANIES = 'bookmark_companies';

    public const COMPANY_INFO_REFERENCE_NUMBER = 'company_info_reference_number';

    public const FREE_PERIOD_USE_COMPANY = 'free_period_use_company';

    public const ROLE_MSTS = 'role_msts';

    public const CONTACT_SENDS = 'contact_sends';

    public const CONTACT_RESPONSES = 'contact_responses';

    public const CREDIT_CARD_INFOS = 'credit_card_infos';

    public const BLOCK_COMPANIES = 'block_companies';

    public const CONTRACTS = 'contracts';

    public const COMPANY_SEARCH_KEYWORDS = 'company_search_keywords';

    public const CREATED_AT = 'create_datetime';

    public const UPDATED_AT = 'update_datetime';

    public const FIELDS  =
    [
        'company_id',
        'company_name',
        'login_key',
        'number_of_employees_id',
        'industry_id',
        'hp_url',
        'pr_information',
        'mail_address',
        'tel_no',
        'post_code',
        'county_id',
        'city',
        'address',
        'representative_id',
        'last_name',
        'first_name',
        'last_name_kana',
        'first_name_kana',
        'position',
        'company_search_open_flg',
        'company_name_open_flg',
        'payment_reference_date',
        'contract_start_date',
        'contract_end_date',
        'contract_license_num',
        'task_manage_use_flg',
        'company_info_search_user_flg',
        'temp_application_flg',
        'company_status',
        'delete_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    protected $fillable = self::FIELDS ;

    protected $primaryKey = 'company_id';

    protected $table = 't_company';

    public $incrementing = false;

    public function user_representative()
    {
        return $this->belongsTo(User::class, 'representative_id', 'user_id');
    }

    public function counties()
    {
        return $this->belongsTo(Company::class, 'county_id', 'county_id');
    }

    public function industries()
    {
        return $this->belongsTo(Industry::class, 'industry_id', 'industry_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'company_id', 'company_id');
    }

    public function user_groups()
    {
        return $this->hasMany(UserGroup::class, 'company_id', 'company_id');
    }

    public function licence_managements()
    {
        return $this->hasMany(LicenceManagement::class, 'company_id', 'company_id');
    }

    public function payment_histories()
    {
        return $this->hasMany(PaymentHistories::class, 'company_id', 'company_id');
    }

    public function projects()
    {
        return $this->hasMany(Project::class, 'company_id', 'company_id');
    }

    public function tasks()
    {
        return $this->hasManyThrough(
            Task::class,
            Project::class,
            'company_id', // Foreign key on t_projects table...
            'project_id', // Foreign key on t_task table...
            'company_id', // Local key on t_company table...
            'project_id'// Local key on t_project table...
        );
    }

    public function bookmark_companies()
    {
        return $this->hasMany(BookmarkCompany::class, 'company_id', 'company_id');
    }

    public function company_info_reference_number()
    {
        return $this->hasOne(CompanyInfoReferenceNum::class, 'company_id', 'company_id');
    }

    public function free_period_use_company()
    {
        return $this->hasOne(FreePeriodUseCompany::class, 'company_id', 'company_id');
    }

    public function role_msts()
    {
        return $this->hasMany(RoleMst::class, 'company_id', 'company_id');
    }

    public function contact_sends()
    {
        return $this->hasManyThrough(
            ContactSend::class,
            Contact::class,
            'sender_company_id',
            'contact_id',
            'company_id',
            'contact_id'
        );
    }

    public function contact_responses()
    {
        return $this->hasManyThrough(
            ContactSend::class,
            Contact::class,
            'destination_company_id',
            'contact_id',
            'company_id',
            'contact_id'
        );
    }

    public function contact_responses_reject()
    {
        return $this->hasManyThrough(
            ContactSend::class,
            Contact::class,
            'sender_company_id',
            'contact_id',
            'company_id',
            'contact_id'
        )->where('t_contact.consent_classification', config('apps.contact.contact_reject'));
    }

    public function contact_responses_not_answer()
    {
        return $this->hasManyThrough(
            ContactSend::class,
            Contact::class,
            'sender_company_id',
            'contact_id',
            'company_id',
            'contact_id'
        )->where('t_contact.consent_classification', config('apps.contact.contact_not_answer'));
    }

    public function contact_responses_approve()
    {
        return $this->hasManyThrough(
            ContactSend::class,
            Contact::class,
            'sender_company_id',
            'contact_id',
            'company_id',
            'contact_id'
        )->where('t_contact.consent_classification', config('apps.contact.contact_approve'));
    }

    public function credit_card_infos()
    {
        return $this->hasMany(CreditCardInfo::class, 'company_id', 'company_id');
    }

    public function block_companies()
    {
        return $this->hasMany(BlockCompany::class, 'company_id', 'company_id');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class, 'company_id', 'company_id');
    }

    public function company_search_keywords()
    {
        return $this->hasMany(CompanySearchKeyword::class, 'company_id', 'company_id');
    }
}
