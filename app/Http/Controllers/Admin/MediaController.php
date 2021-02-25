<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Config, Redirect, Input, Session;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Services\Gcp;
use App\Services\MediaService;

class MediaController extends Controller
{
    protected $customerservice;
    protected $mediaservice;


    public function __construct(Gcp $gcp, MediaService $mediaservice)
    {
        $this->gcp = $gcp;
        $this->mediaservice = $mediaservice;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $viewdata = [];

        $response = $this->mediaservice->index($request);

        $viewdata['medias'] = $response['results'];

        return view('admin.medias.index', $viewdata);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
//        $requestData=array_except($request->all(),['_token']);

        if ($request->hasFile('photo')) {
            $upload = $request->file('photo');
            $bytes = $upload->getSize();

            if ($bytes > Config::get('app.file_size')) {
                $response['error_messages'] = 'The Photo ' . $upload->getClientOriginalName() . ' may not be greater than 900 KB';
                return Redirect::back()->withErrors(['', $response['error_messages']]);
            }
        }

        $response = $this->mediaservice->store($request);

        if (!empty($response['error_messages'])) {
            return Redirect::back()->withErrors(['', $response['error_messages']]);
        }

        Session::flash('message', 'Image Uploaded Successfully..');
        return Redirect::back();
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
}
