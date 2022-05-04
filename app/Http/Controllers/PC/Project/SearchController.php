<?php

namespace App\Http\Controllers\PC\Project;

use App\Http\Controllers\Controller;
use App\Services\ProjectService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private $projectService;

    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
    }

    public function index()
    {
        return view('project.search.index');
    }
}
