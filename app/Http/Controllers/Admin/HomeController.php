<?php

namespace App\Http\Controllers\Admin;


use Input;
use Redirect;
use Config;
use Session;
use Validator;
use Hash;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Services\CmsuserService;


class HomeController extends Controller
{

    protected $cmsuserService;


    public function __construct(CmsuserService $cmsuserService)
    {
        $this->cmsuserservice        =   $cmsuserService;
    }



    public function dashboard(Request $request)
    {

        $viewdata   =   [];

        return view('admin.home.dashboard', $viewdata);

    }



    public function salesDashboard(Request $request)
    {

        $viewdata   =   [];

        return view('admin.home.salesdashboard', $viewdata);

    }


    public function liveSessionsDashboard(Request $request)
    {

        $viewdata   =   [];

        return view('admin.home.livesessionsdashboard', $viewdata);

    }

    public function contentsDashboard(Request $request)
    {

        $viewdata   =   [];

        return view('admin.home.contentsdashboard', $viewdata);

    }













}

