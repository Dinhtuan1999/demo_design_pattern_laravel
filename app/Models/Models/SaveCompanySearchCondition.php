<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaveCompanySearchCondition extends Model
{
    use HasFactory;

    protected $table = 't_save_company_search_condition';

    const CREATED_AT = 'create_datetime';
    const UPDATED_AT = 'update_datetime';

    const FIELDS = [
        'search_condition_id',
        'user_id',
        'search_condition',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    protected $fillable = self::FIELDS;

    const USER = "user";

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
