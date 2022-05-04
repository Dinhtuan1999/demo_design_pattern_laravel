<?php

namespace App\Repositories;

use App\Models\WatchList;

class WatchListRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(WatchList::class);
    }
    
}
