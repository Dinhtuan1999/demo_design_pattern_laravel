<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanySearchKeyword extends Model
{
    use HasFactory;

    public const COMPANY = "company";

    public const CREATED_AT = 'create_datetime';

    public const UPDATED_AT = 'update_datetime';

    public const FIELDS  = 
    [
        'company_search_keyword_id',
        'company_id',
        'company_search_keyword',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
    ];


    protected $fillable = self::FIELDS ;

    protected $primaryKey = 'company_search_keyword_id';

    protected $table = 't_company_search_keyword';

    public function company () 
    {
        return $this->belongsToMany(Company::class, 'company_id', 'company_id');
    }
}
