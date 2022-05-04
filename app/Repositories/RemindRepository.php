<?php


namespace App\Repositories;


use App\Models\Remind;

class RemindRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(Remind::class);
        $this->fields = Remind::FIELDS;
    }
}
