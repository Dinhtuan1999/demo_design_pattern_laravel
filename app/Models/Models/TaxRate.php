<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    const CREATED_AT = 'create_datetime';
    const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'tax_id',
        'task_status_name',
        'display_order',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];
    public $incrementing = false;

    protected $table = "m_tax_rate";
    protected $primaryKey = 'tax_id';

    protected $fillable = self::FIELDS;
}
