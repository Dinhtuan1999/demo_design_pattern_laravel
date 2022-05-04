<?php

namespace App\Models;

use App\Scopes\DeleteFlgNotDeleteScope;
use App\Traits\HasScopeExtend;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactSend extends Model
{
    use HasFactory;
    use HasScopeExtend;

    public const CONTACT = 'contact';
    public const SEND_USER = 'send_user';
    public const CONTACT_RESPONSE = 'contact_response';

    public const CREATED_AT = 'create_datetime';

    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'contact_id',
        'send_user_id',
        'read_flg',
        'delete_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
    ];

    protected $fillable = self::FIELDS;

    protected $primaryKey = 'contact_id';

    protected $table = 't_contact_send';

    public $incrementing = false;

    protected $keyType = 'string';

    public function send_user()
    {
        return $this->belongsTo(User::class, 'send_user_id', 'user_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id', 'contact_id');
    }

    public function contact_response()
    {
        return $this->hasOne(ContactResponse::class, 'contact_id', 'contact_id');
    }
}
