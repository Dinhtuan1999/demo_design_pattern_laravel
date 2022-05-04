<?php

namespace App\Http\Controllers\PC;

use App\Http\Controllers\Controller;
use App\Services\AppService;
use App\Services\ProjectNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function __construct(AppService $appService)
    {
        $this->appService = $appService;
    }

    public function home()
    {
        return view('main');
    }

    public function changeLanguage(Request $request)
    {
        $this->appService->changeLanguage($request);
        return redirect()->back();
    }

    private $appService;
}
