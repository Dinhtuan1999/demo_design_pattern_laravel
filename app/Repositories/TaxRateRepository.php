<?php

namespace App\Repositories;

use App\Models\TaxRate;

class TaxRateRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(TaxRate::class);
        $this->fields = TaxRate::FIELDS;
    }
}
