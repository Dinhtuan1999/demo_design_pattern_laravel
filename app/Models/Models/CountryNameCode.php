<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CountryNameCode extends Model
{
    use HasFactory;

    protected $table = "m_country_name_code";
    public const CREATED_AT = 'create_datetime';
    public const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'country_id',
        'country_name_code',
        'country_name',
        'delete_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id',
    ];
    public $incrementing = false;
    protected $primaryKey = 'country_id';

    protected $fillable = self::FIELDS;

    public function counties()
    {
        return $this->hasMany(County::class, 'country_id', 'country_id');
    }
}
