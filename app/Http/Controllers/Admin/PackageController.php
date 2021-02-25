<?php

namespace App\Http\Controllers\Admin;

use Input;
use Redirect;
use Config;
use Session;

use Illuminate\Http\Request;

//use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Services\ArtistService;
use App\Http\Requests\PackageRequest;
use App\Services\PackageService;
use App\Models\Package as Package;

class PackageController extends Controller
{

    /**
     * Constructor
     *
     * @return Response
     */
    public function __construct(PackageService $packageService, ArtistService $artistservice)
    {
        $this->packageService = $packageService;
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
        $request['perpage'] = 10;
        $responseData = $this->packageService->index($request);
        $viewdata['platforms'] = Config::get('app.platforms');
        $viewdata['packages'] = $responseData['packages'];
        $viewdata['appends_array'] = $responseData['appends_array'];
        $artists = $this->artistservice->artistList();
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];
        return view('admin.packages.index', $viewdata);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

        $viewdata = [];
        $viewdata['platforms'] = Config::get('app.platforms');
        $artists = $this->artistservice->artistList();
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];
        return view('admin.packages.create', $viewdata);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(PackageRequest $request)
    {
        $response = $this->packageService->store($request);
        if (!empty($response['error_messages'])) {
            return Redirect::back()->withErrors(['', $response['error_messages']]);
        }
        Session::flash('message', 'Package added succesfully');
        return Redirect::route('admin.packages.index');
    }

    public function getPackagesForArtist(Request $request)
    {
        $artist_id = $request->get('artist_id', '');
        $msg = '';
        $packages = Package::whereIn('artists', [$artist_id])->get();
        $data = array();
        foreach ($packages as $package) {
            $data['results'][] = array('id' => $package->_id, 'value' => $package->name);
        }
        if (count($data))
            return json_encode($data);
        else {
            $data['results'][] = ['value' => 'No Result Found', 'id' => ''];
            return json_encode($data);
        }
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
        $package = $this->packageService->find($id);
        $viewdata['package'] = $package;
        $artists = $this->artistservice->artistList();
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['platforms'] = Config::get('app.platforms');
        //return $viewdata;
        return view('admin.packages.edit', $viewdata);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(PackageRequest $request, $id)
    {
        $response = $this->packageService->update($request, $id);
        if (!empty($response['error_messages'])) {
            return Redirect::back()->withErrors(['', $response['error_messages']]);
        }

        Session::flash('message', 'Package updated succesfully');
        return Redirect::route('admin.packages.index');
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
