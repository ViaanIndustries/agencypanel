<?php

namespace App\Http\Controllers\Admin;

use Input;
use Redirect;
use Config;
use Session;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Services\ArtistService;
use App\Http\Requests\GiftRequest;
use App\Services\GiftService;

class GiftController extends Controller
{

    public function __construct(
        GiftService $giftservice,
        ArtistService $artistservice
    ){
        $this->artistservice = $artistservice;
        $this->giftservice = $giftservice;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $viewdata = [];
        $request['perpage'] = 10;
        $responseData = $this->giftservice->index($request);
        $artists = $this->artistservice->artistList();
        $viewdata['gifttypes'] = Config::get('app.gift_types');
        $viewdata['live_type'] = Config::get('app.live_type');
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['platforms'] = Config::get('app.platforms');
        $viewdata['bucket_type'] = Config::get('app.bucket_type');
        $viewdata['need_confirm'] = Config::get('app.need_confirm');

        $viewdata['gifts'] = $responseData['gifts'];
        $viewdata['appends_array'] = $responseData['appends_array'];

    //    print_pretty($viewdata);exit;
         return view('admin.gifts.index', $viewdata);
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
        $viewdata['gifttypes'] = Config::get('app.gift_types');
        $viewdata['live_type'] = Config::get('app.live_type');
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['platforms'] = Config::get('app.platforms');
        $viewdata['bucket_type'] = Config::get('app.bucket_type');
        $viewdata['need_confirm'] = Config::get('app.need_confirm');
        // return $viewdata;
        return view('admin.gifts.create', $viewdata);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(GiftRequest $request)
    {
        if ($request->hasFile('photo')) {
            $upload = $request->file('photo');
            $bytes = $upload->getSize();
            if ($bytes > Config::get('app.original_file')) { // original_file > 350 KB
                $response['error_messages'] = 'The Photo ' . $upload->getClientOriginalName() . ' may not be greater than 900 KB';
                return Redirect::back()->withErrors(['', $response['error_messages']]);
            }
        }

        if ($request->hasFile('thumb')) {
            $upload = $request->file('thumb');
            $bytes = $upload->getSize();
            if ($bytes > Config::get('app.file_size')) { // file_size > 10MB
                $response['error_messages'] = 'The Thumb IMage ' . $upload->getClientOriginalName() . ' may not be greater than 10 MB';
                return Redirect::back()->withErrors(['', $response['error_messages']]);
            }
        }


        $response = $this->giftservice->store($request);

        Session::flash('message', 'Gift added succesfully');
        return Redirect::route('admin.gifts.index');
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
        $gift = $this->giftservice->find($id);
        $artists = $this->artistservice->artistList();
        $viewdata['gifttypes'] = Config::get('app.gift_types');
        $viewdata['live_type'] = Config::get('app.live_type');
        $viewdata['gift'] = $gift;
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['platforms'] = Config::get('app.platforms');
        $viewdata['bucket_type'] = Config::get('app.bucket_type');
        $viewdata['need_confirm'] = Config::get('app.need_confirm');

        return view('admin.gifts.edit', $viewdata);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(GiftRequest $request, $id)
    {
        if ($request->hasFile('photo')) {
            $upload = $request->file('photo');
            $bytes = $upload->getSize();
            if ($bytes > Config::get('app.original_file')) { // original_file > 10MB
                $response['error_messages'] = 'The Photo ' . $upload->getClientOriginalName() . ' may not be greater than 10B';
                return Redirect::back()->withErrors(['', $response['error_messages']]);
            }
        }

        if ($request->hasFile('thumb')) {
            $upload = $request->file('thumb');
            $bytes = $upload->getSize();
            if ($bytes > Config::get('app.file_size')) { // file_size > KB
                $response['error_messages'] = 'The Thumb IMage ' . $upload->getClientOriginalName() . ' may not be greater than 10 MB';
                return Redirect::back()->withErrors(['', $response['error_messages']]);
            }
        }


        $response = $this->giftservice->update($request, $id);
//        if (!empty($response['error_messages'])) {
//            return Redirect::back()->withInput();
//        }

        Session::flash('message', 'Gift updated succesfully');
        return Redirect::route('admin.gifts.index');
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
