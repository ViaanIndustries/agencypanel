<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;
use Storage;


use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Services\RoleService;



class RoleController extends Controller
{

    protected $roleservice;

    public function __construct(RoleService $roleservice)
    {
        $this->roleservice = $roleservice;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {

        $viewdata                  =    [];
        $request['perpage']        =    10;
        $responseData              =   $this->roleservice->index($request);
        $viewdata['roles']         =   $responseData['roles'];
        $viewdata['appends_array'] =   $responseData['appends_array'];
        return view('admin.roles.index', $viewdata);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('admin.roles.create');
    }



    /**
     * Store a newly created resource in storage.
     * @param  RoleRequest  $request
     * @return Response
     */
    public function store(RoleRequest $request)
    {

        $response = $this->roleservice->store($request);
        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }
        Session::flash('message','Role added succesfully');
        return Redirect::route('admin.roles.index');
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
        $role                   =   $this->roleservice->find($id);
        $viewdata['role']       =   $role;

        return view('admin.roles.edit', $viewdata);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  RoleRequest  $request
     * @param  int  $id
     * @return Response
     */
    public function update(RoleRequest $request, $id)
    {
        // return $request;
        $response = $this->roleservice->update($request, $id);
        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }

        Session::flash('message','Role updated succesfully');
        return Redirect::route('admin.roles.index');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $blog = $this->roleservice->destroy($id);
        Session::flash('message','Role deleted succesfully');
        return Redirect::route('admin.roles.index');
    }







}