<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Services\ArtistService;
use App\Services\RoleService;
use App\Services\AgencyService;
use App\Services\CmsuserService;
;

use Input;


use Config;
use Session;
use Redirect;
use Validator;
class ProducerController extends Controller
{
    protected $artistservice;
     protected $roleservice;
    protected $agencyservice;
    protected $cmsuserservice;


    public function __construct( ArtistService $artistservice, RoleService $roleservice,  AgencyService $agencyservice , CmsuserService $cmsuserservice )
    {
         $this->artistservice = $artistservice;
          $this->roleservice = $roleservice;
         $this->agencyservice = $agencyservice;
         $this->cmsuserservice = $cmsuserservice;

         $this->page_title="My Artist";
         $this->page_desc="List of my artist";

    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $viewdata = [];
        $responseData                                       = $this->artistservice->index($request);
        $viewdata['artists']                                = $responseData['artists'];
        $viewdata['appends_array']                          = $responseData['appends_array'];
        $viewdata['page_title']                        = $this->page_title;
        $viewdata['page_desc']                         = $this->page_desc;
        return view('admin.producer.index',$viewdata);
    }

     /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {

         $viewdata   = [];   
        $viewdata['genders']        = Config::get('app.genders');
        $viewdata['date_format_dob']= Config::get('app.date_format_dob');
        $viewdata['page_title']                        = "Create Artist";
        $viewdata['page_desc']                         = "Create your artist profile.";
        return view('admin.producer.create',$viewdata);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

         $validator= Validator::make($request->all(),[
             'first_name'=>'required',
             'last_name'=>'required',
              'about_us'=>'required',
              'email'=>'required',
            'city'=>'required',
            'signature_msg'=>'required',
            'mobile'=>'required',
             'password'=>'required',
            'coins'=>'required',
             'platform'=>'required',
              'dob'=>'required',
              'picture'=>'required'

          ]);
          if ($validator->fails()) {
             return redirect('producer/create')
                         ->withErrors($validator)
                         ->withInput();
         }

      $request['stats'] = [
        'shares' => 0,
        'likes' => 0,
        'comments' => 0,
        'cold_likes' =>0,
        'followers' => 0,
        'coins' => 0,
        'sessions'=>0,
        'hot_likes'=>0
    ];
        $request['agency'] = Session::get('agency_id');

       $response = $this->cmsuserservice->store($request);     
       if(!empty($response['error_messages'])) {
             return Redirect::back()->withInput();
       }
       
       Session::flash('message','Artist added succesfully');
    //  //  return Redirect::route('admin.producer');
    //  return redirect()->back()->with('message', ' Artist added succesfully !');

     return redirect('/producer')
      ->withInput();
    }

 

       /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request,$artist_id = '')
    {
        if(empty($artist_id))
        $artist_id = $request->artist_id;       
        $viewdata = [];
        $cmsuser = $this->cmsuserservice->find($artist_id);
        $viewdata['cmsuser'] = $cmsuser;
        $roles = $this->roleservice->activelists();
        $viewdata['roles'] = (isset($roles['results'])) ? $roles['results'] : [];
        $viewdata['date_format_dob']= Config::get('app.date_format_dob');

        $viewdata['page_title']                        = "Edit Artist";
        $viewdata['page_desc']                         = "Update artist profile";

        return view('admin.producer.edit',$viewdata);
    }




}
