<?php

namespace App\Http\Controllers\Admin;

// use Illuminate\Http\Request;
use App\Services\Export\CustomerExport;
use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;


use App\Http\Controllers\Controller;
use App\Services\ArtistService;
use App\Http\Requests\CustomerRequest;
use App\Services\CustomerService;
use App\Services\RewardService;
use App\Services\PassbookService;

use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class CustomerController extends Controller
{

    protected $customerservice;
    protected $artistservice;
    protected $passbookService;
    protected $customerexport;


    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function __construct(CustomerService $customerservice, ArtistService $artistservice, RewardService $rewardservice, PassbookService $passbookService, CustomerExport $customerexport)
    {
        $this->customerservice = $customerservice;
        $this->artistservice = $artistservice;
        $this->rewardservice = $rewardservice;
        $this->passbookService = $passbookService;
        $this->customerexport   = $customerexport;

    }


    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $viewdata = [];
        $responseData                                       = $this->customerservice->index($request);

//        dd($responseData);
        $totalCoinsInCustomerWalletAvailable                = $this->customerservice->totalCoinsInCustomerWalletAvailable($request);
        $artists                                            = $this->artistservice->artistList();
        $viewdata['artists']                                = (isset($artists['results'])) ? $artists['results']->toArray() : [];
        $viewdata['customers']                              = $responseData['customers'];
        $viewdata['totalCoinsInCustomerWalletAvailable']    = $totalCoinsInCustomerWalletAvailable;
        $viewdata['appends_array']                          = $responseData['appends_array'];

        return view('admin.customers.index', $viewdata);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('admin.customers.create');
    }


    /**
     * Store a newly created resource in storage.
     * @param  CustomerRequest $request
     * @return Response
     */
    public function store(CustomerRequest $request)
    {
        $response = $this->customerservice->store($request);
        if (!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }
        Session::flash('message', 'Customer added succesfully');
        return Redirect::route('admin.customers.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return Response
     */
    public function edit($id)
    {
        $viewdata = [];
        $customer = $this->customerservice->find($id);
        $viewdata['date_format_dob']= Config::get('app.date_format_dob');

        if (!empty($customer['error_messages'])) {
            return Redirect::back()->withInput();
        }
        $viewdata['customer'] = $customer['results'];
        return view('admin.customers.edit', $viewdata);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  CustomerRequest $request
     * @param  int $id
     * @return Response
     */

    public function update(CustomerRequest $request, $id)
    {
        if ($request->hasFile('picture')) {
            $upload = $request->file('picture');
            $bytes = $upload->getSize();
            if ($bytes > Config::get('app.file_size')) { // filesize > 350 KB
                $response['error_messages'] = 'The picture ' . $upload->getClientOriginalName() . ' may not be greater than 900 KB';
                return Redirect::back()->withErrors(['', $response['error_messages']]);
            }
        }

        $response = $this->customerservice->update($request, $id);
//        if (!empty($response['error_messages'])) {
//            return Redirect::back()->withInput();
//        }

        Session::flash('message', 'Customer updated succesfully');
        return Redirect::route('admin.customers.index');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return Response
     */
    public function destroy($id)
    {
        $blog = $this->customerservice->destroy($id);
        Session::flash('message', 'Customer deleted succesfully');
        return Redirect::route('admin.customers.index');
    }

    public function showInfo($id)
    {
        $viewdata = [];
        $response = $this->customerservice->show($id);
        $viewdata['customer'] = (isset($response) && isset($response['results']) && isset($response['results']['customer'])) ? $response['results']['customer'] : [];

        return view('admin.customers.showwithlinks', $viewdata);
    }

    public function showDevices($id)
    {
        $viewdata = [];
        $response = $this->customerservice->show($id);
        $viewdata['customer'] = (isset($response) && isset($response['results']) && isset($response['results']['customer'])) ? $response['results']['customer'] : [];
        $viewdata['customerdevices'] = (isset($response) && isset($response['results']) && isset($response['results']['customerdevices'])) ? $response['results']['customerdevices'] : [];
        return view('admin.customers.showdevices', $viewdata);
    }

    public function showActivities(Request $request, $id)
    {
        $viewdata = [];
        $request['perpage'] = 10;
        $response = $this->customerservice->show($id);
        $viewdata['customer'] = (isset($response) && isset($response['results']) && isset($response['results']['customer'])) ? $response['results']['customer'] : [];
        $activities = $this->customerservice->customerActivities($request, $id);
        $viewdata['customeractivities'] = (isset($activities) && isset($activities['results']) && isset($activities['results']['activities'])) ? $activities['results']['activities'] : [];
        $artists = $this->artistservice->artistList();
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['activityList'] = Config::get('app.activityList');
        $viewdata['appends_array'] = (isset($activities) && isset($activities['results']) && isset($activities['results']['appends_array'])) ? $activities['results']['appends_array'] : [];

        return view('admin.customers.showactivities', $viewdata);
    }

    public function showArtistInfo(Request $request, $id)
    {
        $viewdata = [];
        $request['perpage'] = 10;
        $response = $this->customerservice->show($id);
        $viewdata['customer'] = (isset($response) && isset($response['results']) && isset($response['results']['customer'])) ? $response['results']['customer'] : [];
        $inforesult = $this->customerservice->getArtistInfo($request, $id);
        $viewdata['artistinfo'] = (isset($inforesult) && isset($inforesult['results']) && isset($inforesult['results']['artistinfo'])) ? $inforesult['results']['artistinfo'] : [];
        return view('admin.customers.showartistinfo', $viewdata);
    }

    public function showRewards(Request $request, $id)
    {
        $viewdata = [];

        $rewardsResponse = $this->rewardservice->getRewardsForCustomer($request, $id);
        $viewdata['rewards'] = $rewardsResponse['rewards'];
        $viewdata['appends_array'] = (isset($rewardsResponse) && isset($rewardsResponse['appends_array'])) ? $rewardsResponse['appends_array'] : [];
        $viewdata['reward_types'] = Config::get('app.reward_title');
        return view('admin.customers.showrewards', $viewdata);


    }


    public function showPassbook(Request $request, $id)
    {


        $viewdata           =   [];
        $requestData        =   $request->all();
        $perpage            =   20;
        $entity_id          =   (!empty($item['entity_id'])) ? trim($item['entity_id']) : '';
        $artist_id          =   (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $platform           =   (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $customer_name      =   (isset($requestData['customer_name']) && $requestData['customer_name'] != '') ? $requestData['customer_name'] : '';
        $user_type          =   (isset($requestData['user_type']) && $requestData['user_type'] != '') ? $requestData['user_type'] : '';
        $txn_id             =   (isset($requestData['txn_id']) && $requestData['txn_id'] != '') ? $requestData['txn_id'] : '';
        $vendor_txn_id      =   (isset($requestData['vendor_txn_id']) && $requestData['vendor_txn_id'] != '') ? $requestData['vendor_txn_id'] : '';
        $vendor             =   (isset($requestData['vendor']) && $requestData['vendor'] != '') ? $requestData['vendor'] : '';
        $reward_event       =   (isset($requestData['reward_event']) && $requestData['reward_event'] != '') ? $requestData['reward_event'] : '';
        $status             =   (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : 'success';
        $created_at         =   (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end     =   (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';

        $appends_array = [
            'entity_id' => $entity_id,
            'artist_id' => $artist_id,
            'platform' => $platform,
            'customer_name' => $customer_name,
            'user_type' => $user_type,
            'txn_id' => $txn_id,
            'vendor_txn_id' => $vendor_txn_id,
            'vendor' => $vendor,
            'status' => $status,
            'reward_event' => $reward_event,
            'created_at' => $created_at,
            'created_at_end' => $created_at_end,

        ];

        $request['customer_id']         =   $id;
        $request['perpage']             =   $perpage;
        $passbookResults                =   $this->passbookService->customerPassbookAdmin($request);
        $customerResponse               =   $this->customerservice->show($id);
        $artsitListResponse             =   $this->artistservice->artistList();

        $items                          =   (!empty($passbookResults['results']) && !empty($passbookResults['results']['list'])) ? $passbookResults['results']['list'] : [];
        $total                          =   (!empty($passbookResults['results']) && !empty($passbookResults['results']['paginate_data']) && !empty($passbookResults['results']['paginate_data']['total'])) ? intval($passbookResults['results']['paginate_data']['total']) : 0;
        $perpage                        =   (!empty($passbookResults['results']) && !empty($passbookResults['results']['paginate_data']) && !empty($passbookResults['results']['paginate_data']['per_page'])) ? intval($passbookResults['results']['paginate_data']['per_page']) : $perpage;
        $page                           =   (!empty($passbookResults['results']) && !empty($passbookResults['results']['paginate_data']) && !empty($passbookResults['results']['paginate_data']['current_page'])) ? intval($passbookResults['results']['paginate_data']['current_page']) : 1;


//        print_pretty($passbookResults);exit;
        //        var_dump($total);var_dump($perpage);var_dump($page);print_pretty($items);exit;
        $passbookItems = new Paginator($items, $total, $perpage, $page, [
            'path'  => $request->url(),
            'query' => $request->query(),
        ]);


        $viewdata['artists']                    =   (isset($artsitListResponse['results'])) ? $artsitListResponse['results'] : [];
        $viewdata['customer']                   =   (isset($customerResponse) && isset($customerResponse['results']) && isset($customerResponse['results']['customer'])) ? $customerResponse['results']['customer'] : [];
        $viewdata['items']                      =   $passbookItems;
        $viewdata['appends_array']              = $appends_array;

        return view('admin.customers.showpassbook', $viewdata);


    }



    public function showCachePurchasesContents(Request $request, $id){

        $viewdata                   =   [];
        $requestData                =   array_except($request->all(), ['_method', '_token']);
        $customer_id                =   $id;
        $response                   =   $this->customerservice->show($customer_id);
        $viewdata['customer']       =   (isset($response) && isset($response['results']) && isset($response['results']['customer'])) ? $response['results']['customer'] : [];
        $artists                    =   $this->artistservice->artistList();
        $viewdata['artist_info']    =   (isset($artists['results'])) ? $artists['results']->toArray() : [];

        $type       = isset($requestData['type']) ? $requestData['type'] : '';
        $artist_id  = isset($requestData['artist_id']) ? $requestData['artist_id'] : '';
        $viewdata['artist_id'] = $artist_id;

        if($type == 'purge') {
            $requestData['purge'] = 'true';
        }
        else {
            $requestData['purge'] = '';
        }

        if(isset($requestData['artist_id']) && $requestData['artist_id']) {
            $requestData['customer_id'] = $customer_id;

            $dataResponse = $this->customerservice->getPurchaseContentsMetaIds($requestData);

            $viewdata['purchase_content_ids']   = (isset($dataResponse) && isset($dataResponse['results']) && isset($dataResponse['results']['purchase_content_ids'])) ? $dataResponse['results']['purchase_content_ids'] : [];

            $viewdata['purchase_content_ids_cache']   = (isset($dataResponse) && isset($dataResponse['results']) && isset($dataResponse['results']['purchase_content_ids_cache'])) ? $dataResponse['results']['purchase_content_ids_cache'] : [];

            if($viewdata['purchase_content_ids']) {
                $purchase_contents = \App\Models\Content::where('status', '=', 'active')->whereIn('_id', $viewdata['purchase_content_ids'])->get(['_id', 'name', 'caption', 'type', 'photo','video', 'audio'])->toArray();
                if($purchase_contents) {
                    $viewdata['purchase_contents']   = $purchase_contents;
                }
            }
        }

        return view('admin.customers.showcachepurchasescontents', $viewdata);
    }




    public function purgePurchaseContentsMetaIds(Request $request){


        if (empty($request['customer_id'])) {
            return Redirect::back()->withErrors(['', 'Customer field is required']);
        }


        if (empty($request['artist_id'])) {
            return Redirect::back()->withErrors(['', 'Artist field is required']);
        }

        $requestData        =   array_except($request->all(), ['_method', '_token']);
        $artist_id          =   $requestData['artist_id'];
        $customer_id        =   $requestData['customer_id'];

        $sendNotification   =   $this->customerservice->purgePurchaseContentsMetaIds($requestData);

        Session::flash('message', 'Purge purchase content ids  successfully');
        return Redirect::route('admin.customers.showcachepurchasescontents', ['customerid' => $customer_id]);
    }



    public function ordersReport(Request $request)
    {
        $viewdata = [];

        $responseData = $this->customerservice->ordersReport($request);
        $artists = $this->artistservice->artistList();
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results']->toArray() : [];
        $viewdata['top_performing_users'] = $responseData['results']['topCustomerOrderWise'];
        $viewdata['appends_array'] = $responseData['results']['appends_array'];
        return view('admin.reports.ordersreport', $viewdata);
    }


    public function addcoins($customer_id)
    {
        $viewdata = [];
        $customer = $this->customerservice->find($customer_id);

        $viewdata['customer'] = $customer['results'];

//        $artist_id = $customer['results']['artists'];
//        if (!empty($artist_id)) {
//            $artist_info = $this->artistservice->artistListIdWise($artist_id);
//        }
//        $viewdata['artist_info'] = !empty($artist_info) ? $artist_info->toArray() : [];

        $artists = $this->artistservice->artistList();
        $viewdata['artist_info'] = (isset($artists['results'])) ? $artists['results']->toArray() : [];

        if (Session::has('user_first_name')) {
            $viewdata['loggedin_user_first_name'] = Session::get('user_first_name');
        }

        if (Session::has('user_last_name')) {
            $viewdata['loggedin_user_last_name'] = Session::get('user_last_name');
        }

        return view('admin.customers.addcoins', $viewdata);

    }

    public function customerAddCoins(Request $request)
    {
        if (empty($request['artist_id'])) {
            return Redirect::back()->withErrors(['', 'Artist field is required']);
        }
        if (empty($request['refund_coins'])) {
            return Redirect::back()->withErrors(['', 'Coins field is required']);
        }
        if (empty($request['remark'])) {
            return Redirect::back()->withErrors(['', 'Remark field is required']);
        }


        if (Session::has('user_id')) {
            $request['loggedin_user_id'] = Session::get('user_id');
        }

        $addCoins = $this->customerservice->customerAddCoins($request);

        Session::flash('message', 'Notification send successfully');
        return Redirect::route('admin.customers.index');
    }

    public function customershowsendnotification($customer_id)
    {
        $viewdata = [];
        $customer = $this->customerservice->find($customer_id);

        $viewdata['customer'] = $customer['results'];

        $artist_id = $customer['results']['artists'];

        if (!empty($artist_id)) {
            $artist_info = $this->artistservice->artistListIdWise($artist_id);
        }

        $viewdata['artist_info'] = !empty($artist_info) ? $artist_info->toArray() : [];

        $code = \App\Models\Bucket::active()->where('status', 'active')->lists('code', 'code')->toArray();
        $viewdata['code'] = array_merge(['select bucket type'], $code);

        return view('admin.customers.sendnotification', $viewdata);
    }

    public function customersendnotification(Request $request)
    {
        if (empty($request['artist_id'])) {
            return Redirect::back()->withErrors(['', 'Artist field is required']);
        }

        $sendNotification = $this->customerservice->sendNotification($request);
        Session::flash('message', 'Notification send successfully');
        return Redirect::route('admin.customers.index');
    }

    public function coinsreports(Request $request)
    {
        $viewdata = [];
        $responseData = $this->customerservice->fetchCoinsReports($request);

        $artists = $this->artistservice->artistList();
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results']->toArray() : [];
        $viewdata['coins_logs'] = $responseData['results']['coins_logs'];
        $viewdata['appends_array'] = $responseData['results']['appends_array'];

        return view('admin.reports.coinslogsreport', $viewdata);
    }

    public function customershowcoinsreport($customer_id)
    {
        $viewdata = [];

        $request['customer_id'] = $customer_id;

        $responseData = $this->customerservice->fetchCoinsReports($request);

        $viewdata['coins_logs'] = $responseData['results']['coins_logs'];

        $id['id'] = $customer_id;
        $viewdata['customer'] = (object)$id;

        return view('admin.customers.showcoinsreport', $viewdata);
    }

    public function ExportCustomers(Request $request)
    {
         $export = $this->customerexport->export_customer();
    }

}
