<?php

namespace App\Repositories;

use App\Models\Payment\StripeEvent;

class StripeEventRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(StripeEvent::class);
        $this->fields = $this->getInstance()->getFillable();
    }
}
