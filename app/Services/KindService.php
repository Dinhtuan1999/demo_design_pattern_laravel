<?php

namespace App\Services;

use App\Repositories\KindRepository;

class KindService
{
    protected $kindRepository;

    public function __construct(KindRepository $kindRepository)
    {
        $this->kindRepository = $kindRepository;
    }

    public function getListKindsWithProjectAttribute()
    {
        $model = $this->kindRepository->getModel();
        return $model::with(['project_attributes' => function ($q) {
            $q->orderBy('project_attribute_name', 'asc');
        }])
                    ->orderBy('kinds_name', 'asc')
                    ->get();
    }
}
