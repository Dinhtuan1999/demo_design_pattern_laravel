<?php

namespace App\Repositories;

use App\Models\Kind;

class KindRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(Kind::class);
        $this->fields = Kind::FIELDS;
    }

    public function getAll()
    {
        return  $this->getInstance()::where('delete_flg', config('apps.general.not_deleted'))->get();
    }
}
