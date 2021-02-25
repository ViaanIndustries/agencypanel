<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use Input;
use Redirect;
use Config;
use Session;


use App\Http\Controllers\Controller;
use App\Http\Requests\ContestantRequest;
use App\Services\ContestantService;

class ContestantController extends Controller
{
    protected $contestantservice;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function __construct(ContestantService $contestantservice)
    {
        $this->contestantservice = $contestantservice;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $viewdata                   = [];
        $responseData               = $this->contestantservice->index($request);
        $viewdata['contestants']    = $responseData['contestants'];
        $viewdata['appends_array']  = $responseData['appends_array'];

        return view('admin.contestants.index', $viewdata);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('admin.contestants.create');
    }

    /**
     * Store a newly created resource in storage.
     * @param  ContestantRequest  $request
     * @return Response
     */
    public function store(ContestantRequest $request)
    {
        $response = $this->contestantservice->register($request);
        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput()->withErrors(['', $response['error_messages']]);
        }

        Session::flash('message','Contestant added succesfully');
        return Redirect::route('admin.contestants.index', ['approval_status' => 'pending']);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        $viewdata               = [];
        $contestant             = $this->contestantservice->find($id);
        $viewdata['contestant'] = $contestant;

        return view('admin.contestants.edit', $viewdata);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  ContestantRequest  $request
     * @param  int  $id
     * @return Response
     */

    public function update(ContestantRequest $request, $id)
    {
        $response = $this->contestantservice->update($request, $id);
        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput()->withErrors(['', $response['error_messages']]);
        }

        Session::flash('message','Contestant updated succesfully');
        return Redirect::route('admin.contestants.index', ['approval_status' => 'pending']);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $blog = $this->contestantservice->destroy($id);
        Session::flash('message','Contestant deleted succesfully');
        return Redirect::route('admin.contestants.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function activate($id)
    {
        $response   = $this->contestantservice->activate($id);
        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput()->withErrors(['', $response['error_messages']]);
        }

        Session::flash('message','Contestant activated succesfully');

        return Redirect::route('admin.contestants.index', ['approval_status' => 'approved']);
    }

}
