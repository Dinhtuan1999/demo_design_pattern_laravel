<?php

namespace App\Http\Controllers\PC;

use App\Http\Controllers\Controller as ControllerBasic;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;

class Controller extends ControllerBasic
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    public function setSessionFlashSuccess(string $message)
    {
        session()->flash('success', $message);
    }

    public function setSessionFlashError(string $message)
    {
        session()->flash('error', $message);
    }
}
