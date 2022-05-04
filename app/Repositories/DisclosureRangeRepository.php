<?php

namespace App\Repositories;

use App\Models\DisclosureRange;

class DisclosureRangeRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(DisclosureRange::class);
        $this->fields = DisclosureRange::FIELDS;
    }
}
