<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;
use Storage;


use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Services\SettingService;
use App\Services\CustomerService;



class SettingController extends Controller
{

    protected $settingservice;

    public function __construct(SettingService $settingservice,CustomerService $customerservice)
    {
        $this->settingservice  = $settingservice;
        $this->customerservice = $customerservice;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {

        $viewdata                   =    [];
        $response                   =  $this->settingservice->showListing($request);
        $viewdata['env']            =  $request->input('env');
        $viewdata['settings']   = $response; 
        
        //dd($viewdata['settings']);
        return view('admin.extras.syncfcm', $viewdata);
    }

    public function update(Request $request)
    {
       
        $response = $this->settingservice->update($request);
       // print_pretty($response);exit;
        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }
        Session::flash('message','Setting updated succesfully');
        return Redirect::route('admin.syncfcm.index');
    }

   

}