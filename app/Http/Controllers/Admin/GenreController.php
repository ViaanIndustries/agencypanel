<?php

namespace App\Http\Controllers\Admin;

/**
 * ControllerName : Genre.
 * Maintains a list of functions used for Genre.
 *
 * @author Ruchi <ruchi.sharma@bollyfame.com>
 * @since 2019-07-23
 * @link http://bollyfame.com/
 * @copyright 2019 BOLLYFAME
 * @license http://bollyfame.com//license/
 */

use Illuminate\Http\Request;

use Input;
use Redirect;
use Config;
use Session;


use App\Http\Controllers\Controller;
use App\Http\Requests\GenreRequest;
use App\Services\GenreService;

class GenreController extends Controller
{
    protected $genreservice;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function __construct(GenreService $genreservice)
    {
        $this->genreservice = $genreservice;
    }


    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $viewdata                   = [];
        $responseData               = $this->genreservice->index($request);
        $viewdata['genres']         = $responseData['genres'];
        $viewdata['appends_array']  = $responseData['appends_array'];
        return view('admin.genres.index', $viewdata);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $viewdata   = [];
        $viewdata['status'] = Config::get('app.status');

        return view('admin.genres.create', $viewdata);
    }


    /**
     * Store a newly created resource in storage.
     * @param  GenreRequest  $request
     * @return Response
     */
    public function store(GenreRequest $request)
    {
        $response = $this->genreservice->store($request);
        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }
        Session::flash('message','Genre added succesfully');
        return Redirect::route('admin.genres.index');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        $viewdata   = [];
        $viewdata['status'] = Config::get('app.status');

        $genre   = $this->genreservice->find($id);
        if($genre) {
            $viewdata['genre']   = $genre;
        }

        return view('admin.genres.edit', $viewdata);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  GenreRequest  $request
     * @param  int  $id
     * @return Response
     */

    public function update(GenreRequest $request, $id)
    {
        $response = $this->genreservice->update($request, $id);
        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }

        Session::flash('message','Genre updated succesfully');
        return Redirect::route('admin.genres.index');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $blog = $this->genreservice->destroy($id);
        Session::flash('message','Genre deleted succesfully');
        return Redirect::route('admin.genres.index');
    }

}
