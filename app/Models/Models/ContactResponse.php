<?php

namespace App\Models;

use App\Traits\HasScopeExtend;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactResponse extends Model
{
    use HasFactory;
    use HasScopeExtend;

    public const CONTACT = 'contact';
    public const RESPONSE_USER = 'response_user';

    public const CREATED_AT = 'create_datetime';

    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'contact_id',
        'response_user_id',
        'read_flg',
        'delete_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
    ];

    protected $fillable = self::FIELDS;

    protected $primaryKey = 'contact_id';

    protected $table = 't_contact_response';

    public $incrementing = false;

    protected $keyType = 'string';

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id', 'contact_id');
    }

    public function response_user()
    {
        return $this->belongsTo(User::class, 'response_user_id', 'user_id');
    }
}
