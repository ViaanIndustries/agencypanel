<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;


use App\Http\Controllers\Controller;
use App\Http\Requests\AuctionproductRequest;
use App\Services\AuctionproductService;
use App\Services\ArtistService;
use Config;
use Input;
use Redirect;
use Session;

class AuctionproductController extends Controller
{
    protected $auctionproductservice;
    protected $artistservice;

    public function __construct(AuctionproductService $auctionproductService, ArtistService $artistservice)
    {
        $this->auctionproductservice = $auctionproductService;
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
        $responseData = $this->auctionproductservice->index($request);
        $artists = $this->artistservice->artistList();
        
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results']->toArray() : [];

        $viewdata['auctionproducts'] = isset($responseData['results']['auctionproducts']) ? $responseData['results']['auctionproducts'] : [];
        $viewdata['appends_array'] = $responseData['results']['appends_array'];

        return view('admin.auctionproducts.index', $viewdata);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(AuctionproductRequest $request)
    {
        $viewdata = [];
        $artists = $this->artistservice->artistList();
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['status'] = Config::get('app.status');
        return view('admin.auctionproducts.create', $viewdata);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $response = $this->auctionproductservice->store($request);
        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }
        Session::flash('message','Auction product added succesfully');
        return Redirect::route('admin.auctionproducts.index');
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
        $auctionproduct = $this->auctionproductservice->find($id);
        $artists = $this->artistservice->artistList();
        $viewdata['status'] = Config::get('app.status');
        $viewdata['auctionproduct'] = $auctionproduct;
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];
        return view('admin.auctionproducts.edit', $viewdata);

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
        $response = $this->auctionproductservice->update($request, $id);
        if (!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }

        Session::flash('message', 'Auctionproduct updated succesfully');
        return Redirect::route('admin.auctionproducts.index');

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
