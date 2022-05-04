<?php

namespace App\Models;

use App\Scopes\DeleteFlgNotDeleteScope;
use App\Traits\HasScopeExtend;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;
    use HasScopeExtend;

    public const SENDER_COMPANY = 'sender_company';
    public const DESTINATION_COMPANY = 'destination_company';
    public const CONTACT_PURPOSE = 'contact_purpose';
    public const CONTACT_REJECTION_REASON = 'contact_rejection_reason';
    public const USER_SEND = 'contact_send.send_user';
    public const USER_RESPONSE = 'contact_response.user';
    public const TYPE_SEND = 'send';
    public const TYPE_RESPONSE = 'response';

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';


    public const FIELDS =
        [
            'contact_id',
            'sender_company_id',
            'destination_company_id',
            'contact_message',
            'contact_purpose_id',
            'interest_point',
            'consent_classification',
            'response_message',
            'contact_address',
            'contact_rejection_reason_id',
            'delete_flg',
            'create_datetime',
            'create_user_id',
            'update_datetime',
            'update_user_id',
        ];

    protected $fillable = self::FIELDS;

    protected $primaryKey = 'contact_id';

    protected $table = 't_contact';

    public $incrementing = false;

    protected $keyType = 'string';

    public function sender_company()
    {
        return $this->belongsTo(Company::class, 'sender_company_id', 'company_id');
    }

    public function destination_company()
    {
        return $this->belongsTo(Company::class, 'destination_company_id', 'company_id');
    }

    public function contact_purpose()
    {
        return $this->belongsTo(ContactPurpose::class, 'contact_purpose_id', 'contact_purpose_id');
    }

    public function contact_rejection_reason()
    {
        return $this->belongsTo(ContactRejectionReason::class, 'contact_rejection_reason_id', 'contact_rejection_reason_id');
    }

    public function contact_send()
    {
        return $this->hasOne(ContactSend::class, 'contact_id', 'contact_id');
    }

    public function contact_response()
    {
        return $this->hasOne(ContactResponse::class, 'contact_id', 'contact_id');
    }
}
