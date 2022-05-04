<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockCompany extends Model
{
    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';
    public const COMPANY = "company";
    public const FIELDS = [
        'block_keyword_id',
        'company_id',
        'block_keyword',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];
    public $incrementing = false;

    protected $table = 't_block_company';
    protected $primaryKey = 'block_keyword_id';
    protected $keyType = 'string';

    protected $fillable = self::FIELDS;

    public function company()
    {
        return $this->hasOne(Company::class, 'company_id', 'company_id');
    }
}
