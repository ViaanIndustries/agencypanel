<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;
use Storage;


use App\Http\Controllers\Controller;
use App\Http\Requests\PollRequest;

use App\Services\PollService;
use App\Services\ArtistService;


class PollController extends Controller
{

    protected $pollservice;
    protected $artistservice;

    public function __construct(PollService $pollservice, ArtistService $artistservice)
    {
        $this->pollservice = $pollservice;
        $this->artistservice = $artistservice;
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
        $responseData = $this->pollservice->index($request);
        $artists = $this->artistservice->artistList();
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results']->toArray() : [];
        $viewdata['polls'] = $responseData['results']['polls'];
        $viewdata['appends_array'] = $responseData['results']['appends_array'];
//        return $viewdata;
        return view('admin.polls.index', $viewdata);

    }

    

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $viewdata = [];
        $artists = $this->artistservice->artistList();
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results']->toArray() : [];
        return view('admin.polls.create', $viewdata);
    }


    /**
     * Store a newly created resource in storage.
     * @param  PollRequest $request
     * @return Response
     */
    public function store(PollRequest $request)
    {
        $response = $this->pollservice->store($request);
        if (!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }
        Session::flash('message', 'poll added succesfully');
        return Redirect::route('admin.polls.index');

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
        $poll = $this->pollservice->find($id);
        $viewdata = [];
        $artists = $this->artistservice->artistList();
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['poll'] = $poll;
        return view('admin.polls.edit', $viewdata);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  PollRequest $request
     * @param  int $id
     * @return Response
     */
    public function update(PollRequest $request, $id)
    {
        // return $request;
        $response = $this->pollservice->update($request, $id);
        if (!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }

        Session::flash('message', 'poll updated succesfully');
        return Redirect::route('admin.polls.index');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return Response
     */
    public function destroy($id)
    {
        $blog = $this->captureservice->destroy($id);
        Session::flash('message', 'poll deleted succesfully');
        return Redirect::route('admin.polls.index');
    }


    


}