<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\CustomerInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use Config;
use App\Services\Jwtauth;
use Request;
use Carbon;
use App\Services\RedisDb;

class CustomerRepository extends AbstractRepository implements CustomerInterface
{

    protected $modelClassName = 'App\Models\Customer';

    protected $jwtauth;
    protected $redisdb;


    public function __construct(Jwtauth $jwtauth, RedisDb $redisDb)
    {
        $this->jwtauth = $jwtauth;
        $this->redisdb = $redisDb;
        parent::__construct();
    }


    public function getDashboardStatsQuery($requestData)
    {
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $created_at = (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end = (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';

//        $query = \App\Models\Customer::with('artists')->orderBy('created_at', 'desc');
        $query = \App\Models\Customer::orderBy('created_at', 'desc');

        if ($created_at != '') {
            $query->where('created_at', '>=', new \DateTime(date("d-m-Y", strtotime($created_at))));
        }

        if ($artist_id != '') {
            $query->whereIn('artists', (array)$artist_id);
        }

        if ($created_at_end != '') {
            $query->where('created_at', '<=', new \DateTime(date("d-m-Y", strtotime($created_at_end))));
        }

        if ($platform != '') {
            $query->where('platform', $platform);
        }

        if ($status != '') {
            $query->where('status', $status);
        }
        $customer_name = '';
        $query->GenuineCustomers($customer_name);


        return $query;

    }


    public function getArtistWiseCustomerStats($requestData)
    {
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';

        if (!empty($artist_id)) {
            $artists = \App\Models\Cmsuser::where('status', '=', 'active')->where('_id', $artist_id)->whereIn('is_contestant', ['false', null])->get(['first_name', 'last_name', '_id'])->toArray();
            $artist_ids = array($artist_id);
        } else {
            $artist_role_ids = \App\Models\Role::where('slug', 'artist')->lists('_id');
            $artist_role_ids = ($artist_role_ids) ? $artist_role_ids->toArray() : [];
            $artists = \App\Models\Cmsuser::where('status', '=', 'active')->whereIn('roles', $artist_role_ids)->whereIn('is_contestant', ['false', null])->get(['first_name', 'last_name', '_id'])->toArray();
            $artist_ids = array_pluck($artists, '_id');
        }

        $artistwise_customers = \App\Models\Customerartist::raw(function ($collection) use ($artist_ids) {

            $aggregate = [
                [
                    '$match' => [
                        'artist_id' => ['$in' => $artist_ids],
                    ]
                ],
                [
                    '$group' => [
                        '_id' => ['artist_id' => '$artist_id'],
                        'total_customers' => ['$sum' => 1]
                    ]
                ],
                [
                    '$project' => [
                        '_id' => '$_id.artist_id',
                        'artist_id' => '$_id.artist_id',
                        'total_customers' => '$total_customers'
                    ]
                ]
            ];
            return $collection->aggregate($aggregate);
        });

        $artistsArr = [];
        $artistwise_customers = $artistwise_customers->toArray();

        foreach ($artists as $artist) {

            $artist_id = $artist['_id'];
            $artistobj = $artist;
            $name = $artist['first_name'] . ' ' . $artist['last_name'];
            $stats = head(array_where($artistwise_customers, function ($key, $value) use ($artist_id) {
                if ($value['_id'] == $artist_id) {
                    return $value;
                }
            }));

            $stats = (!is_array($stats)) ? [] : $stats;
            $artist_info = array_merge($artist, $stats, ['name' => ucwords($name)]);
            array_push($artistsArr, $artist_info);
        }
        return $artistsArr;

    }


    public function paginate($perpage = NULL)
    {
        $perpage = ($perpage == NULL) ? \Config::get('app.perpage') : intval($perpage);
        $customers = \App\Models\Customer::paginate($perpage);
        return $customers;
    }


    public function paginateForApi($perpage = NULL)
    {
        $perpage = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
        $data = $this->model->orderBy('_id')->paginate($perpage)->toArray();

        $responeData = [];
        $responeData['list'] = (isset($data['data'])) ? $data['data'] : [];
        $responeData['paginate_data']['total'] = (isset($data['total'])) ? $data['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($data['current_page'])) ? $data['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($data['last_page'])) ? $data['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($data['from'])) ? $data['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($data['to'])) ? $data['to'] : 0;

        return $responeData;
    }


    public function getArtistInfo($requestData, $customer_id)
    {
        $perpage = ($requestData['perpage'] == NULL) ? Config::get('app.perpage') : intval($requestData['perpage']);
        $name = (isset($requestData['name']) && $requestData['name'] != '') ? $requestData['name'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';;
        $appends_array = array('name' => $name, 'status' => $status, 'artist_id' => $artist_id);

        $query = \App\Models\Customerartist::with('artist')->where('customer_id', $customer_id)->orderBy('fan_xp', 'desc');

        if ($name != '') {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }
        $artistinfos = $query->paginate($perpage);
        $artistinfos->getCollection()->transform(function ($artistinfo, $key) {

            $artistinfoData = $artistinfo;
            $send_free_gifts = isset($artistinfoData['send_free_gifts']) ? $artistinfoData['send_free_gifts'] : [];
            $free_gifts_arr = [];

            foreach ($send_free_gifts as $key => $value) {
                $giftObj = \App\Models\Gift::where('_id', $value['id'])->first();
                $free_gifts_arr[] = [
                    'name' => $giftObj['name'],
                    'available_coins' => $giftObj['free_limit'] - $value['consumed'],
                    'free_coins' => $giftObj['free_limit'],
                    'consumed' => $value['consumed']
                ];
                $artistinfoData['send_free_gifts'] = $free_gifts_arr;
            }

            return $artistinfoData;
        });
        $results['artistinfo'] = $artistinfos;
        $results['appends_array'] = $appends_array;
        return $results;
    }

    public function store($postdata)
    {
        $error_messages = array();
        $data = array_except($postdata, ['password_confirmation']);
        $user = new $this->model($data);
        $user->save();
        return $user;
    }


    public function update($postdata, $id)
    {

        $error_messages = array();
        $data = array_except($postdata, ['password_confirmation', 'email', 'password']);
        $user = $this->model->findOrFail(trim($id));
        $user->update($data);
        return $user;
    }


    public function register($postdata)
    {
        $error_messages = array();
        $data = array_except($postdata, ['password_confirmation', 'image_url']);
        $email = trim(strtolower($data['email']));

        //if customer
        $customer = \App\Models\Customer::where('email', '=', $email)->first();
        if ($customer) {
            $account_link = $customer->account_link;
            $account_link[$data['identity']] = 1;
            $data['account_link'] = $account_link;
            $data = array_except($data, ['identity', 'password', 'password_confirmation', 'device_id', 'segment_id', 'fcm_id', 'platform', 'coins']);
            $customer = \App\Models\Customer::where('email', '=', $email)->first();
            $customer->save();


        } else {
            $account_link = array('email' => 0, 'google' => 0, 'facebook' => 0, 'twitter' => 0);
            $account_link[$data['identity']] = 1;
            $data['account_link'] = $account_link;
            $data['status'] = 'active';
            $data = array_except($data, ['password_confirmation', 'device_id', 'segment_id', 'fcm_id', 'platform', 'coins']);
            $customer = new \App\Models\Customer($data);
            $customer->save();

        }

        $customer = \App\Models\Customer::where('email', '=', $email)->first();

        $platform = (request()->header('platform')) ? trim(request()->header('platform')) : "";
        $artist = (request()->header('artistid')) ? trim(request()->header('artistid')) : "";

        if ($platform != '') {
            $customer->push('platforms', trim(strtolower($platform)), true);
        }
        if ($artist != '') {
            $customer->push('artists', trim(strtolower($artist)), true);
        }

        return $customer;
    }


    public function customerLists()
    {
        $customers = \App\Models\Customer::where('status', '=', 'active')->lists('first_name', '_id');
        return $customers;
    }

    public function profile($request)
    {

        $customer_id = $this->jwtauth->customerIdFromToken();
        $artist_id = (request()->header('artistid')) ? trim(request()->header('artistid')) : "";
        $customer = \App\Models\Customer::where('_id', '=', $customer_id)->first();
        $customerartist = \App\Models\Customerartist::where('customer_id', '=', $customer_id)->where('artist_id', '=', $artist_id)->first();

        if ($customerartist != NULL) {
            $customer['coins'] = (isset($customer->coins)) ? intval($customer->coins) : 0;
            $customer['xp'] = (isset($customerartist->xp)) ? intval($customerartist->xp) : 0;
        }

        $badges = [
            ['name' => 'super fan', 'level' => 1, 'icon' => 'https://storage.googleapis.com/arms-razrmedia/badges/super-fan.png', 'status' => true],
            ['name' => 'loyal fan', 'level' => 2, 'icon' => 'https://storage.googleapis.com/arms-razrmedia/badges/loyal-fan.png', 'status' => false],
            ['name' => 'die hard', 'level' => 3, 'icon' => 'https://storage.googleapis.com/arms-razrmedia/badges/die-hard-fan.png', 'status' => false],
            ['name' => 'top fan', 'level' => 4, 'icon' => 'https://storage.googleapis.com/arms-razrmedia/badges/top-fan.png', 'status' => false]
        ];
        $customer['badges'] = $badges;

        return $customer;
    }


    public function getCoinsXp()
    {
        $customer = [];

        $customer_id = $this->jwtauth->customerIdFromToken();
        $artist_id = (request()->header('artistid')) ? trim(request()->header('artistid')) : "";

        $customerObject = \App\Models\Customer::where('_id', $customer_id)->first();
        $customerInfoExists = \App\Models\Customerartist::where('customer_id', '=', $customer_id)->where('artist_id', '=', $artist_id)->first();

        $customer['coins'] = (isset($customerObject->coins)) ? intval($customerObject->coins) : 0;
        $customer['xp'] = (isset($customerInfoExists->xp)) ? intval($customerInfoExists->xp) : 0;

        return $customer;
    }


    public function updateProfile($postdata)
    {
        $error_messages = array();
//        $data = array_except($postdata, ['password_confirmation', 'email', 'password', 'photo']);

        $data = array_except($postdata, ['password_confirmation', 'email', 'password']);
        $customer_id = $this->jwtauth->customerIdFromToken();
        $user = \App\Models\Customer::where('_id', trim($customer_id))->first();

        $user->update($data);

//--------------------------------------Redis Key Update-------------------------------------------------------
        $customerArr = $user->toArray();
        $customerArr['password'] = $user->password;
        $customer_id = $customerArr['_id'];

        $this->redisdb->saveCustomerProfile($customer_id, $customerArr);
//--------------------------------------Redis Key Update-------------------------------------------------------

        return $user;
    }


    public function syncCustomerDeviceInfo($postdata)
    {
        $error_messages = [];
        $customerDeviceinfo = [];
        $data = array_except($postdata, []);
        $customer_id = (isset($postdata['customer_id']) && $postdata['customer_id'] != '') ? trim($postdata['customer_id']) : "";
        $artist_id = (isset($postdata['artist_id']) && $postdata['artist_id'] != '') ? trim($postdata['artist_id']) : "";
        $platform = (isset($postdata['platform']) && $postdata['platform'] != '') ? strtolower(trim($postdata['platform'])) : "";
        $fcm_device_token = (isset($postdata['fcm_id']) && $postdata['fcm_id'] != '') ? trim($postdata['fcm_id']) : "";
        $device_id = (isset($postdata['device_id']) && $postdata['device_id'] != '') ? trim($postdata['device_id']) : "";
        $segment_id = (isset($postdata['segment_id']) && $postdata['segment_id'] != '') ? intval($postdata['segment_id']) : 1;
        if ($segment_id < 0) {
            $segment_id = 1;
        }

        //if customer
        if ($customer_id != '' && $artist_id != '' && $fcm_device_token != '') {

            $deviceinfoData = [
                'customer_id' => $customer_id,
                'artist_id' => $artist_id,
                'platform' => $platform,
                'fcm_device_token' => $fcm_device_token,
                'device_id' => $device_id,
                'segment_id' => $segment_id
            ];

            $customerDeviceinfo = \App\Models\Customerdeviceinfo::where('customer_id', '=', $customer_id)->where('artist_id', '=', $artist_id)->where('platform', '=', $platform)->first();

            if ($customerDeviceinfo) {
                \App\Models\Customerdeviceinfo::where('customer_id', '=', $customer_id)->where('artist_id', '=', $artist_id)->where('platform', '=', $platform)->update($deviceinfoData);
            } else {
                $customerDeviceinfo = new \App\Models\Customerdeviceinfo($deviceinfoData);
                $customerDeviceinfo->save();
            }

            $customerDeviceinfo = \App\Models\Customerdeviceinfo::where('customer_id', '=', $customer_id)->where('artist_id', '=', $artist_id)->where('platform', '=', $platform)->first();
        }

        return $customerDeviceinfo;
    }


    public function coinsDeposit($customer_id, $coins)
    {

        $customerInfoExists = \App\Models\Customer::where('_id', $customer_id)->first();
        $customerArtistObj = [];

        if ($customerInfoExists != NULL) {
            $coins = (isset($customerInfoExists->coins)) ? $customerInfoExists->coins + $coins : $coins;
            $data = ['coins' => intval($coins)];
            $customerObj = $customerInfoExists->update($data);
            $customerArtistObj = $customerInfoExists;
        }

        return $customerArtistObj;
    }


    public function coinsWithdrawal($customer_id, $coins)
    {

        $customerInfoExists = \App\Models\Customer::where('_id', $customer_id)->first();
        $customerArtistObj = [];

        if ($customerInfoExists != NULL) {
            $coins = (isset($customerInfoExists->coins)) ? $customerInfoExists->coins - $coins : 0;
            $data = ['coins' => intval($coins)];
            $customerObj = $customerInfoExists->update($data);
            $customerInfoExists = \App\Models\Customer::where('_id', $customer_id)->first();
        }

        return $customerInfoExists;
    }


    public function xpDeposit($customer_id, $artist_id, $xp)
    {

        $customerInfoExists = \App\Models\Customerartist::where('customer_id', '=', $customer_id)->where('artist_id', '=', $artist_id)->first();

        if ($customerInfoExists != NULL) {
            $xp = (isset($customerInfoExists->xp)) ? $customerInfoExists->xp + $xp : $xp;
            $fan_xp = (isset($customerInfoExists->fan_xp)) ? $customerInfoExists->fan_xp + $xp : $xp;
            $data = ['xp' => intval($xp), 'fan_xp' => intval($fan_xp)];
            $customerObj = $customerInfoExists->update($data);
            $customerArtistObj = $customerInfoExists;
        } else {
            $data = ['customer_id' => $customer_id, 'artist_id' => $artist_id, 'xp' => intval($xp), 'fan_xp' => ($xp)];
            $customerArtistObj = new \App\Models\Customerartist($data);
            $customerArtistObj->save();
        }

        return $customerArtistObj;
    }


    public function xpWithdrawal($customer_id, $artist_id, $xp)
    {


    }


    public function updateCoinsForGiftBk($customerInfoExists, $send_free_gifts, $coins, $freecoins, $giftObj)
    {
        if ($customerInfoExists != NULL) {
            if ($giftObj['type'] == 'paid') {
                $coins = (isset($customerInfoExists->coins)) ? $customerInfoExists->coins - $coins : 0;
                $data = ['coins' => $coins];
                $customerObj = $customerInfoExists->update($data);
                $customerArtistObj = $customerInfoExists;
            } else {
                //$send_free_gifts=$customerInfoExists['send_free_gifts'];
                $data['send_free_gifts'] = array_values($send_free_gifts);
                $customerObj = $customerInfoExists->update($data);
                $customerArtistObj = $customerInfoExists;
            }
        }

        return $customerArtistObj;

    }

    public function updateCoinsForGift($customer_id, $coins, $send_free_gifts)
    {
        //  print_r($send_free_gifts) ;exit;
//        if($customerInfoExists != NULL)
//        {
//            if($giftObj['type']=='paid')
//            {
//                $coins              =   (isset($customerInfoExists->coins)) ? $customerInfoExists->coins - $coins : 0;
//                $data               =   ['coins'    => $coins];
//                $customerObj        =   $customerInfoExists->update($data);
//                $customerArtistObj  =   $customerInfoExists;
//            }else{
//                //$send_free_gifts=$customerInfoExists['send_free_gifts'];
//                $data['send_free_gifts'] =  array_values($send_free_gifts);
//                $customerObj             =   $customerInfoExists->update($data);
//                $customerArtistObj       =   $customerInfoExists;
//            }
//        }

//        return $customerArtistObj;

    }


    public function syncCustomerArtist($postdata)
    {
        $error_messages = [];
        $data = array_except($postdata, []);
        $customer_id = (isset($postdata['customer_id']) && $postdata['customer_id'] != '') ? trim($postdata['customer_id']) : "";
        $artist_id = (isset($postdata['artist_id']) && $postdata['artist_id'] != '') ? trim($postdata['artist_id']) : 1;

        //if customer
        if ($customer_id != '' && $artist_id != '') {

            $Data = ['customer_id' => $customer_id, 'artist_id' => $artist_id, 'xp' => 0, 'fan_xp' => 0];
            $customerartist = \App\Models\Customerartist::where('customer_id', '=', $customer_id)->where('artist_id', '=', $artist_id)->first();

            if (!$customerartist) {
                $customerartist = new \App\Models\Customerartist($Data);
                $customerartist->save();
            }

            //$customerartist = \App\Models\Customerartist::where('customer_id', '=', $customer_id)->where('artist_id', '=', $artist_id)->first();

            return true;
        }

        return false;
    }


    public function index($requestData, $perpage = NULL)
    {
        $results = [];
        $perpage = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
        $name = (isset($requestData['name']) && $requestData['name'] != '') ? $requestData['name'] : '';
        $email = (isset($requestData['email']) && $requestData['email'] != '') ? $requestData['email'] : '';
        $gender = (isset($requestData['gender']) && $requestData['gender'] != '') ? $requestData['gender'] : '';
        $identity = (isset($requestData['identity']) && $requestData['identity'] != '') ? $requestData['identity'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : 'active';
        $created_at = (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end = (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';

        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';

        $appends_array = array(
            'name' => $name,
            'status' => $status,
            'email' => $email,
            'gender' => $gender,
            'identity' => $identity,
            'artist_id' => $artist_id,
            'created_at'=>$created_at,
            'created_at_end'=>$created_at_end
        );

        $query = \App\Models\Customer::orderBy('last_visited', 'desc');

        if ($name != '') {
            $query->where('first_name', 'LIKE', '%' . $name . '%');
            $query->orWhere('last_name', 'LIKE', '%' . $name . '%');
        }

	if ($email != '') {

		if(is_numeric($email))
		{
			$query->where('mobile', 'LIKE', '%' . $email . '%');
		}else
		{
			$query->where('email', 'LIKE', '%' . $email . '%');
		}
        }

        if ($gender != '') {
            $query->where('gender', $gender);
        }

        if ($artist_id != '') {
            $query->where('artists', $artist_id);
        }

        if ($identity != '') {
            $query->where('account_link.' . $identity, 1);
        }

        if ($status != '') {
            $query->where('status', $status);
        }

        if ($created_at != '') {
             $query->where('created_at', '>', mongodb_start_date($created_at));
        }

        if ($created_at_end != '') {

             $query->where('created_at', '<', mongodb_end_date($created_at_end));
        }


        $customers = $query->paginate($perpage);
        $results['customers'] = $customers;
        $results['appends_array'] = $appends_array;
        return $results;
    }


    public function totalCoinsInCustomerWalletAvailable($requestData){

        $name              = (isset($requestData['name']) && $requestData['name'] != '') ? $requestData['name'] : '';
        $email             = (isset($requestData['email']) && $requestData['email'] != '') ? $requestData['email'] : '';
        $gender            = (isset($requestData['gender']) && $requestData['gender'] != '') ? $requestData['gender'] : '';
        $identity          = (isset($requestData['identity']) && $requestData['identity'] != '') ? $requestData['identity'] : '';
        $status            = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : 'active';


        $artist_id          =     (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $not_genuine_cids   =     array_column(\App\Models\Customer::whereIn('email', Config::get('app.test_customers'))->orWhereIn('status', ['inactive', 'banned'])->select('_id')->get()->toArray(), '_id');
        $query              =     \App\Models\Customer::whereNotIn('_id', $not_genuine_cids);

//        if ($name != '') {
//            $query->where('first_name', 'LIKE', '%' . $name . '%');
//            $query->orWhere('last_name', 'LIKE', '%' . $name . '%');
//        }
//
//        if ($email != '') {
//            $query->where('email', 'LIKE', '%' . $email . '%');
//        }
//
//        if ($gender != '') {
//            $query->where('gender', $gender);
//        }
//
//        if ($artist_id != '') {
//            $query->where('artists', $artist_id);
//        }
//
//        if ($identity != '') {
//            $query->where('account_link.' . $identity, 1);
//        }
//
//        if ($status != '') {
//            $query->where('status', $status);
//        }


        $coins              =     $query->sum('coins');
        return intval($coins);
    }


    public function askToArtist($requestData)
    {

        $data = $requestData;
        $artist_id = (request()->header('artistid')) ? trim(request()->header('artistid')) : "";
        $question = (isset($data['question'])) ? trim($data['question']) : '';
        $customer = $this->jwtauth->customerFromToken();
        $customer_id = $customer['_id'];

        //insert
        if ($artist_id != "" && $customer_id != "") {
            $questionData = [
                'artist_id' => $artist_id,
                'customer_id' => $customer_id,
                'question' => $question,
                'status' => 'active'
            ];

            //            var_dump($questionData);exit;

            $questionObj = new \App\Models\Asktoartist($questionData);
            $questionObj->save();
            return $questionObj;
        }
    }


    public function deleteColumn($requestData)
    {
        $customer = $this->jwtauth->customerFromToken();
        //  print_pretty($customer);exit;
        $customer_id = $customer['_id'];
        $column_name = (isset($requestData['column_name'])) ? trim($requestData['column_name']) : '';
        $customerObj = \App\Models\Customer::where('_id', '=', $customer_id)->first();
        if ($column_name != '') {
            $customerObj->unset($column_name);
        }
        return $customerObj;
    }


    public function getQuestionsAskToArtist($requestData)
    {
        $data = $requestData;
        $artist_id = (request()->header('artistid')) ? trim(request()->header('artistid')) : "";
        $perpage = (isset($data['perpage'])) ? intval($data['perpage']) : 15;
        $data = \App\Models\Asktoartist::where('status', '=', 'active')->where('artist_id', $artist_id)->with('customer')->orderBy('_id', 'desc')->paginate($perpage);

        if ($data) {
            $data = $data->toArray();
        }

        $responseArr = [];
        $results = (isset($data['data'])) ? $data['data'] : [];
        foreach ($results as $result) {

            $result['customer'] = (isset($result['customer'])) ? array_only($result['customer'], ['_id', 'first_name', 'last_name', 'picture']) : [];
            $result['human_readable_created_date'] = Carbon\Carbon::parse($result['created_at'])->format('F j\\, Y h:i A');
            $result['date_diff_for_human'] = Carbon\Carbon::parse($result['created_at'])->diffForHumans();

            $result = array_except($result, ['entity', 'customer_id', 'customer_name']);
            array_push($responseArr, $result);
        }


        $responeData = [];
        $responeData['list'] = $responseArr;
        $responeData['paginate_data']['total'] = (isset($data['total'])) ? $data['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($data['current_page'])) ? $data['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($data['last_page'])) ? $data['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($data['from'])) ? $data['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($data['to'])) ? $data['to'] : 0;

        return $responeData;
    }


    public function getHistoryPurchases($requestData)
    {


        $data = $requestData;
        $perpage = ($requestData['perpage'] == NULL) ? \Config::get('app.perpage') : intval($requestData['perpage']);
        $artist_id = (isset($data['artist_id']) && $data['artist_id'] != '') ? trim($data['artist_id']) : "";
        $platform = (isset($data['platform']) && $data['platform'] != '') ? trim($data['platform']) : "";
        $customer = $this->jwtauth->customerFromToken();
        $customer_id = $customer['_id'];

        $customerInfo = \App\Models\Customer::where('_id', '=', $customer_id)->first();

//        $query                      =   \App\Models\Purchase::with('content')->with('gift')->where('artist_id','=', $artist_id)->whereIn('entity', ['contents','gifts'])->where('customer_id', $customer_id);


        $entites = ['contents', 'gifts'];
        $query = \App\Models\Purchase::with(array('artist' => function ($query) {
            $query->select('_id', 'first_name', 'last_name', 'cover');
        }))->whereIn('entity', $entites)->where('customer_id', $customer_id);


        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }


        $purchaseslists = $query->orderBy('_id', 'desc')->paginate($perpage);

        $purchaseslists->getCollection()->transform(function ($purchaseList, $key) use ($artist_id) {

            $purchaseData = $purchaseList;
            $entity_id = $purchaseList['entity_id'];

            if ($purchaseList['entity'] == 'contents') {

                $entityObj = \App\Models\Content::where('_id', $entity_id)->first();
                $content = ($entityObj) ? $entityObj : [];


                if (isset($content['photo'])) {
                    if ($content['photo'] == '' || !array_key_exists("cover", $content['photo'])) {
                        $content['photo'] = ['cover' => '', 'thumb' => ''];
                    }
                }

                $purchaseData['content'] = $content;
            }

            if ($purchaseList['entity'] == 'gifts') {
                $entityObj = \App\Models\Gift::where('_id', $entity_id)->first(['_id', 'photo', 'name', 'coins', 'type', 'free_limit', 'status']);
                $purchaseData['gift'] = ($entityObj) ? $entityObj : [];
            }

            return $purchaseData;
        });


        $data = $purchaseslists->toArray();
        $purchases = (isset($data['data'])) ? $data['data'] : [];

        $purchasesArr = [];
        $responeData = [];

        if (count($purchases) > 0) {

            foreach ($purchases as $purchase) {

                $entity = $purchase['entity'];

                if ($entity == "contents" && isset($purchase['content']) && isset($purchase['content']['_id'])) {
                    array_push($purchasesArr, $purchase);
                }

                if ($entity == "gifts" && isset($purchase['gift']) && isset($purchase['gift']['_id'])) {
                    array_push($purchasesArr, $purchase);
                }
            }
        }


        $responeData['list'] = $purchasesArr;
        $responeData['paginate_data']['total'] = (isset($data['total'])) ? $data['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($data['current_page'])) ? $data['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($data['last_page'])) ? $data['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($data['from'])) ? $data['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($data['to'])) ? $data['to'] : 0;


        return $responeData;

    }

    public function getHistoryPurchasesLists($requestData)
    {
        $data = $requestData;
        $perpage = 10;
        $artist_id = (isset($data['artist_id']) && $data['artist_id'] != '') ? trim($data['artist_id']) : "";
        $platform = (isset($data['platform']) && $data['platform'] != '') ? trim($data['platform']) : "";
        $customer_id = (isset($data['customer_id']) && $data['customer_id'] != '') ? trim($data['customer_id']) : "";


        $entites = ['contents', 'gifts', 'stickers'];

        $query = \App\Models\Purchase::with(array('artist' => function ($query) {
            $query->select('_id', 'first_name', 'last_name', 'cover');
        }))->whereIn('entity', $entites)->where('customer_id', $customer_id);

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        $purchaseslists = $query->orderBy('_id', 'desc')->paginate($perpage);
        $purchaseslists->getCollection()->transform(function ($purchaseList, $key) use ($artist_id) {

            $purchaseData = $purchaseList;
            $entity_id = $purchaseList['entity_id'];

            if ($purchaseList['entity'] == 'contents') {

                $entityObj = \App\Models\Content::where('_id', $entity_id)
                    ->first(['artist_id', 'bucket_id', 'level', 'is_album', 'photo', 'slug', 'type', 'source',
                        'commercial_type', 'coins', 'video', 'audio']);
                $content = ($entityObj) ? $entityObj : null;

                if (isset($content->photo)) {
                    if ($content->photo == '' || !array_key_exists("cover", $content->photo)) {
                        $content->photo = ['cover' => '', 'thumb' => ''];
                    }
                }

                if (isset($content->video)) {
                    if ($content->video == '' || !array_key_exists("cover", $content->video)) {
                        $content->video = ['cover' => '', 'player_type' => '', 'url' => '', 'embed_code' => ''];
                    }
                }

                if (isset($content->audio)) {
                    if ($content->audio == '' || !array_key_exists("cover", $content->audio)) {
                        $content->audio = ['cover' => '', 'url' => '',];
                    }
                }

                $purchaseData['content'] = $content;
            }


            if ($purchaseList['entity'] == 'gifts') {
                $entityObj = \App\Models\Gift::where('_id', $entity_id)->first(['_id', 'photo', 'name', 'coins', 'type']);
                $purchaseData['gift'] = ($entityObj) ? $entityObj : null;
            }

            return $purchaseData;
        });


        $data = $purchaseslists->toArray();

        $purchases = (isset($data['data'])) ? $data['data'] : [];
        $responeData = [];

        $responeData['list'] = $purchases;
        $responeData['paginate_data']['total'] = (isset($data['total'])) ? $data['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($data['current_page'])) ? $data['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($data['last_page'])) ? $data['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($data['from'])) ? $data['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($data['to'])) ? $data['to'] : 0;

        return $responeData;
    }


    public function getHistoryRewards($requestData)
    {
        $perpage = ($requestData['perpage'] == NULL) ? Config::get('app.perpage') : intval($requestData['perpage']);
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $reward_type = (isset($requestData['reward_type']) && $requestData['reward_type'] != '') ? $requestData['reward_type'] : '';
        $customer_id = $requestData['customer_id'];

        $query = \App\Models\Reward::with(array('artist' => function ($query) {
            $query->select('_id', 'first_name', 'last_name', 'cover');
        }))->where('customer_id', '=', $customer_id);

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }


        if ($reward_type != '') {
            $query->where('title', '=', $reward_type);
        }


        $data = $query->orderBy('created_at', 'desc')->paginate($perpage)->toArray();
        $responeData = [];
        $responeData['list'] = (isset($data['data'])) ? $data['data'] : [];
        $responeData['paginate_data']['total'] = (isset($data['total'])) ? $data['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($data['current_page'])) ? $data['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($data['last_page'])) ? $data['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($data['from'])) ? $data['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($data['to'])) ? $data['to'] : 0;

        return $responeData;
    }

    public function getHistoryRewardsLists($requestData)
    {
        $perpage = 10;
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $reward_type = (isset($requestData['reward_type']) && $requestData['reward_type'] != '') ? $requestData['reward_type'] : '';
        $customer_id = $requestData['customer_id'];

        $query = \App\Models\Reward::with(array('artist' => function ($query) {
            $query->select('_id', 'first_name', 'last_name', 'cover');
        }))->where('customer_id', '=', $customer_id);

//        $query = \App\Models\Reward::where('customer_id', '=', $customer_id);

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        if ($reward_type != '') {
            $query->where('title', '=', $reward_type);
        }


        $data = $query->orderBy('created_at', 'desc')->paginate($perpage)->toArray();
        $responeData = [];
        $responeData['list'] = (isset($data['data'])) ? $data['data'] : [];
        $responeData['paginate_data']['total'] = (isset($data['total'])) ? $data['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($data['current_page'])) ? $data['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($data['last_page'])) ? $data['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($data['from'])) ? $data['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($data['to'])) ? $data['to'] : 0;

//        return (isset($data['data'])) ? $data['data'] : [];
        return $responeData;
    }


    public function getCustAutoSearch($customer_name)
    {
        if (strlen($customer_name) >= 4) {
            $customer_result = \App\Models\Customer::select('_id', 'first_name', 'last_name', 'email')->where('first_name', 'LIKE', '%' . $customer_name . '%')->orWhere('last_name', 'LIKE', '%' . $customer_name . '%')->orWhere('email', 'LIKE', '%' . $customer_name . '%')->get()->toArray();
            return $customer_result;
        } else {
            return null;
        }
    }

    public function ordersReport($requestData)
    {
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $created_at = (isset($requestData['created_at']) && $requestData['created_at'] != '') ? $requestData['created_at'] : '';
        $created_at_end = (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? $requestData['created_at_end'] : '';
        $appends_array = [
            'artist_id' => $artist_id,
            'created_at' => $created_at,
            'created_at_end' => $created_at_end,
        ];

        $created_at = mongodb_start_date((isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '');
        $created_at_end = mongodb_end_date((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '');

        if (!empty($artist_id)) {
//            $artists = \App\Models\Cmsuser::where('status', '=', 'active')->where('_id', $artist_id)->get(['first_name', 'last_name', '_id'])->toArray();
            $artist_ids = array($artist_id);
        } else {
            $artist_role_ids = \App\Models\Role::where('slug', 'artist')->lists('_id');
            $artist_role_ids = ($artist_role_ids) ? $artist_role_ids->toArray() : [];
            $artists = \App\Models\Cmsuser::where('status', '=', 'active')->whereIn('roles', $artist_role_ids)->get(['first_name', 'last_name', '_id'])->toArray();
            $artist_ids = array_pluck($artists, '_id');
        }

        $not_genuine_cids = not_genuine_cids();

        $orderwise_customers = \App\Models\Order::raw(function ($collection) use ($artist_ids, $not_genuine_cids, $created_at, $created_at_end) {

            $aggregate = [
                [
                    '$match' => [
                        'artist_id' => ['$in' => $artist_ids],
                        'customer_id' => ['$nin' => $not_genuine_cids],
                        'order_status' => ['$in' => ['successful']],
                        '$and' => [
                            ["created_at" => ['$gte' => $created_at]],
                            ["created_at" => ['$lte' => $created_at_end]]
                        ],

                    ]
                ],
                [
                    '$group' => [
                        '_id' => ['customer_id' => '$customer_id'],
                        'total_orders' => ['$sum' => 1],
                        'artist_id' => ['$last' => '$artist_id'],
                        "total_coins" => ['$sum' => '$package_coins'],
                        "total_price" => ['$sum' => '$package_price'],
                    ]
                ],
                [
                    '$project' => [
                        '_id' => '$_id.customer_id',
                        'artist_id' => '$artist_id',
                        'customer_id' => '$_id.customer_id',
                        'total_orders' => '$total_orders',
                        'total_coins' => '$total_coins',
                        'total_price' => '$total_price',
                    ]
                ],
                ['$sort' => ['total_price' => -1]],
                ['$limit' => 50]
            ];
            return $collection->aggregate($aggregate);
        });
        $response = [];
        $topCustomerOrderWise = [];
        $orderwise_customers = $orderwise_customers->toArray();

        foreach ($orderwise_customers as $customer_info) {
//            $artist_id = $customer_info['artist_id'];
//            $artist = head(array_where($artists, function ($key, $value) use ($artist_id) {
//                if ($value['_id'] == $artist_id) {
//                    return $value;
//                }
//            }));
//            $artist_name = $artist['first_name'] . ' ' . $artist['last_name'];

            $customer = \App\Models\Customer::where('_id', $customer_info['customer_id'])->first(['first_name', 'last_name', 'email', 'picture', 'mobile', 'status']);
            $cust_info = [
                'first_name' => ucwords($customer['first_name']),
                'last_name' => ucwords($customer['last_name']),
                'email' => ($customer['email']),
                'picture' => ($customer['picture']),
                'mobile' => ($customer['mobile']),
                'status' => ucwords($customer['status']),
            ];

//            $result = array_merge($customer_info, $cust_info,['artist_name' => ucwords($artist_name)]);

            $result = array_merge($customer_info, $cust_info);
            array_push($topCustomerOrderWise, $result);
        }
        $response['topCustomerOrderWise'] = $topCustomerOrderWise;
        $response['appends_array'] = $appends_array;


        return $response;
    }

    public function customerAddCoins($requestData)
    {
        $error_messages = array();

        $customer_id                    =   $requestData['customer_id'];
        $refund_coins                   =   intval($requestData['refund_coins']);

        $customerObj                    =   \App\Models\Customer::where('_id', $customer_id)->first();
        $coins_before_txn               =   (isset($customerObj->coins)) ? $customerObj->coins : 0;
        $coins_after_txn                =   (isset($customerObj->coins)) ? $customerObj->coins + $refund_coins : $refund_coins;

        $requestData['refund_coins']        = $refund_coins;
        $requestData['coins_before_txn']    = $coins_before_txn;
        $requestData['coins_after_txn']     = $coins_after_txn;
        $requestData['passbook_applied']    = true;

        $user_add_coins = new \App\Models\Refundcoins($requestData);
        $user_add_coins->save();

        $this->redisdb->saveCustomerCoins($customer_id, intval($coins_after_txn));

        $customerObj->update(['coins' => intval($coins_after_txn)]);

        return $user_add_coins;
    }

    public function fetchCoinsReports($requestData, $perpage = NULL)
    {
        $results = [];
        $perpage = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);

        $email = (isset($requestData['email']) && $requestData['email'] != '') ? $requestData['email'] : '';
        $customer_id = !empty($email) ? \App\Models\Customer::where('email', $email)->first(['_id'])->toArray()['_id'] : '';

        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';

        $created_at = (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end = (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';

        $appends_array = array(
            'email' => $email,
            'artist_id' => $artist_id,
            'created_at' => $created_at,
            'created_at_end' => $created_at_end,
        );

        if (!empty($requestData['customer_id'])) {
            $customer_id = $requestData['customer_id'];
        }

        $query = \App\Models\Refundcoins::with([
            'artist' => function ($a) {
                $a->select('first_name', 'last_name');
            },
            'cmsuser' => function ($c) {
                $c->select('first_name', 'last_name');
            },
            'customer' => function ($cust) use ($email) {
                $cust->select('first_name', 'last_name', 'email');

                if (!empty($email)) {
                    $cust->where('email', $email);
                }
            },
        ])->orderBy('created_at', 'desc');

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        if ($customer_id != '') {
            $query->where('customer_id', $customer_id);
        }

        if ($created_at != '') {
            $query->where('created_at', '>', mongodb_start_date($created_at));
        }

        if ($created_at_end != '') {
            $query->where('created_at', '<', mongodb_end_date($created_at_end));
        }

        $coins_logs = $query->paginate($perpage);
        $results['coins_logs'] = $coins_logs;
        $results['appends_array'] = $appends_array;
        return $results;

    }


    public function getArtistWiseContestantStats($requestData)
    {
        $ret = 0;
        $ret = \App\Models\Cmsuser::where('status', '=', 'active')->whereIn('is_contestant', ['true'])->count();
        return $ret;

    }

}
