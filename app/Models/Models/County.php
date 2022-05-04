<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class County extends Model
{
    use HasFactory;

    protected $table = "m_county";

    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'county_id',
        'county_name',
        'display_order',
        'country_id',
    ];
    public $incrementing = false;
    protected $primaryKey = 'county_id';

    protected $fillable = self::FIELDS;

    public function counties()
    {
        return $this->hasOne(CountryNameCode::class, 'country_id', 'country_id');
    }
}
