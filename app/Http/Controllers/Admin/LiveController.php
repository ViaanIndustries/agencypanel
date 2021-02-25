<?php

namespace App\Http\Controllers\Admin;

/**
 * ControllerName : Live.
 * Maintains a list of functions used for Live.
 *
 * @author      Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since       2019-05-13
 * @link        http://bollyfame.com/
 * @copyright   2019 BOLLYFAME
 * @license     http://bollyfame.com/license
 */

use Illuminate\Http\Request;

use Input;
use Redirect;
use Config;
use Session;
use Validator;


use App\Http\Controllers\Controller;
use App\Http\Requests\LiveRequest;
use App\Services\LiveService;
use App\Services\CastService;
use App\Services\CmsuserService;

class LiveController extends Controller
{
    protected $service;
    protected $castservice;
    protected $cmsuserservice;
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function __construct(LiveService $service, CastService $castservice,  CmsuserService $cmsuserservice) {
        $this->service = $service;
	$this->castservice  = $castservice;
	 $this->cmsuserservice = $cmsuserservice;
    }


    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index_old(Request $request) {
        if(!isset($request['status'])) {
            $request['status'] = 'active';
        }
        $viewdata                   = [];
        $data                       = $this->service->index($request);
        $viewdata['lives']          = $data['lives'];
        $viewdata['artists']        = [];

        $artists = $this->service->getArtistList();
        if($artists) {
            $viewdata['artists'] = $artists;
        }
        $viewdata['appends_array']  = $data['appends_array'];
        return view('admin.lives.index', $viewdata);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request) {
        $viewdata   = [];
        $viewdata['status']         = Config::get('app.status');
        $viewdata['types']          = Config::get('app.live_types');
        $viewdata['platforms']      = array_except(Config::get('app.platforms'), 'paytm');
        $viewdata['datetime_format']= Config::get('app.datetime_format');

        $viewdata['artists']= [];

        $artists = $this->service->getArtistList();
        if($artists) {
            $viewdata['artists'] = $artists;
        }
 	if(!empty($request->artist_id))
        {
            $cmsuser = $this->cmsuserservice->find($request->artist_id);
            $viewdata['cmsuser'] = $cmsuser->toArray();
	}

	



    //echo "<pre>";
//	print_r($viewdata);
//	echo "<pre>";	
//	exit;
    
        /*$viewdata['casts']  = [];
        $casts              = $this->castservice->activelists();
        if($casts) {
            $viewdata['casts']  = (isset($casts['results'])) ? $casts['results'] : [];
	}*/

        return view('admin.lives.create', $viewdata);
    }


    /**
     * Store a newly created resource in storage.
     * @param  LiveRequest  $request
     * @return Response
     */
    public function store(LiveRequest $request) {
        $response = $this->service->store($request);

        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }

        Session::flash('message','Live added succesfully');

        return Redirect::route('admin.lives.index');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id) {
        $viewdata   = [];
        $viewdata['status']         = Config::get('app.status');
        $viewdata['types']          = Config::get('app.live_types');
        $viewdata['platforms']      = array_except(Config::get('app.platforms'), 'paytm');
        $viewdata['datetime_format']= Config::get('app.datetime_format');

        $viewdata['artists']= [];

        $artists = $this->service->getArtistList();
        if($artists) {
            $viewdata['artists'] = $artists;
        }

        $live   = $this->service->find($id);

        if($live) {
            $viewdata['live']   = $live;
        }

        $viewdata['casts']  = [];
        $casts              = $this->castservice->activelists();
        if($casts) {
            $viewdata['casts']  = (isset($casts['results'])) ? $casts['results'] : [];
        }

        return view('admin.lives.edit', $viewdata);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  LiveRequest  $request
     * @param  int  $id
     * @return Response
     */

    public function update(LiveRequest $request, $id) {
        $response = $this->service->update($request, $id);
        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }

        Session::flash('message','Live updated succesfully');
        return Redirect::route('admin.lives.index');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id) {
        $live = $this->service->destroy($id);
        Session::flash('message','Live deleted succesfully');
        return Redirect::route('admin.lives.index');
    }



    public function stats(Request $request, $live_id)
    {
        $viewdata = [];
        $request['live_id'] = $live_id;
        $responseData = $this->service->stats($request);
        $viewdata['stats'] = $responseData;
        $viewdata['appends_array'] = (isset($responseData['appends_array'])) ? $responseData['appends_array'] : [];

//        return $viewdata;

        return view('admin.lives.stats', $viewdata);

    }

     /*Author Pratikesh work start here*/
    public function index(Request $request) {
        $viewdata                   = [];
        $data                       = $this->service->index($request);




	$viewdata['total_reports'] = $data['total_report'];
    	$viewdata['lives']          = $data['lives'];
	$viewdata['appends_array']  = $data['appends_array'];
	$viewdata['paginate'] = $data['paginate_data'];
        return view('admin.lives.index', $viewdata);
    }
 public function LiveEventStore(Request $request)
    {
        $error_messages     = [];
        $request_data       = $request->all();
        $response_data      = [];
        $status_code        = 200;



                $validator= Validator::make($request->all(),[
                'schedule_at'=>'required',
                'schedule_end_at'=>'required',
                'desc'=>'required',
 
         ]);
         if ($validator->fails()) {
            return redirect('lives/create')
                        ->withErrors($validator)
                        ->withInput();
        }
 
        $response = $this->service->LiveEventStore($request);

        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }

        Session::flash('message','Upcoming Event scheduled succesfully');

        return Redirect::route('admin.lives.create');

    }

	public function upcomingEventList(Request $request)
    {

        $viewdata                   = [];
	$data                       = $this->service->upcomingEventList($request);      




	$artists = $this->service->getArtistList();
	 $viewdata['artists'] = $artists;
        $viewdata['status']         = Config::get('app.status');
	    $viewdata['lives']          = $data['lives'];
	$viewdata['appends_array']  = $data['appends_array'];

	//echo "<pre>";
       // print_r($viewdata);
       // echo "<pre>";
       // exit;



        return view('admin.lives.upcoming',$viewdata);
	}

    public function editLive(Request $request, $event_id = null)
    {
        $viewdata   = [];
        $viewdata['status']         = Config::get('app.status');
        $viewdata['types']          = Config::get('app.live_types');
        $viewdata['platforms']      = array_except(Config::get('app.platforms'), 'paytm');
        $viewdata['datetime_format']= Config::get('app.datetime_format');
	$upcomingdata = $this->service->getUpcomingEventById($request->event_id);

       
        $viewdata['lives'] = $upcomingdata;//->toArray();
    return view('admin.lives.edit_live',$viewdata);

    }


    public function LiveEventUpdate(Request $request)
    {
	$error_messages     = [];
	$request_data       = $request->all();



        $response_data      = [];
        $status_code        = 200;

                $validator= Validator::make($request->all(),[
                'schedule_at'=>'required',
                'schedule_end_at'=>'required',
                'desc'=>'required',

         ]);
	if ($validator->fails()) {

		    return Redirect::back()->withErrors($validator)->withInput();
        }

	
        $response = $this->service->LiveEventUpdate($request);
        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }

        Session::flash('message','Upcoming Event updated succesfully');
	return Redirect::back() ;



    }
    /*Author Pratikesh work end here*/

}
