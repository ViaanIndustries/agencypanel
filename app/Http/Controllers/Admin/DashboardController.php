<?php

namespace App\Http\Controllers\Admin;


use Input;
use Redirect;
use Config;
use Session;
use Validator;
use Hash;
use App\Services\ArtistService;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;


class DashboardController extends Controller
{

    protected $dashboardService;
    protected $artistservice;



    public function __construct(ArtistService $artistservice)
    {

        $this->perpage = 15;
        $this->user_type = 'genuine';
        $this->page_title="My Dashboard";
        $this->page_desc="Artist Count, Earning, Live Sessions";
        $this->artistservice = $artistservice;

    }


    public function getDashboard(Request $request)
    {

        $requestData = $request->all();
        $requestData['user_type'] = 'genuine';
        $requestData['txn_type'] = 'added';
        $product = env('PRODUCT');
        $viewdata = [
            'page_title' => $this->page_title,
            'page_desc' =>  $this->page_desc,
            'artist_count' => 0,
            'total_coins' => 0,
            'live_session_count'=>0,
            'total_coins'=>0
        ];

        $responseSalesData = $this->artistservice->getAgencyDashboard($requestData);
        $viewdata['artist_count'] = intval($responseSalesData['artist_count']);
        $viewdata['live_session_count'] = intval($responseSalesData['live_session_count']);
        $viewdata['total_coins'] = intval($responseSalesData['total_coins']);
        
        return view('admin.dashboard.dashboard', $viewdata);

    }


}

