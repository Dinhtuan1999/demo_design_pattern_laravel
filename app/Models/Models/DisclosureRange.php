<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisclosureRange extends Model
{
    protected $table = "m_disclosure_range_mst";
    public $timestamps = false;

    public const FIELDS = [
        'disclosure_range_id',
        'disclosure_range_name',
        'display_order',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
    ];

    protected $fillable   = self::FIELDS;
    protected $primaryKey = 'disclosure_range_id';
    protected $keyType    = 'string';
}
