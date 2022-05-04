<?php

namespace App\Repositories;

use App\Models\BookmarkCompany;

class BookmarkCompanyRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(BookmarkCompany::class);
        $this->fields = BookmarkCompany::FIELDS;
    }
}
