<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyInfoReferenceNum extends Model
{
    use HasFactory;

    public const COMPANY = "company";

    public const CREATED_AT = 'create_datetime';

    public const UPDATED_AT = 'update_datetime';

    public const FIELDS  =
    [
        'company_id',
        'company_info_reference_num',
        'create_datetime',
        'create_user_id' ,
        'update_datetime',
        'update_user_id',
        'delete_flg'
    ];

    protected $fillable = self::FIELDS;

    protected $primaryKey = 'company_id';

    protected $table = 't_company_info_reference_num';

    public function company()
    {
        return $this->belongsToMany(Company::class, 'company_id', 'company_id');
    }
}
