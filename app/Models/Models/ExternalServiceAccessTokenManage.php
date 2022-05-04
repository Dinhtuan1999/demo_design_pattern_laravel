<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExternalServiceAccessTokenManage extends Model
{
    use HasFactory;
    use HasCompositePrimaryKey;

    public const USER = "user";

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public $incrementing = false;

    protected $primaryKey = ['user_id', 'access_token'];

    protected $table = 't_external_service_access_token_manage';

    protected $fillable = [
        'user_id',
        'access_token',
        'cooperation_service',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
