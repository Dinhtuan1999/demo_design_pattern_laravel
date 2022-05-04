<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentHistory extends Model
{
    use HasFactory;
    use HasCompositePrimaryKey;

    public const COMPANY = "company";

    protected $table = 't_payment_history';

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    protected $primaryKey = ['payment_history_id', 'company_id'];
    public $incrementing = false;

    protected $fillable = [
        'payment_history_id',
        'company_id',
        'payment_method',
        'billing_date',
        'billing_amount',
        'payment_date',
        'deposit_amount',
        'commission',
        'assess_licence_num',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }
}
