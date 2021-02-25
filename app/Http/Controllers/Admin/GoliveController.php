<?php

namespace App\Http\Controllers\Admin;

use Input;
use Redirect;
use Config;
use Session;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Services\ArtistService;
use App\Services\GoliveService;

class GoliveController extends Controller
{
    public function __construct(
        GoliveService $goliveservice,
        ArtistService $artistservice
    )
    {
        $this->artistservice = $artistservice;
        $this->goliveservice = $goliveservice;
    }

    public function index(Request $request)
    {
        $viewdata = [];
        $request['perpage'] = 10;
        $responseData = $this->goliveservice->index($request);
        $artists = $this->artistservice->artistList();
        $viewdata['test'] = Array('true' => 'True', 'false' => 'False');
        $viewdata['live_type'] = Config::get('app.live_type');
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results']->toArray() : [];
        $viewdata['golives'] = (isset($responseData['golives'])) ? $responseData['golives'] : [];
        $viewdata['appends_array'] = (isset($responseData['appends_array'])) ? $responseData['appends_array'] : [];


        return view('admin.golives.index', $viewdata);
    }


    public function stats(Request $request, $golive_id)
    {
        $viewdata = [];
        $request['golive_id'] = $golive_id;
        $responseData = $this->goliveservice->stats($request);
        $viewdata['stats'] = $responseData;
        $viewdata['appends_array'] = (isset($responseData['appends_array'])) ? $responseData['appends_array'] : [];

        return view('admin.golives.stats', $viewdata);

    }

    public function create(Request $request)
    {
        $viewdata = [];
        $artists = $this->artistservice->artistList();
        $viewdata['platforms'] = Config::get('app.platforms');
        $viewdata['live_type'] = Config::get('app.live_type');
        $viewdata['test'] = Array('true' => 'True', 'false' => 'False');
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];

        return view('admin.golives.create', $viewdata);
    }

    public function store(Request $request)
    {
        $response = $this->goliveservice->start($request);
        if (!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }
        Session::flash('message', 'Live added succesfully');
        return Redirect::route('admin.golives.index');
    }

    public function edit($id)
    {
        $viewdata = [];
        $golive = $this->goliveservice->find($id);
        
        $artists = $this->artistservice->artistList();
        $viewdata['platforms'] = Config::get('app.platforms');
        $viewdata['live_type'] = Config::get('app.live_type');
        $viewdata['test'] = Array('true' => 'True', 'false' => 'False');
        $viewdata['golive'] = $golive;
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['appends_array'] = Array('created_at'=>$golive['created_at'],'updated_at'=>$golive['updated_at']);
        return view('admin.golives.edit', $viewdata);
    }

    public function update(Request $request,$id)
    {
        $data=array_except($request->all(),['_token','_method']);
        array_set($data,'golive_id',$id);
        $response = $this->goliveservice->web_end($data);
        if (!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }
        Session::flash('message', 'Live Updated succesfully');
        return Redirect::route('admin.golives.index');
    }

}