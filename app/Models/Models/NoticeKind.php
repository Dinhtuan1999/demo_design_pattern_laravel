<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NoticeKind extends Model
{
    use HasFactory;

    public const CREATED_AT = 'create_datetime';
    public const FIELDS = [
        'notice_kinds_id',
        'notice_kinds',
        'notice_kinds_class_code',
        'notice_contents',
        'inapp_notification_flg',
        'desktop_notification_flg',
        'mail_notification_flg',
        'customization_possible_flg',
        'create_datetime',
        'create_user_id',
        'update_datetime',
        'update_user_id'
    ];

    public const UPDATED_AT = 'update_datetime';
    public $incrementing = false;

    protected $table = "m_notice_kinds";
    protected $primaryKey = 'notice_kinds_id';

    protected $fillable = self::FIELDS;

    public function scopeAvaiableSetting($query)
    {
        return $query->where('customization_possible_flg', config('apps.notice_kinds.customization_possible_true'));
    }
}
