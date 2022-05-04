<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditCardInfo extends Model
{
    use HasFactory;

    public const COMPANY = 'company';

    public const CREATED_AT = 'create_datetime';

    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'company_id',
        'credit_card_info_id',
        'date_of_expiry',
        'cvc',        
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
    ];

    protected $fillable = self::FIELDS;

    protected $primaryKey = 'company_id';

    protected $table = 't_credit_card_info';

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }     
}
