<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;

// use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Services\TemplateService;
use App\Http\Requests\TemplateRequest;

class TemplateController extends Controller
{

    protected $templateservice;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function __construct(TemplateService $templateService)
    {
        $this->templateservice = $templateService;
    }


    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $viewdata                       =   [];
        $responseData                   =   $this->templateservice->index($request);
        $viewdata['templates']          =   $responseData['templates'];
        $viewdata['appends_array']      =   $responseData['appends_array'];
        return view('admin.templates.index', $viewdata);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('admin.templates.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  TemplateRequest  $request
     * @return Response
     */
    public function store(TemplateRequest $request)
    {

        $template = $this->templateservice->store($request->all());
        Session::flash('message','Template added succesfully');
        return Redirect::route('admin.templates.index');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        $template = $this->templateservice->find($id);
        return view('admin.templates.edit', compact('template'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  TemplateRequest  $request
     * @param  int  $id
     * @return Response
     */
    public function update(TemplateRequest $request, $id)
    {
        $template = $this->templateservice->update($request->all(), $id);
        Session::flash('message','Template updated succesfully');
        return Redirect::route('admin.templates.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $template = $this->templateservice->destroy($id);
        Session::flash('message','Template deleted succesfully');
        return Redirect::route('admin.templates.index');
    }
}
