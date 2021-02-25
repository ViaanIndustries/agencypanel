<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Services\RedisDb;
use App\Http\Controllers\Controller;

class MonitoringController extends Controller
{
    protected $redisdb;

    public function __construct(RedisDb $redisdb)
    {
        $this->redisdb = $redisdb;
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

    public function redismonitoring()
    {
        $viewdata = [];
        $responseData = $this->redisdb->monitoringLogs();
        $viewdata['redis'] = $responseData;
        return view('admin.monitoring.redis', $viewdata);
    }

    public function rediflushall($redisIP = null)
    {
        $viewdata = [];
        
        $response = !empty($redisIP)?$this->redisdb->flushall($redisIP):$this->redisdb->flushall();
        $responseData = $this->redisdb->monitoringLogs();
        $viewdata['redis'] = $responseData;
        return view('admin.monitoring.redis', $viewdata);
    }
}
