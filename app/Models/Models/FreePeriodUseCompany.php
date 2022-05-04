<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FreePeriodUseCompany extends Model
{
    use HasFactory;

    public const STATUS_AVAILABILITY = 1;
    public const STATUS_NOT_AVAILABILITY = 0;

    public const COMPANY = "company";

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    protected $primaryKey = 'company_id';
    public $incrementing = false;

    protected $table = 't_free_period_use_company';

    protected $fillable = [
        'company_id',
        'free_period_start_date',
        'free_peiod_end_date',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function getStatusFreePeriod()
    {
        $now = Carbon::now()->startOfDay();
        $checkStartDate = $this->free_period_start_date ? $now->gt($this->free_period_start_date) : true;
        $checkEndDate = $this->free_peiod_end_date ? $now->lt($this->free_peiod_end_date) : false;

        return  $checkStartDate && $checkEndDate ? true : false;
    }
}
