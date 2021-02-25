<?php

namespace App\Http\Controllers\Admin;

/**
 * ControllerName : Language.
 * Maintains a list of functions used for Language.
 *
 * @author      Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since       2019-05-13
 * @link        http://bollyfame.com/
 * @copyright   2019 BOLLYFAME
 * @license     http://bollyfame.com/license
 */

use Illuminate\Http\Request;

use Input;
use Redirect;
use Config;
use Session;


use App\Http\Controllers\Controller;
use App\Http\Requests\LanguageRequest;
use App\Services\LanguageService;

class LanguageController extends Controller
{
    protected $service;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function __construct(LanguageService $service) {
        $this->service = $service;
    }


    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request) {
        $viewdata                   = [];
        $data                       = $this->service->index($request);
        $viewdata['languages']      = $data['languages'];
        $viewdata['appends_array']  = $data['appends_array'];
        return view('admin.languages.index', $viewdata);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create() {
        $viewdata   = [];
        $viewdata['status'] = Config::get('app.status');

        return view('admin.languages.create', $viewdata);
    }


    /**
     * Store a newly created resource in storage.
     * @param  LanguageRequest  $request
     * @return Response
     */
    public function store(LanguageRequest $request) {
        $response = $this->service->store($request);

        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }

        Session::flash('message','Language added succesfully');

        return Redirect::route('admin.languages.index');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id) {
        $viewdata   = [];
        $viewdata['status'] = Config::get('app.status');

        $language   = $this->service->find($id);
        if($language) {
            $viewdata['language']   = $language;
        }

        return view('admin.languages.edit', $viewdata);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  LanguageRequest  $request
     * @param  int  $id
     * @return Response
     */

    public function update(LanguageRequest $request, $id) {
        $response = $this->service->update($request, $id);
        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }

        Session::flash('message','Language updated succesfully');
        return Redirect::route('admin.languages.index');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id) {
        $language = $this->service->destroy($id);
        Session::flash('message','Language deleted succesfully');
        return Redirect::route('admin.languages.index');
    }

}
