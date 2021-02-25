<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use Input;
use Redirect;
use Config;
use Session;

use App\Services\ArtistService;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Services\Export\DailystatsExportImport;

class DailystatsController extends Controller
{
    protected $artistservice;

    public function __construct(ArtistService $artistservice, DailystatsExportImport $dailystatsexportimport)
    {
        $this->artistservice = $artistservice;
        $this->dailystatsexportimport = $dailystatsexportimport;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        if (!empty($request['dailystats_download'])) {
            $export = $this->dailystatsexportimport->export_dailystats($request);
            return Redirect::back()->with(array('success_message' => "Exported " . ucwords($export) . " Successfully!"))->withInput();
        }

        $viewdata = [];
        $responseData = $this->artistservice->getDailystats($request);

        $artists = $this->artistservice->artistList();
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results']->toArray() : [];
        $viewdata['dailystats'] = !empty($responseData['results']['dailystats']) ? $responseData['results']['dailystats'] : [];

        $viewdata['appends_array'] = $responseData['results']['appends_array'];

        return view('admin.dailystats.index', $viewdata);
    }

    public function importExcel(Request $request)
    {
        if (!Input::hasFile('dailystats_import')) {
            return Redirect::back()->withErrors(['', 'No file chosen to Import']);
        }

        $export = $this->dailystatsexportimport->import_dailystats();

        return Redirect::route('admin.dailystats.index');
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
        $viewdata['status'] = Array('active' => 'Active', 'inactive' => 'Inactive');
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];

        return view('admin.dailystats.create', $viewdata);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $response = $this->artistservice->storeDailyStats($request);

        if (!empty($response['error_messages'])) {
            return Redirect::back()->withErrors(['', $response['error_messages']]);
        }
        Session::flash('message', 'Added succesfully');
        return Redirect::route('admin.dailystats.index');
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
        $dailystats_id_wise = $this->artistservice->getDailyStatsIdWise($id);
        $artists = $this->artistservice->artistList();
        $viewdata['status'] = Array('active' => 'Active', 'inactive' => 'Inactive');
        $viewdata['dailystats'] = $dailystats_id_wise['results'];
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];

        return view('admin.dailystats.edit', $viewdata);
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
        $data = array_except($request->all(), ['_token', '_method']);

        $response = $this->artistservice->updateDailyStats($data, $id);

        if (!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }

        Session::flash('message', 'Updated succesfully');
        return Redirect::route('admin.dailystats.index');
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
