<?php

 namespace App\Services;

 use App\Services\BaseService;
 use App\Services\AppService;
 use App\Repositories\DispColorRepository;
 use Illuminate\Support\Facades\Log;

 class DisplayColorService extends BaseService
 {
     protected $dispColorRepo;

     public function __construct(DispColorRepository $dispColorRepo)
     {
         $this->dispColorRepo = $dispColorRepo;
     }

     // get all display color
     public function getListDisplayColor()
     {
         $dispColors = $this->dispColorRepo->all(null, ['by' => 'display_order', 'type' => 'ASC']);

         return $dispColors->toArray();
     }
 }
