<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Services\ArtistService;
use App\Services\AgencyService;
use App\Services\LiveService;


class ReportController extends Controller
{

    protected $artistservice;
    protected $agencyservice;
    protected $liveservice;


    public function __construct(ArtistService $artistservice, AgencyService $agencyservice, LiveService $liveservice)
    {
        $this->artistservice = $artistservice;
        $this->agencyservice = $agencyservice;
        $this->liveservice = $liveservice;

        $this->page_title = "Session Report";
        $this->page_desc = "Artist Live Session Report ";

    }

    public function getSessionReport(Request $request)
    {

        $viewdata = [];
        $responseData = $this->liveservice->index($request);
        $viewdata['lives'] = $responseData['lives'];
        $viewdata['total_earning_doller']          = (isset($responseData['total_earning_doller'])) ? $responseData['total_earning_doller'] : 0;
        $viewdata['coins']          = (isset($responseData['coins'])) ? $responseData['coins'] : 0;


        $artists = $this->artistservice->artistList($request);
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];

        $viewdata['appends_array'] = $responseData['appends_array'];
        $viewdata['page_title'] = $this->page_title;
        $viewdata['page_desc'] = $this->page_desc;

        return view('admin.reports.session_report', $viewdata);
    }
}
