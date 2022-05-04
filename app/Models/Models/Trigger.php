<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trigger extends Model
{
    use HasFactory;

    const CREATED_AT = 'create_datetime';
    const UPDATED_AT = 'update_datetime';

    public const FIELDS = [
        'trigger_id',
        'log_id',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    protected $table = 't_trigger';
    protected $primaryKey = 'trigger_id';
    public $incrementing = false;

    protected $fillable = self::FIELDS;

}
