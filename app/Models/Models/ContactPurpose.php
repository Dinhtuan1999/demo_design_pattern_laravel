<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactPurpose extends Model
{
    use HasFactory;

    public const CONTACTS = 'contacts';

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS  =
    [
        'contact_purpose_id',
        'contact_purpose',
        'display_order',
        'delete_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
    ];

    protected $fillable = self::FIELDS ;

    protected $primaryKey = 'contact_purpose_id';

    protected $table = 'm_contact_purpose';

    public $incrementing = false;

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'company_id');
    }
}
