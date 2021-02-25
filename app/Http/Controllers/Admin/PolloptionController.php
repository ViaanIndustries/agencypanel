<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;
use Storage;


use App\Http\Controllers\Controller;
use App\Http\Requests\PolloptionRequest;
use App\Services\PollService;
use App\Services\PolloptionService;
use App\Services\ArtistService;
use App\Services\ContentService;


class PolloptionController extends Controller
{

    protected $pollservice;
    protected $polloptionservice;
    protected $artistservice;
    protected $contentservice;

    public function __construct(
        PollService $pollservice,
        PolloptionService $polloptionservice,
        ArtistService $artistservice,
        ContentService $contentservice
    )
    {
        $this->pollservice = $pollservice;
        $this->polloptionservice = $polloptionservice;
        $this->artistservice = $artistservice;
        $this->contentservice = $contentservice;
    }


    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index($content_id, Request $request)
    {
        $viewdata = [];
        $content = $this->contentservice->find($content_id);
        $viewdata['content'] = $content;
        $request['perpage'] = 10;
        $request['content_id'] = $content_id;
        $response = $this->polloptionservice->index($request);
        $viewdata['polloptions'] = $response['results']['polloptions'];;
        $viewdata['appends_array'] = $response['results']['appends_array'];

        return view('admin.polloptions.index', $viewdata);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create($content_id)
    {
        $viewdata = [];
        $content = $this->contentservice->find($content_id);
        $viewdata['content'] = $content;
        $viewdata['status'] = Config::get('app.status');

        return view('admin.polloptions.create', $viewdata);
    }


    /**
     * Store a newly created resource in storage.
     * @param  PollOptionRequest $request
     * @return Response
     */
//    public function store(PolloptionRequest $request)
    public function store(Request $request)
    {
        $content_id = $request['content_id'];
        $response = $this->polloptionservice->store($request);

        if (!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }

        Session::flash('message', 'polloption added succesfully');
        return Redirect::route('admin.polloptions.index', ['content_id' => $content_id]);
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
        $content = $this->polloptionservice->find($id);
//        $viewdata                   =   [];
//        $artists                    =   $this->artistservice->artistList();
//        $viewdata['artists']        =   (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['polloption'] = $content;
        return view('admin.polloptions.edit', $viewdata);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  PollRequest $request
     * @param  int $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $response = $this->polloptionservice->update($request, $id);
        if (!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }
        Session::flash('message', 'polloption updated succesfully');
        return Redirect::route('admin.polloptions.index', ['content_id' => $response['results']['polloption']['content_id']]);
    }

    public function pollResult($content_id, Request $request)
    {
        $viewdata = [];
        $request['perpage'] = 10;
        $request['content_id'] = $content_id;
        $responseData = $this->contentservice->fetchResults($request);
        $viewdata['contents'] = $responseData['results']['data'];

        return view('admin.pollresults.index', $viewdata);

    }

    public function pollStats(Request $request, $content_id)
    {
        $viewdata = [];
        $request['perpage'] = 10;
        $request['content_id'] = $content_id;
        $responseData = $this->contentservice->pollStats($request);

        $viewdata['pollstats'] = !empty($responseData['results']) ? $responseData['results'] : [];
        $viewdata['total_votes'] = !empty($responseData['total_votes']) ? $responseData['total_votes'] : '';

        return view('admin.pollstats.index', $viewdata);


    }
}