<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Services\AgencyService;
use Config;
use App\Http\Requests\AgencyRequest;
use Session;
use Redirect;

class AgencyController extends Controller
{

    protected $agencyservice;
 
    public function __construct( AgencyService $agencyservice)
    {
         $this->agencyservice = $agencyservice;
 
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $viewdata = [];
        $responseData                                       = $this->agencyservice->index($request);
        $viewdata['agency']                                = $responseData['agency'];
        $viewdata['appends_array']                          =  $responseData['appends_array'];
        return view('admin.agency.index',$viewdata);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $viewdata   = [];
         $viewdata['date_format_dob']= Config::get('app.date_format_dob');
         return view('admin.agency.create',$viewdata);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(AgencyRequest $request)
    {
        $response = $this->agencyservice->store($request);

        Session::flash('message', 'Agency added succesfully');
        return Redirect::route('admin.agency.index');
    }
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $viewdata = [];
        $agency = $this->agencyservice->find($id);
        $viewdata['agency'] = $agency;
        return view('admin.agency.edit', $viewdata);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $response = $this->agencyservice->update($request, $id);
        Session::flash('message', 'Agency updated succesfully');
        return Redirect::route('admin.agency.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
