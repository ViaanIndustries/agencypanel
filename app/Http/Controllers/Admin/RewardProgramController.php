<?php

namespace App\Http\Controllers\Admin;

/**
 * ControllerName : Reward Program.
 * Maintains a list of functions used for Reward Program.
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


use App\Http\Controllers\Controller;
use App\Http\Requests\RewardProgramRequest;
use App\Services\RewardProgramService;

class RewardProgramController extends Controller
{
    protected $service;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct(RewardProgramService $service) {
        $this->service = $service;
    }


    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request) {
        $viewdata                   = [];
        $data                       = $this->service->index($request);
        $viewdata['rewardprograms'] = $data['rewardprograms'];
        $viewdata['appends_array']  = $data['appends_array'];
        return view('admin.rewardprograms.index', $viewdata);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create() {
        $viewdata   = [];
        $viewdata['status']     = $this->service->getStatusList();
        $viewdata['platforms']  = $this->service->getPlatformList();
        $viewdata['priorities'] = $this->service->getPriorityList();
        $viewdata['events']     = $this->service->getEventList();

        $viewdata['artists']    = [];
        $artists = $this->service->getArtistList();

        if($artists) {
            $viewdata['artists'] = $artists;
        }

        return view('admin.rewardprograms.create', $viewdata);
    }


    /**
     * Store a newly created resource in storage.
     * @param  RewardProgramRequest  $request
     * @return Response
     */
    public function store(RewardProgramRequest $request) {
        $response = $this->service->store($request);

        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }

        Session::flash('message','Reward Program added succesfully');

        return Redirect::route('admin.rewardprograms.index');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id) {
        $viewdata   = [];
        $viewdata['status']     = $this->service->getStatusList();
        $viewdata['platforms']  = $this->service->getPlatformList();
        $viewdata['priorities'] = $this->service->getPriorityList();
        $viewdata['events']     = $this->service->getEventList();

        $viewdata['artists']    = [];
        $artists = $this->service->getArtistList();

        if($artists) {
            $viewdata['artists'] = $artists;
        }

        $rewardprogram   = $this->service->find($id);

        if($rewardprogram) {
            $viewdata['rewardprogram']   = $rewardprogram;
        }

        return view('admin.rewardprograms.edit', $viewdata);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  RewardProgramRequest  $request
     * @param  int  $id
     * @return Response
     */

    public function update(RewardProgramRequest $request, $id) {
        $response = $this->service->update($request, $id);
        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }

        Session::flash('message','Reward Program updated succesfully');
        return Redirect::route('admin.rewardprograms.index');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id) {
        $rewardprogram = $this->service->destroy($id);
        Session::flash('message','Reward Program deleted succesfully');
        return Redirect::route('admin.rewardprograms.index');
    }

}
