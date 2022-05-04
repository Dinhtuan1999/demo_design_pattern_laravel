<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Model;

class BookmarkCompany extends Model
{
    use HasCompositePrimaryKey;

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';
    public const COMPANY = "company";
    public const USER = "user";
    public const FIELDS = [
        'user_id',
        'company_id',
        'display_order',
        'display_parent_id',
        'delete_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];
    public $incrementing = false;


    protected $table = "t_bookmark_company";
    protected $primaryKey = ['user_id', 'company_id'];

    protected $fillable = self::FIELDS;

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
