<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    use HasFactory;

    public const COMPANY = 'company';

    public const CONTRACT_PLAN = 'contract_plan';

    public const CREATED_AT = 'create_datetime';

    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'company_id',
        'contract_date',
        'unit_price',
        'contract_plan_id',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
        'delete_flg'
    ];

    protected $fillable = self::FIELDS;

    protected $primaryKey = ['company_id', 'contract_plan_id'];

    protected $table = 't_contract';
    public $incrementing = false;

    public function contract_plan()
    {
        return $this->belongsTo(ContractPlan::class, 'contract_plan_id', 'contract_plan_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }
}
