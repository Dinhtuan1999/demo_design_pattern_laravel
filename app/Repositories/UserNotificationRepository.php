<?php

namespace App\Repositories;

use App\Models\UserNotification;

class UserNotificationRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(UserNotification::class);
    }
}
