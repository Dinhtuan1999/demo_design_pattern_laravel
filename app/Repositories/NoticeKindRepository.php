<?php

namespace App\Repositories;

use App\Models\NoticeKind;

class NoticeKindRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(NoticeKind::class);
        $this->fields = NoticeKind::FIELDS;
    }
}
