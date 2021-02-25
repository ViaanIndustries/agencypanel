<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use Input;
use Redirect;
use Config;
use Session;

use App\Http\Controllers\Controller;
use App\Http\Requests\FanRequest;
use App\Services\FanService;
use App\Services\ArtistService;

class FanController extends Controller
{
    protected $fanservice;
    protected $artistservice;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function __construct(FanService $fanservice, ArtistService $artistservice)
    {
        $this->fanservice = $fanservice;
        $this->artistservice = $artistservice;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $viewdata = [];
        $responseData = $this->fanservice->index($request);
        $artists = $this->artistservice->artistList();
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['fans'] = !empty($responseData['results']['fans']) ? $responseData['results']['fans'] : [];
        $viewdata['appends_array'] = $responseData['results']['appends_array'];

        return view('admin.fans.index', $viewdata);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $viewdata = [];
        $artists = $this->artistservice->artistList();
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];

        return view('admin.fans.create', $viewdata);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(FanRequest $request)
    {
        $response = $this->fanservice->store($request);

        if (!empty($response['error_messages'])) {
            return Redirect::back()->withErrors(['', $response['error_messages']]);
        }
        Session::flash('message', 'Fan added succesfully');
        return Redirect::route('admin.fans.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $viewdata = [];
        $fan = $this->fanservice->find($id);

        $artists = $this->artistservice->artistList();
//        $viewdata['reward_title'] = Config::get('app.reward_title');
        $viewdata['reward_title'] = Array('reward_on_fan_of_the_month' => 'Fan of the month');
        $viewdata['reward_type'] = Array('coins' => 'Coins', 'xp' => 'XP');
        $viewdata['status'] = Array('active' => 'Active', 'inactive' => 'Inactive');
        $viewdata['fan'] = $fan;
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];
        return view('admin.fans.edit', $viewdata);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $data = array_except($request->all(), ['_token', '_method', 'checked']);

        if ($request['checked'] == 1) {
            if ($request['coins'] > 0) {
                $sendNotification = $this->fanservice->sendNotification($data, $id);
            } else {
                return Redirect::back()->withErrors(['', 'Coins should contain minimum of 1 for sending Notification']);
            }
        }

        $response = $this->fanservice->update($data, $id);

        if (!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }

        Session::flash('message', 'Fans Updated succesfully');
        return Redirect::route('admin.fans.index');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
