<?php

namespace App\Models;

use App\Models\Payment\StripeCard;
use App\Models\Payment\StripeUser;
use App\Models\Payment\Subscription;
use App\Scopes\DeleteFlgNotDeleteScope;
use App\Traits\HasScopeExtend;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use Billable;
    use HasScopeExtend;

    public const DISP_COLOR                            = "disp_color";
    public const USER_GROUP                            = "user_group";
    public const COMPANY                               = "company";
    public const URL_AUTHENTICATION_VERIFY             = "url_authentication_verify";
    public const FOLLOWUP                              = "followup";
    public const BREAKDOWN                             = "breakdown";
    public const TASK                                  = "task";
    public const COMPANY_OWNED                         = "company_owned";
    public const MY_PROJECT_DISPLAY_ORDER              = "my_project_display_order";
    public const EXTERNAL_SERVICE_ACCSESS_TOKEN_MANAGE = "external_service_access_token_manage";
    public const BOOKMARK_COMPANY                      = "bookmark_company";
    public const SAVE_COMPANY_SEARCH_CONDITION         = "save_company_search_condition";
    public const GOOD                                  = "good";
    public const NOTIFICATION_MANAGEMENTS              = "notification_managements";
    public const WATCH_LIST                            = "watch_list";
    public const USER_NOTIFICATION                     = "user_notification";
    public const PROJECT_PARTICIPANT                   = "project_participant";
    public const PROJECTS                              = "projects";
    public const NOTICE_KINDS                          = "notice_kinds";
    public const DELETE_FLG_DELETED                    = 1;
    public const DELETE_FLG_UN_DELETED                 = 0;

    public const WATCHLIST = "watchlist";

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'user_id',
        'company_id',
        'disp_name',
        'mail_address',
        'user_group_id',
        'icon_image_path',
        'display_color_id',
        'login_password',
        'regist_date',
        'service_auth_id',
        'temp_application_flg',
        'company_search_flg',
        'service_contractor_auth_flg',
        'super_user_auth_flg',
        'mail_verify_flg',
        'guest_flg',
        'delete_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',

    ];

    protected $table        = 't_user';
    protected $primaryKey   = 'user_id';
    public $incrementing = false;
    protected $keyType      = 'string';

    protected $fillable = self::FIELDS;

    protected $hidden = [
        'login_password',
        'pivot',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope(new DeleteFlgNotDeleteScope());
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }


    public function disp_color()
    {
        return $this->belongsTo(UserDispColor::class, 'display_color_id', 'disp_color_id');
    }

    public function user_group()
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id', 'user_group_id');
    }

    public function company_owned()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function url_authentication_verify()
    {
        return $this->hasMany(UrlAuthenticationVerify::class, 'user_id', 'user_id');
    }

    public function followup()
    {
        return $this->hasMany(Followup::class, 'followup_user_id', 'user_id');
    }

    public function breakdown()
    {
        return $this->hasMany(Breakdown::class, 'reportee_user_id', 'user_id');
    }

    public function task()
    {
        return $this->hasMany(Task::class, 'user_id', 'user_id')
            ->where('t_task.delete_flg', config('apps.general.not_deleted'));
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function my_project_display_order()
    {
        return $this->hasMany(MyProjectDisplayOrder::class, 'representative_id', 'user_id');
    }

    public function external_service_access_token_manage()
    {
        return $this->hasMany(ExternalServiceAccessTokenManage::class, 'user_id', 'user_id');
    }

    public function bookmark_company()
    {
        return $this->hasMany(BookmarkCompany::class, 'user_id', 'user_id');
    }

    public function save_company_search_condition()
    {
        return $this->hasMany(SaveCompanySearchCondition::class, 'user_id', 'user_id');
    }

    public function good()
    {
        return $this->hasMany(Good::class, 'user_id', 'user_id');
    }

    public function notification_managements()
    {
        return $this->hasMany(NotificationManagement::class, 'user_id', 'user_id');
    }

    public function user_notification()
    {
        return $this->hasMany(UserNotification::class, 'user_id', 'user_id');
    }

    public function project_participant()
    {
        return $this->hasMany(ProjectParticipant::class, 'user_id', 'user_id');
    }

    public function getAuthPassword()
    {
        return $this->login_password;
    }

    public function projects()
    {
        return $this->belongsToMany(
            Project::class,
            't_project_participant',
            'user_id',
            'project_id',
            'user_id',
            'project_id'
        )->avaiable()->orderBy('t_project.project_name', 'asc');
    }

    public function isMatchPasswordCurrent($passwordCheck): bool
    {
        return Hash::check($passwordCheck, $this->login_password);
    }

    public function isPasswordNewSamePasswordCurrent($passwordNew): bool
    {
        return Hash::check($passwordNew, $this->login_password);
    }

    public function watchlist()
    {
        return $this->belongsToMany(
            Task::class,
            't_watch_list',
            'user_id',
            'task_id',
            'user_id',
            'task_id'
        )->where('t_task.delete_flg', config('apps.general.not_deleted'))
            ->orderBy('t_task.task_name', 'desc')
            ->with(Task::TASK_RELATION);
    }

    public function all_projects()
    {
        return $this->belongsToMany(Project::class, 't_project_participant', 'user_id', 'project_id', 'user_id', 'project_id');
    }

    // check is free period use
    public function isFreePeriodUse()
    {
        $free_period_use = $this->{$this::COMPANY_OWNED}->{Company::FREE_PERIOD_USE_COMPANY}()->first();
        if ($free_period_use) {
            return ($free_period_use->free_peiod_end_date > date('Y-m-d')) ? true : false;
        }

        return null;
    }

    // get class display color
    public function getDisplayColorClass()
    {
        $color = $this->{$this::DISP_COLOR}()->first();
        if ($color) {
            if ($color->classification == config('constants.color.red') || $color->classification == config('constants.color.orange') || $color->classification == config('constants.color.green') || $color->classification == config('constants.color.green')) {
                return $color->classification;
            }
            return config('constants.color.green');
        }
        return null;
    }


    // Check role is Service contractor
    public function isRoleServiceContractor()
    {
        return $this->service_contractor_auth_flg === config('apps.user.is_service_contractor_auth');
    }

    // Check role is Superuser
    public function isRoleSuperuser()
    {
        return $this->super_user_auth_flg === config('apps.user.is_super_user');
    }

    // Check role is Corporate search user
    public function isRoleCorporateSearchUser()
    {
        return $this->company_search_flg === config('apps.user.is_company_search');
    }

    // Check role is Guest
    public function isRoleGuest()
    {
        return $this->guest_flg === config('apps.user.is_guest');
    }

    // Check role is Contractor Deactive
    public function isRoleContractorDeactive()
    {
        $company = $this->{$this::COMPANY_OWNED}->first();
        if ($company) {
            if ($company->contract_end_date < date('Y-m-d')) {
                return true;
            }
        }

        return false;
    }

    public function getIconImageAttribute()
    {
        if (empty($this->icon_image_path)) {
            return asset(config('apps.general.avatar_image_default'));
        }

        return Storage::url($this->icon_image_path);
    }

    public function getIconImageUrlAttribute()
    {
        if (empty($this->icon_image_path) || trim($this->icon_image_path) == config('apps.general.avatar_image_default')) {
            return asset(config('apps.general.avatar_image_default'));
        }
        return Storage::url($this->icon_image_path);
    }

    public function notice_kinds()
    {
        return $this->belongsToMany(NoticeKind::class, 't_notification_management', 'user_id', 'notice_kinds_id', 'user_id', 'notice_kinds_id');
    }

    public function free_period_use_company()
    {
        return $this->hasOne(FreePeriodUseCompany::class, 'company_id', 'company_id');
    }

    public function stripe_user()
    {
        return $this->hasOne(StripeUser::class, 'user_id', 'user_id');
    }

    public function stripe_users()
    {
        return $this->hasMany(StripeUser::class, 'user_id', 'user_id');
    }

    public function licences()
    {
        return $this->hasMany(LicenceManagement::class, 'company_id', 'company_id');
    }

    public function getLicenceNumber()
    {
        return $this->licences()->sum('licence_num');
    }

    public function licence_last()
    {
        return $this->hasOne(LicenceManagement::class, 'company_id', 'company_id')->orderBy('licence_num_change_date', 'desc');
    }

    public function scopeSupperUser($query)
    {
        return $query->super_user_auth_flg === config('apps.user.is_super_user');
    }

    public function isVerifyEmail()
    {
        return empty($this->mail_verify_flg) ? false : true;
    }

    /**
     * check user is guest
     *
     * @return boolean
     */
    public function isGuest()
    {
        return $this->guest_flg == config('apps.user.is_guest');
    }

    /**
     * check user is member
     *
     * @return boolean
     */
    public function isMember()
    {
        return !$this->isGuest();
    }

    /**
     * relationship project not complete
     *
     * @return void
     */
    public function projects_not_complete()
    {
        return $this->all_projects()->where('t_project_participant.delete_flg', config('apps.general.not_deleted'))->avaiable()->notComplete()->orderBy('t_project.project_name', 'asc');
    }

    /**
     * relationship project complete
     *
     * @return void
     */
    public function projects_complete()
    {
        return $this->all_projects()->avaiable()->complete()->orderBy('t_project.project_name', 'asc');
    }

    public function stripe_subs()
    {
        return $this->hasMany(Subscription::class, 'user_id', 'user_id');
    }

    public function cards()
    {
        return $this->hasMany(StripeCard::class, 'user_id', 'user_id');
    }
}
