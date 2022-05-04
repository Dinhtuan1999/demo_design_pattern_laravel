<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractPlan extends Model
{
    use HasFactory;
    protected $table = "m_contract_plan";
    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';
    public const FIELDS = [
        'contract_plan_id',
        'contract_plan_name',
        'plan_unit_price',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
    ];
    public $incrementing = false;
    protected $primaryKey = 'contract_plan_id';
    protected $fillable = self::FIELDS;
}
