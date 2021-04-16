<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Services\ArtistService;
use App\Services\AgencyService;
use App\Services\LiveService;
use App\Services\Export\ArtistLiveSessionExport;
use Excel;

class ReportController extends Controller
{

    protected $artistservice;
    protected $liveservice;

    protected $agencyservice;


    public function __construct(ArtistService $artistservice, AgencyService $agencyservice, LiveService $liveservice)
    {
        $this->artistservice = $artistservice;
        $this->liveservice = $liveservice;

        $this->agencyservice = $agencyservice;

        $this->page_title = "Session Report";
        $this->page_desc = "Artist Live Session Report ";

    }

    public function export($request)
    {
        $data = $request->all();
        $agency_id = $request->session()->get('agency_id');
        $data['agency_id'] = $agency_id;
        
        return Excel::download(new ArtistLiveSessionExport($data), 'artist_live_session_report.xlsx');
    }

    public function getSessionReport(Request $request)
    {
         $viewdata = [];
         switch ($request->actionbutton) {

            case 'Search':
                $responseData = $this->liveservice->index($request);
                break;

            case 'Export':
                return $this->export($request);
                break;
            default:
                $responseData = $this->liveservice->index($request);
        }
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
