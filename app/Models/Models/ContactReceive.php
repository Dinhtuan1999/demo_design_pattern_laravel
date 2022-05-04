<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactReceive extends Model
{
    use HasFactory;

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'contact_id',
        'comapny_id',
        'address_company_id',
        'contact_message',
        'contact_address',
        'contact_message_sender_id',
        'consent_flg',
        'read_flg',
        'contact_rejection_reason_id',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];
    public $incrementing = false;

    protected $table = "t_contact_receive";

    protected $fillable = self::FIELDS;

    public function contact_send()
    {
        return $this->belongsToMany(ContactSend::class, 'contact_id', 'contact_id');
    }
}
