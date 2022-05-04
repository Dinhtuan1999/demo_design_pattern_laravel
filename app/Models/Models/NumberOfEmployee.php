<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NumberOfEmployee extends Model
{
    use HasFactory;

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS  =
    [
        'number_of_employees_id',
        'number_of_employees',
        'delete_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
    ];

    protected $fillable = self::FIELDS ;

    protected $primaryKey = 'number_of_employees_id';

    protected $table = 'm_number_of_employees';

    public $incrementing = false;
}
