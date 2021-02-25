<?php

namespace App\Http\Controllers\Admin;

use Input;
use Redirect;
use Config;
use Session;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Services\PackageService;
use App\Services\ArtistService;
use App\Services\CustomerService;
use App\Services\Export\OrderExport;

class OrderController extends Controller
{

    public function __construct(
        OrderExport $orderexport,
        OrderService $orderservice,
        ArtistService $artistservice,
        PackageService $packageservice,
        CustomerService $customerservice
    )
    {
        $this->orderexport = $orderexport;
        $this->orderservice = $orderservice;
        $this->artistservice = $artistservice;
        $this->packageservice = $packageservice;
        $this->customerservice = $customerservice;

    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if(!empty($request->all()['data_report'])){
            $export = $this->orderexport->export_order($request);
            return Redirect::back()->with(array('success_message' => "Exported " . ucwords($export)." Successfully!"))->withInput();
        }
        $viewdata = [];
        $request['perpage'] = 10;
        $responseData = $this->orderservice->index($request);

//        dd($responseData);exit;

        $artists = $this->artistservice->artistList();
//      $packages=$this->packageservice->activeLists();
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];
//      $viewdata['packages']=(isset($packages['results'])) ? $packages['results'] : [];
        $viewdata['platforms'] = Config::get('app.platforms');
        $viewdata['vendor'] = Config::get('app.vendor');
        $viewdata['order_status'] = Config::get('app.order_status');
        $viewdata['user_type'] = Array('genuine' => 'Genuine', 'inactive' => 'Test');
        $viewdata['orders'] = (isset($responseData['orders'])) ? $responseData['orders'] : [];
        $viewdata['coins'] = (isset($responseData['coins'])) ? $responseData['coins'] : 0;
        $viewdata['prices'] = (isset($responseData['prices'])) ? $responseData['prices'] : 0;
        $viewdata['appends_array'] = $responseData['appends_array'];
        

        return view('admin.orders.index', $viewdata);
    }

    public function demo(Request $request, $id)
    {
        $viewdata = [];
        $request['perpage'] = 10;
        $activities = $this->customerservice->customerActivitiesDemo($request, $id);

        $viewdata['customeractivities'] = (isset($activities) && isset($activities['results']) && isset($activities['results']['activities'])) ? $activities['results']['activities'] : [];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $artists = $this->artistservice->artistList();
        $packages = $this->packageservice->activeLists();
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['packages'] = (isset($packages['results'])) ? $packages['results'] : [];


        return view('admin.orders.create', $viewdata);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $response = $this->orderservice->store($request);
        if (!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }
        Session::flash('message', 'Order added succesfully');
        return Redirect::route('admin.orders.index');
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
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(GiftRequest $request, $id)
    {

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

    public function getcustautosearch(Request $request)
    {
        $custKeyword = $request->input('customer_keyword');
        $response = $this->customerservice->getCustAutoSearch($custKeyword);

        if (!empty($response['results'])) {
            $responseData = [
                'data' => $response,
                'message' => 'Customer name content'
            ];
            return $this->responseJson($responseData, 200);
        } else {
            $responseData = [
                'data' => $response,
                'message' => 'Keywords should be more than 3 letters'
            ];
            return $this->responseJson($responseData, 400);
        }
    }
    public function update_order(Request $request){
        $order_id=$request->input('orderId');
        $response = $this->orderservice->update_order($order_id);
        $responseData = [
            'data' => $response,
            'message' => 'Order remarked as fake'
        ];
            return $this->responseJson($responseData, 200);
    }
}
