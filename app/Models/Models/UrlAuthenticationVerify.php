<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrlAuthenticationVerify extends Model
{
    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';
    public const MAIL_KIND_USER_REGISTRATION = 0;
    public const MAIL_KIND_PASSWORD_RESET = 1;
    public const TOKEN_LENGTH = 64;
    public const FIELDS = [
        'url_authentication_verify_id',
        'user_id',
        'authentication_token',
        'authentication_token_expiration',
        'mail_kinds',
        'delete_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    public $incrementing = false;

    protected $table = "t_url_authentication_verify";
    protected $primaryKey = 'url_authentication_verify_id';
    protected $keyType = 'string';
    protected $fillable = self::FIELDS;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
