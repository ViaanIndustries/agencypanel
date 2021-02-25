<?php

namespace App\Http\Controllers\Admin;

// use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;


use App\Http\Controllers\Controller;
use App\Http\Requests\SaticepageRequest;
use App\Services\SaticepageService;

class SaticepageController extends Controller
{

    protected $saticepageservice;


    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function __construct(SaticepageService $saticepageservice)
    {
        $this->saticepageservice = $saticepageservice;
    }



    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {

        $viewdata                        =   [];
        $saticepages                     =   $this->saticepageservice->index();
        $viewdata['saticepages']         =   $saticepages;
        return view('admin.saticepages.index', $viewdata);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $viewdata            =   [];

        $artist_role_ids        =   \App\Models\Role::where('slug','artist')->lists('_id');
        $artist_role_ids        =   ($artist_role_ids) ? $artist_role_ids->toArray() : [];
        $viewdata['artists']    =   \App\Models\Cmsuser::active()->whereIn('roles',$artist_role_ids)->lists('name','name')->toArray();

        return view('admin.saticepages.create', $viewdata);
    }



    /**
     * Store a newly created resource in storage.
     * @param  SaticepageRequest  $request
     * @return Response
     */
    public function store(SaticepageRequest $request)
    {

        $response = $this->saticepageservice->store($request);
        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }
        Session::flash('message','Saticepage added succesfully');
        return Redirect::route('admin.saticepages.index');
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
        $saticepage                   =   $this->saticepageservice->find($id);
        $viewdata['saticepage']       =   $saticepage;

        return view('admin.saticepages.edit', $viewdata);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  SaticepageRequest  $request
     * @param  int  $id
     * @return Response
     */

    public function update(SaticepageRequest $request, $id)
    {
        // return $request;
        $response = $this->saticepageservice->update($request, $id);
        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }

        Session::flash('message','Saticepage updated succesfully');
        return Redirect::route('admin.saticepages.index');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $blog = $this->saticepageservice->destroy($id);
        Session::flash('message','Saticepage deleted succesfully');
        return Redirect::route('admin.saticepages.index');
    }

}