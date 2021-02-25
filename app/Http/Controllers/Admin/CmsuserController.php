<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;


use App\Http\Controllers\Controller;
use App\Http\Requests\CmsuserRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Services\CmsuserService;
use App\Services\RoleService;

class CmsuserController extends Controller
{
    protected $cmsuserservice;
    protected $roleservice;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function __construct(  CmsuserService $cmsuserservice,RoleService $roleservice)
    {
        $this->cmsuserservice = $cmsuserservice;
        $this->roleservice = $roleservice;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {

         $viewdata = [];
        $request['perpage'] = 10;
        $responseData = $this->cmsuserservice->index($request);
        print_r($responseData);exit;

        $viewdata['cmsusers'] = $responseData['cmsusers'];

        $roles = $this->roleservice->activelists();

        $viewdata['roles'] = (isset($roles['results'])) ? $roles['results']->toArray() : '';
        $viewdata['appends_array'] = $responseData['appends_array'];

        return view('admin.cmsusers.index', $viewdata);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $viewdata = [];
        $roles = $this->roleservice->activelists();
        $viewdata['roles'] = (isset($roles['results'])) ? $roles['results'] : [];

        return view('admin.cmsusers.create', $viewdata);
    }

    /**
     * Store a newly created resource in storage.
     * @param  CmsuserRequest $request
     * @return Response
     */
    public function store(CmsuserRequest $request)
    {
        $request['perpage'] = 25;
        $response           = $this->cmsuserservice->store($request);
        if (!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }
        Session::flash('message', 'Cmsuser added succesfully');
        return Redirect::route('admin.cmsusers.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return Response
     */
    public function edit($id)
    {
        $viewdata = [];
        $cmsuser = $this->cmsuserservice->find($id);
        $viewdata['cmsuser'] = $cmsuser;
        $roles = $this->roleservice->activelists();
        $viewdata['roles'] = (isset($roles['results'])) ? $roles['results'] : [];


        return view('admin.cmsusers.edit', $viewdata);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  CmsuserRequest $request
     * @param  int $id
     * @return Response
     */
    public function update(CmsuserRequest $request, $id)
    {
        $response = $this->cmsuserservice->update($request, $id);

        if (!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }

        $redirect_route     = 'admin.cmsusers.index';
        $redirect_route_op  = [];
        if($response && isset($response['results']) && isset($response['results']['cmsuser']) && isset($response['results']['cmsuser']['is_contestant'])) {

            if($response['results']['cmsuser']['is_contestant'] == 'true') {
                //$redirect_route = 'admin.contestants.index';
                //$redirect_route_op = ['approval_status' => 'approved'];
            }
        }

       Session::flash('message', 'Artist updated succesfully'); 
	//        return Redirect::route($redirect_route, $redirect_route_op);
	 return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return Response
     */
    public function destroy($id)
    {
        $blog = $this->cmsuserservice->destroy($id);
        Session::flash('message', 'Cmsuser deleted succesfully');
        return Redirect::route('admin.cmsusers.index');
    }


    public function showChangePassword()
    {
        $viewdata = [];
        $user_id = Session::get('user_id');
        $cmsuser = $this->cmsuserservice->find($user_id);
        $viewdata['cmsuser'] = $cmsuser;

        return view('admin.cmsusers.change', $viewdata);
    }


    public function saveChangePassword(ResetPasswordRequest $request, $id)
    {
        $response = $this->cmsuserservice->updatePassword($request, $id);
        if (!empty($response['error_messages'])) {
            return Redirect::back()->with(array('error_messages' => $response['error_messages']))->withInput();
        }

        Session::flash('message', 'Password updated succesfully');
        return Redirect::route('admin.home.dashboard');
    }

    public function showResetPassword($user_id)
    {
        $viewdata = [];
        $cmsuser = $this->cmsuserservice->find($user_id);
        $viewdata['cmsuser'] = $cmsuser;

        return view('admin.cmsusers.reset', $viewdata);
    }


    public function saveResetPassword(ChangePasswordRequest $request, $id)
    {
        $response = $this->cmsuserservice->changePassword($request, $id);
        if (!empty($response['error_messages'])) {
            return Redirect::back()->with(array('error_messages' => $response['error_messages']))->withInput();
        }

        Session::flash('message', 'Password updated succesfully');
        return Redirect::route('admin.home.dashboard');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return Response
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-31
     */
    public function editContestant($id) {
        $viewdata = [];
        $cmsuser = $this->cmsuserservice->find($id);
        $viewdata['cmsuser'] = $cmsuser;
        $roles = $this->roleservice->activelists();
        $viewdata['roles'] = (isset($roles['results'])) ? $roles['results'] : [];

        return view('admin.cmsusers.contestantedit', $viewdata);
    }
}
