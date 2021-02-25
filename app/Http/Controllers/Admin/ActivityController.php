<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use Input;
use Redirect;
use Config;
use Session;


use App\Http\Controllers\Controller;
use App\Http\Requests\ActivityRequest;
use App\Services\ActivityService;

class ActivityController extends Controller
{

    protected $activityservice;


    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function __construct(ActivityService $activityservice)
    {
        $this->activityservice = $activityservice;
    }



    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $viewdata                        =   [];
        $responseData                    =   $this->activityservice->index($request);
        $viewdata['activities']          =   $responseData['activities'];
        $viewdata['appends_array']       =   $responseData['appends_array'];
//        return $responseData;
        return view('admin.activities.index', $viewdata);
    }



    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('admin.activities.create');
    }



    /**
     * Store a newly created resource in storage.
     * @param  BucketcodeRequest  $request
     * @return Response
     */
    public function store(ActivityRequest $request)
    {

        $response = $this->activityservice->store($request);
        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }
        Session::flash('message','activities added succesfully');
        return Redirect::route('admin.activities.index');
    }
    

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        $viewdata                   =   [];
        $bucketcode                   =   $this->activityservice->find($id);
        $viewdata['activity']       =   $bucketcode;

        return view('admin.activities.edit', $viewdata);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  BucketcodeRequest  $request
     * @param  int  $id
     * @return Response
     */

    public function update(ActivityRequest $request, $id)
    {
        // return $request;
        $response = $this->activityservice->update($request, $id);
        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }

        Session::flash('message','activities updated succesfully');
        return Redirect::route('admin.activities.index');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $blog = $this->activityservice->destroy($id);
        Session::flash('message','activities deleted succesfully');
        return Redirect::route('admin.activities.index');
    }

}