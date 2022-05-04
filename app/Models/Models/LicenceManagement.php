<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LicenceManagement extends Model
{
    use HasFactory;
    use HasCompositePrimaryKey;

    public const COMPANY = "company";

    protected $table = 't_licence_management';

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    protected $primaryKey = ['licence_management_id', 'company_id'];
    public $incrementing = false;

    protected $fillable = [
        'licence_management_id',
        'company_id',
        'licence_num',
        'licence_num_change_date',
        'delete_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }
}
