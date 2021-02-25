<?php

namespace App\Http\Controllers\Admin;

use Input;
use Redirect;
use Config;
use Session;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Services\ArtistService;
use App\Services\PassbookService;


class PassbookController extends Controller
{

    public function __construct(
        ArtistService $artistservice,
        PassbookService $passbookService
    ){
        $this->artistservice    =   $artistservice;
        $this->passbookService  =   $passbookService;
        $this->perpage          =   15;
        $this->user_type        =   'genuine';

    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function purchasePackages(Request $request)
    {

        $viewdata                           =   [];
        $request['perpage']                 =   $this->perpage;
        $request['user_type']               =   isset($request['user_type']) ? $request['user_type'] : $this->user_type;
        $request['entity']                  =   ['packages'];

        $artists                            =   $this->artistservice->artistList();
        $responseData                       =   $this->passbookService->searchListing($request);

//        dd($responseData);exit;

        $viewdata['artists']                =   (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['platforms']              =   Config::get('app.platforms');
        $viewdata['vendor']                 =   Config::get('app.vendor');
        $viewdata['status']                 =   Config::get('app.passbook_status');
        $viewdata['user_type']              =   Config::get('app.user_type');


        $viewdata['items']                  =   (isset($responseData['items'])) ? $responseData['items'] : [];
        $viewdata['coins']                  =   (isset($responseData['coins'])) ? $responseData['coins'] : 0;
        $viewdata['amount']                 =   (isset($responseData['amount'])) ? $responseData['amount'] : 0;
        $viewdata['appends_array']          =   (isset($responseData['appends_array'])) ? $responseData['appends_array'] : [];


//        print_pretty($responseData);exit;

        return view('admin.passbooks.purchase.packages', $viewdata);
    }




    public function spendContents(Request $request)
    {


        $viewdata                           =   [];
        $request['perpage']                 =   $this->perpage;
        $request['user_type']               =   isset($request['user_type']) ? $request['user_type'] : $this->user_type;
        $request['entity']                  =   ['contents'];

        $artists                            =   $this->artistservice->artistList();
        $responseData                       =   $this->passbookService->searchListing($request);

//        dd($responseData);exit;

        $viewdata['artists']                =   (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['platforms']              =   Config::get('app.platforms');
        $viewdata['status']                 =   Config::get('app.passbook_status');
        $viewdata['user_type']              =   Config::get('app.user_type');


        $viewdata['items']                  =   (isset($responseData['items'])) ? $responseData['items'] : [];
        $viewdata['coins']                  =   (isset($responseData['coins'])) ? $responseData['coins'] : 0;
        $viewdata['amount']                 =   (isset($responseData['amount'])) ? $responseData['amount'] : 0;
        $viewdata['appends_array']          =   (isset($responseData['appends_array'])) ? $responseData['appends_array'] : [];


//        print_pretty($responseData);exit;


        return view('admin.passbooks.spend.contents', $viewdata);
    }





    public function spendGifts(Request $request)
    {


        $viewdata                           =   [];
        $request['perpage']                 =   $this->perpage;
        $request['user_type']               =   isset($request['user_type']) ? $request['user_type'] : $this->user_type;
        $request['entity']                  =   ['gifts'];

        $artists                            =   $this->artistservice->artistList();
        $responseData                       =   $this->passbookService->searchListing($request);

//        dd($responseData);exit;

        $viewdata['artists']                =   (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['platforms']              =   Config::get('app.platforms');
        $viewdata['user_type']              =   Config::get('app.user_type');


        $viewdata['items']                  =   (isset($responseData['items'])) ? $responseData['items'] : [];
        $viewdata['coins']                  =   (isset($responseData['coins'])) ? $responseData['coins'] : 0;
        $viewdata['amount']                 =   (isset($responseData['amount'])) ? $responseData['amount'] : 0;
        $viewdata['appends_array']          =   (isset($responseData['appends_array'])) ? $responseData['appends_array'] : [];


//        print_pretty($responseData);exit;


        return view('admin.passbooks.spend.gifts', $viewdata);
    }



    public function spendStickers(Request $request)
    {


        $viewdata                           =   [];
        $request['perpage']                 =   $this->perpage;
        $request['user_type']               =   isset($request['user_type']) ? $request['user_type'] : $this->user_type;
        $request['entity']                  =   ['stickers'];

        $artists                            =   $this->artistservice->artistList();
        $responseData                       =   $this->passbookService->searchListing($request);

//        dd($responseData);exit;

        $viewdata['artists']                =   (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['platforms']              =   Config::get('app.platforms');
        $viewdata['user_type']              =   Config::get('app.user_type');


        $viewdata['items']                  =   (isset($responseData['items'])) ? $responseData['items'] : [];
        $viewdata['coins']                  =   (isset($responseData['coins'])) ? $responseData['coins'] : 0;
        $viewdata['amount']                 =   (isset($responseData['amount'])) ? $responseData['amount'] : 0;
        $viewdata['appends_array']          =   (isset($responseData['appends_array'])) ? $responseData['appends_array'] : [];


//        print_pretty($responseData);exit;


        return view('admin.passbooks.spend.stickers', $viewdata);
    }



    /**
     * Display a listing of the spending on lives content.
     *
     * @return Response
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-10
     *
     */
    public function spendLives(Request $request) {

        $viewdata                   = [];
        $request['perpage']         = $this->perpage;
        $request['user_type']       = isset($request['user_type']) ? $request['user_type'] : $this->user_type;
        $request['entity']          = ['lives'];

        $artists                    = $this->artistservice->artistList();
        $responseData               = $this->passbookService->searchListing($request);

        $viewdata['artists']        = (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['platforms']      = Config::get('app.platforms');
        $viewdata['user_type']      = Config::get('app.user_type');


        $viewdata['items']          = (isset($responseData['items'])) ? $responseData['items'] : [];
        $viewdata['coins']          = (isset($responseData['coins'])) ? $responseData['coins'] : 0;
        $viewdata['amount']         = (isset($responseData['amount'])) ? $responseData['amount'] : 0;
        $viewdata['appends_array']  = (isset($responseData['appends_array'])) ? $responseData['appends_array'] : [];


        return view('admin.passbooks.spend.lives', $viewdata);
    }


    public function givenRewards(Request $request)
    {


        $viewdata                           =   [];
        $request['perpage']                 =   $this->perpage;
        $request['user_type']               =   isset($request['user_type']) ? $request['user_type'] : $this->user_type;
        $request['entity']                  =   ['rewards'];

        $artists                            =   $this->artistservice->artistList();
        $responseData                       =   $this->passbookService->searchListing($request);

//        dd($responseData);exit;

        $viewdata['artists']                =   (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['platforms']              =   Config::get('app.platforms');
        $viewdata['reward_events']          =   Config::get('app.reward_event');
        $viewdata['user_type']              =   Config::get('app.user_type');


        $viewdata['items']                  =   (isset($responseData['items'])) ? $responseData['items'] : [];
        $viewdata['coins']                  =   (isset($responseData['coins'])) ? $responseData['coins'] : 0;
        $viewdata['amount']                 =   (isset($responseData['amount'])) ? $responseData['amount'] : 0;
        $viewdata['appends_array']          =   (isset($responseData['appends_array'])) ? $responseData['appends_array'] : [];


//        print_pretty($responseData);exit;


        return view('admin.passbooks.given.rewards', $viewdata);
    }




    public function givenRecharges(Request $request)
    {


        $viewdata                           =   [];
        $request['perpage']                 =   $this->perpage;
        $request['user_type']               =   isset($request['user_type']) ? $request['user_type'] : $this->user_type;
        $request['entity']                  =   ['rechargecoins'];

        $artists                            =   $this->artistservice->artistList();
        $responseData                       =   $this->passbookService->searchListing($request);

//        dd($responseData);exit;

        $viewdata['artists']                =   (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['user_type']              =   Config::get('app.user_type');


        $viewdata['items']                  =   (isset($responseData['items'])) ? $responseData['items'] : [];
        $viewdata['coins']                  =   (isset($responseData['coins'])) ? $responseData['coins'] : 0;
        $viewdata['amount']                 =   (isset($responseData['amount'])) ? $responseData['amount'] : 0;
        $viewdata['appends_array']          =   (isset($responseData['appends_array'])) ? $responseData['appends_array'] : [];


//        print_pretty($responseData);exit;


        return view('admin.passbooks.given.recharges', $viewdata);
    }





}
