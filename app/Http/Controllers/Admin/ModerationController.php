<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use App\Http\Requests;

use App\Http\Controllers\Controller;

use App\Services\ModerationService;
use App\Services\ArtistService;


class ModerationController extends Controller
{
    protected $moderationservice;
    protected $artistservice;

    public function __construct(
        ModerationService $moderationService,
        ArtistService $artistService
    )
    {
        $this->moderationservice = $moderationService;
        $this->artistservice = $artistService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
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
        //
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
        //
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

    public function moderationContent(Request $request)
    {
        $viewdata = [];
        $responseData = $this->moderationservice->moderationContent($request);
        $artists = $this->artistservice->artistList();
        $viewdata['artists'] = !empty($artists['results']) ? $artists['results'] : [];
        $viewdata['contents'] = !empty($responseData['results']['contents']) ? $responseData['results']['contents'] : [];
        $viewdata['moderation_entites'] = ['content' => 'Content', 'comment' => 'Comment', 'user' => 'User'];
        $viewdata['status'] = ['active' => 'Active', 'inactive' => 'Inactive', 'banned' => 'Banned'];
        $viewdata['appends_array'] = $responseData['results']['appends_array'];
print_b($viewdata);
        return view('admin.moderation.content', $viewdata);

    }

    public function moderationComment()
    {
        $viewdata = [];
        $responseData = $this->moderationservice->moderationComment();
        $artists = $this->artistservice->artistList();
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['fans'] = !empty($responseData['results']['fans']) ? $responseData['results']['fans'] : [];
        $viewdata['appends_array'] = $responseData['results']['appends_array'];

        return view('admin.moderation.comment', $viewdata);

    }

    public function moderationCustomer()
    {
        $viewdata = [];
        $responseData = $this->moderationservice->moderationUser();
        $artists = $this->artistservice->artistList();
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['fans'] = !empty($responseData['results']['fans']) ? $responseData['results']['fans'] : [];
        $viewdata['appends_array'] = $responseData['results']['appends_array'];

        return view('admin.moderation.user', $viewdata);

    }
}
