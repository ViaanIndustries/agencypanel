<?php

namespace App\Http\Controllers\Admin;

use Input;
use Redirect;
use Config;
use Session;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Services\ArtistService;
use App\Services\PurchaseService;

class PurchaseController extends Controller
{
    public function __construct(
        PurchaseService $purchaseservice,
        ArtistService $artistservice
    )
    {
        $this->artistservice = $artistservice;
        $this->purchaseservice = $purchaseservice;
    }

    public function index(Request $request)
    {
        $viewdata = [];
        $request['perpage'] = 10;
        $responseData = $this->purchaseservice->index($request);

        $artists = $this->artistservice->artistList();

        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results']->toArray() : [];
        $viewdata['purchase_entites'] = Config::get('app.purchase_entites');
        $viewdata['user_type'] = Array('genuine' => 'Normal user', 'fake' => 'Test user');
        $viewdata['purchases'] = (isset($responseData['purchases'])) ? $responseData['purchases'] : [];
        $viewdata['coins'] = (isset($responseData['coins'])) ? $responseData['coins'] : 0;
        $viewdata['appends_array'] = (isset($responseData['appends_array'])) ? $responseData['appends_array'] : [];

//        return $viewdata;
        return view('admin.purchases.index', $viewdata);
    }


}