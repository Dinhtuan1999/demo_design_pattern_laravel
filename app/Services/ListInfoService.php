<?php

namespace App\Services;

use App\Services\BaseService;
use App\Repositories\CountyRepository;

class ListInfoService extends BaseService
{
    protected $countyRepo;

    public function __construct(CountyRepository $countyRepo)
    {
        $this->countyRepo = $countyRepo;
    }

    /**
     * Get list county
     *
     * @return array
     */
    public function getListCounty()
    {
        return $this->countyRepo->getModel()::all(['county_id', 'county_name', 'display_order', 'country_id'])
        ->sortBy('country_id')
        ->sortBy('display_order')
        ->toArray();
    }
}
