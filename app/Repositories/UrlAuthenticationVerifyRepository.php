<?php

namespace App\Repositories;

use App\Models\UrlAuthenticationVerify;

class UrlAuthenticationVerifyRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(UrlAuthenticationVerify::class);
        $this->fields = UrlAuthenticationVerify::FIELDS;
    }
}
