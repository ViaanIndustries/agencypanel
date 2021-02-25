<?php

namespace App\Services;

use App\Models\Customer as Customer;
use App\Models\Customerdeviceinfo;
use App\Repositories\Contracts\CustomerActivityInterface;
use App\Repositories\Contracts\CustomerDeviceInterface;
use App\Repositories\Contracts\CustomerInterface;
use App\Repositories\Contracts\RewardInterface;
use App\Services\Cache\AwsElasticCacheRedis;
use App\Services\Mailers\CustomerMailer;
use App\Services\Notifications\CustomerNotification;
use App\Services\Notifications\PushNotification;
use Cache;
use Carbon\Carbon;
use Config;
use Hash;
use Input;
use Log;
use Redirect;
use Session;
use App\Services\Image\Kraken;

class CustomerService
{
    protected $jwtauth;
    protected $customerRep;
    protected $devicerepObj;
    protected $customer;
    protected $gcp;
    protected $activity;
    protected $pushnotification;
    protected $customermailer;
    protected $rewardRep;
    protected $redisDb;
    protected $kraken;
    protected $customernotification;
    protected $caching;
    protected $awscloudfrontService;
    protected $awsElasticCacheRedis;


    public function __construct(
        Jwtauth $jwtauth,
        Customer $customer,
        Customerdeviceinfo $customerdevice,
        CustomerInterface $customerRep,
        CustomerDeviceInterface $devicerepObj,
        Gcp $gcp,
        PushNotification $pushnotification,
        CustomerMailer $customermailer,
        CustomerActivityInterface $activity,
        RewardInterface $rewardRep,
        RedisDb $redisDb,
        Kraken $kraken,
        CustomerNotification $customerNotification,
        AwsCloudfront $awscloudfrontService,
        CachingService $caching,
        AwsElasticCacheRedis $awsElasticCacheRedis
    )
    {
        $this->jwtauth = $jwtauth;
        $this->customer = $customer;
        $this->customerdevice = $customerdevice;
        $this->customerRep = $customerRep;
        $this->devicerepObj = $devicerepObj;
        $this->gcp = $gcp;
        $this->pushnotification = $pushnotification;
        $this->customermailer = $customermailer;
        $this->activity = $activity;
        $this->rewardRep = $rewardRep;
        $this->redisDb = $redisDb;
        $this->kraken = $kraken;
        $this->customernotification = $customerNotification;
        $this->caching = $caching;
        $this->awscloudfrontService = $awscloudfrontService;
        $this->awsElasticCacheRedis = $awsElasticCacheRedis;
    }


    public function index($request)
    {
        $requestData = $request->all();
        $results = $this->customerRep->index($requestData);
        return $results;
    }

    public function totalCoinsInCustomerWalletAvailable($request)
    {
        $requestData = $request->all();
        $conis = $this->customerRep->totalCoinsInCustomerWalletAvailable($requestData);
        return $conis;
    }

    public function find($id)
    {
        $error_messages = $results = [];
        if (empty($error_messages)) {
            $results = $this->customerRep->find($id);
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function paginate()
    {
        $error_messages = $results = [];
        $results = $this->customerRep->paginateForApi();

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function deleteColumn($request)
    {
        $error_messages = $results = [];
        $results = $this->customerRep->deleteColumn($request);

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function activeLists()
    {
        $error_messages = $results = [];
        $results = $this->customerRep->activelistswithslug();

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function customerLists()
    {
        $error_messages = $results = [];
        $results = $this->customerRep->customerLists();

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function show($id)
    {
        $error_messages = $results = [];
        if (empty($error_messages)) {
            $results['customer'] = $this->customerRep->find($id);
            $results['customerdevices'] = $this->devicerepObj->customerDevices($id);
            //  $results['customeractivities']      =   $this->activity->customerActivities($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function customerActivities($request, $id)
    {
        $error_messages = $results = [];
        $data = $request->all();
        if (empty($error_messages)) {
            $results = $this->activity->customerActivities($data, $id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function customerActivitiesDemo($request, $id)
    {
        $error_messages = $results = [];
        $data = $request->all();
        if (empty($error_messages)) {
            $results = $this->activity->customerActivitiesDemo($data, $id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function getArtistInfo($request, $id)
    {
        $error_messages = $results = [];
        $data = $request->all();

        if (empty($error_messages)) {
            $results = $this->customerRep->getArtistInfo($data, $id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function store($request)
    {
        $data = $request->all();
        $error_messages = $results = [];

        if (empty($error_messages)) {
            $data['identity'] = 'email';
            $results['customer'] = $this->customerRep->store($data);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function subscribeUserToTopic()
    {

        $artist_role_ids = \App\Models\Role::where('slug', 'artist')->lists('_id');
        $artist_role_ids = ($artist_role_ids) ? $artist_role_ids->toArray() : [];
        $artists = $this->model->whereIn('roles', $artist_role_ids)->with('artistconfig')->orderBy('id')->paginate($perpage);

        $device_token_lax = 'cIDQW0QsjoM:APA91bG1y4Nwo6p25_1u2e5Vpg0s-jA_17Jrtnu1LA62dOgAp_qG_Otbt6u4EsuchBQ5G2tmUdpPlW5ezeHOndnS-UoYEimSdeZqzzxWNniGIOzTa4fA4hQSDpVfw8wVlbwhbLxVBCyQ';
        $device_token_san = 'f1T0c2uCew4:APA91bGoZY7GhgEhwbgvs_UvAZesW_RS7eDj38oEsDwGF8qOkIj2S-NSOC-xmGmPMIIEtTghSfmSlYmNgTkJNdfrp8WE1_S2zuasju8L80-5u0ihIJKWBWRTImWqzOyNt3C4FuKO6G7G';

        $topic_id = 'zareenkhan';
        $params = [
            'device_token' => $device_token_lax,
            'topic_id' => $topic_id
        ];

        $response = $this->pushnotification->subscribeUserToTopic($params);
//        $responseBody       =   json_decode($response, true);
        return $response;

    }

    public function update($request, $id)
    {
        $data = $request->all();
        $error_messages = $results = [];

        if ($request->hasFile('picture')) {
//            $upload = $request->file('picture');
//            $folder_path = 'uploads/customers/picture/';
//            $img_path = public_path($folder_path);
//            $imageName = time() . '_' . str_slug($upload->getRealPath()) . '.' . $upload->getClientOriginalExtension();
//            $fullpath = $img_path . $imageName;
//            $upload->move($img_path, $imageName);
//            chmod($fullpath, 0777);
//            $customer_id = $this->jwtauth->customerIdFromToken();
//            $object_source_path = $fullpath;
//            $object_upload_path = "customers/" . $customer_id . '/profile/' . $imageName;
//            $params = ['object_source_path' => $object_source_path, 'object_upload_path' => $object_upload_path];
//            $uploadToGcp = $this->gcp->localFileUpload($params);
//            $cover_url = Config::get('gcp.base_url') . Config::get('gcp.default_bucket_path') . $object_upload_path;
//            array_set($data, 'picture', $cover_url);
//
//            @unlink($fullpath);

//------------------------------------Kraken Image Compression--------------------------------------------

            $parmas = ['file' => $request->file('picture'), 'type' => 'customerprofile'];

            $photo  =   $this->kraken->uploadToAws($parmas);
            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                array_set($data, 'photo', $photo['results']);
                array_set($data, 'picture', $photo['results']['cover']);
            }

//------------------------------------Kraken Image Compression--------------------------------------------

        }

        if (empty($error_messages)) {
            $results['customer'] = $this->customerRep->update($data, $id);
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function destroy($id)
    {
        $results = $this->customerRep->destroy($id);
        return $results;
    }

    public function register($request)
    {
        $data = $request->all();
        $error_messages = $results = [];
        $status_code = 201;

        if (empty($data['first_name']) && empty($data['last_name'])) {
            $data['first_name'] = explode("@", $data['email'])[0];
        }

        $email = strtolower(trim($data['email']));
        $customer = \App\Models\Customer::where('email', '=', $email)->first();

        if ($customer) {
//            $error_messages[] = 'This email has already been registered';
            $error_messages[] = 'Customer already register';
            $status_code = 202;
        }

        $results['status_code'] = $status_code;
        if (empty($error_messages)) {
            $data['coins'] = 0;
//            $data['badges'] = [];
            $customer = $this->customerRep->register($data);
            $results['customer'] = $customer;
            $results['token'] = $this->jwtauth->createLoginToken($customer);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function login($request)
    {
        $error_messages = $results = [];
        $data = array_except($request->all(), []);

        $identity = trim($data['identity']);
        $email = strtolower(trim($data['email']));
        $customer = \App\Models\Customer::where('email', '=', $email)->first();

        $data['picture'] = "https://d1bng4dn08r9r5.cloudfront.net/default/customersprofile/default.png";

        //email,google,facebook,twitter

        if (empty($customer) && $identity != 'email') {
            $customer = $this->customerRep->register($data);
            $customer = \App\Models\Customer::where('email', '=', $email)->first();
        }

        if (empty($customer)) {
            $error_messages[] = 'Customer does not exists';
        }

        if (!empty($customer) && isset($customer['status']) && $customer['status'] != 'active') {
            $error_messages[] = 'Customer account is suspended';
        }

        $artist_id = trim(request()->header('artistid'));
        if (isset($artist_id) && empty($artist_id)) {
            $error_messages[] = 'ArtistId required in header';
        }


        if (!empty($customer) && isset($data['password']) && $data['password'] != '' && $identity == 'email') {
            if (!Hash::check(trim($data['password']), $customer['password'])) {
                $error_messages[] = 'Invalid credentials, please try again';
            }
        }

        $results = ['error_messages' => $error_messages];

        if (empty($error_messages)) {

            $platform = (request()->header('platform')) ? trim(request()->header('platform')) : "";
            $artist = (request()->header('artistid')) ? trim(request()->header('artistid')) : "";

            $customer->last_visited = Carbon::now();

//            if (isset($data['identity']) && $data['identity'] == 'facebook' && isset($data['facebook_id']) && $data['facebook_id'] != "") {
//                $customer->picture = 'https://graph.facebook.com/' . $data['facebook_id'] . '/picture?type=large';
//            }
//
//            if (isset($data['identity']) && $data['identity'] == 'google' && isset($data['profile_pic_url']) && $data['profile_pic_url'] != '') {
//                $customer->picture = trim($data['profile_pic_url']);
//            }

            if (isset($data['first_name'])) {
                $customer->first_name = trim($data['first_name']);
            }

            if (isset($data['last_name'])) {
                $customer->last_name = trim($data['last_name']);
            }

            if (isset($data['gender'])) {
                $customer->gender = strtolower(trim($data['gender']));
            }

            $customer->update();

            if ($platform != '') {
                $customer->push('platforms', trim(strtolower($platform)), true);
            }
            if ($artist != '') {
                $customer->push('artists', trim(strtolower($artist)), true);
            }

            $customer_id = $customer->_id;
            $artist_id = request()->header('artistid');
            $customerMetaInfo = $data;
            $customerMetaInfo['customer_id'] = $customer_id;
            $customerMetaInfo['artist_id'] = $artist_id;

            if ($customer_id != '' && $artist_id != '') {
                // Sync Customer Artist Relationship
                $this->syncCustomerArtist($customerMetaInfo);

                // Update Customer Device Info
                $this->syncCustomerDeviceInfo($customerMetaInfo);
            }

            if ($data['fcm_id'] && $data['fcm_id'] != '') {
                //Subscribe To Artist Topic
                $this->subscribeCustomerToArtistTopic($customerMetaInfo);
            }

            // Give 200 conis on first login
            $customerReward = $this->giveRewardForFirstLogin($customerMetaInfo, 'reward_on_first_login');
            $customer = \App\Models\Customer::where('_id', '=', $customer_id)->first();
            $customer = array_except($customer, ['badges']);

            $results['customer'] = $customer;
            $results['token'] = $this->jwtauth->createLoginToken($customer);
            $results['reward'] = $customerReward;

            //For Badges
            $badges = [
                ['name' => 'super fan', 'level' => 1, 'icon' => 'https://d1bng4dn08r9r5.cloudfront.net/default/badges/super-fan.png', 'status' => true],
                ['name' => 'loyal fan', 'level' => 2, 'icon' => 'https://d1bng4dn08r9r5.cloudfront.net/default/badges/loyal-fan.png', 'status' => false],
                ['name' => 'die hard', 'level' => 3, 'icon' => 'https://d1bng4dn08r9r5.cloudfront.net/default/badges/die-hard-fan.png', 'status' => false],
                ['name' => 'top fan', 'level' => 4, 'icon' => 'https://d1bng4dn08r9r5.cloudfront.net/default/badges/top-fan.png', 'status' => false]
            ];
            $results['customer']['badges'] = $badges;

            // For App Local Db
            $customerInfoLocalDbData = $this->getCustomerInfoForClientLevelDbData($customer_id);
//            $customerInfoLocalDbData = $this->getCustomerInfoForClientLevelDbData($customer_id);
            $results = array_merge(apply_cloudfront_url($results), $customerInfoLocalDbData);

        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function getCustomerInfoForClientLevelDbData($customer_id)
    {

        $customerData = [];
        $artist_id = (request()->header('artistid')) ? trim(request()->header('artistid')) : "";
        $platform = (request()->header('platform')) ? trim(request()->header('platform')) : "";

        if ($customer_id != '' && $artist_id != '') {
            $like_content_ids = \App\Models\Like::where('customer_id', '=', $customer_id)
                ->where('entity', 'content')
                ->where("status", "active")
                ->where('artist_id', $artist_id)
                ->lists('entity_id')
                ->toArray();
            $purchase_content_ids = \App\Models\Purchase::where('customer_id', '=', $customer_id)
                ->where('entity', 'contents')
                ->where('artist_id', $artist_id)
                ->lists('entity_id')
                ->toArray();
        }

        $customerData['like_content_ids'] = ($like_content_ids) ? $like_content_ids : [];
        $customerData['purchase_content_ids'] = ($purchase_content_ids) ? $purchase_content_ids : [];

        return $customerData;

    }

    public function giveRewardForFirstLogin($postdata, $reward_title)
    {
        $artist_id = $postdata['artist_id'];
        $artist = \App\Models\Cmsuser::where('_id', '=', $artist_id)->first();
        $customer_id = $postdata['customer_id'];
//        $saveData = [
//            'customer_id' => $customer_id,
//            'artist_id' => $artist_id,
//            'reward_title' => 'install',
//            'title' => $reward_title,
//            'description' => "You've won 50 coins for installing " . $artist->fullname . " app",
//            'reward_type' => 'coins',
//            'coins' => 50
//        ];

//        $rewardObj = $this->rewardRep->saveOneTimeRewardForCustomer($saveData, $reward_title);
//
//        if ($rewardObj && isset($rewardObj['coins']) && intval($rewardObj['coins']) > 0) {
//            $reward = $saveData;
//        } else {
//            $reward = $saveData;
//            $reward['coins'] = 0;
//        }

        $reward = $saveData = [
            'customer_id' => $customer_id,
            'artist_id' => $artist_id,
            'reward_title' => 'install',
            'title' => $reward_title,
            'description' => "You've won 0 coins for installing " . $artist->fullname . " app",
            'reward_type' => 'coins',
            'coins' => 0
        ];
        return array_except($reward, ['customer_id', 'artist_id']);
    }

    public function syncCustomerArtist($postdata)
    {
        try {
            $this->customerRep->syncCustomerArtist($postdata);
        } catch (Exception $e) {
            $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            Log::info('syncCustomerArtist : Fail ', $message);
        }
    }

    public function syncCustomerDeviceInfo($postdata)
    {
        try {
            $this->customerRep->syncCustomerDeviceInfo($postdata);
        } catch (Exception $e) {
            $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            Log::info('syncCustomerDeviceInfo : Fail ', $message);
        }
    }

    public function subscribeCustomerToArtistTopic($postdata)
    {

        try {

            $artist_id = (isset($postdata['artist_id']) && $postdata['artist_id'] != '') ? trim($postdata['artist_id']) : 1;
            $fcm_device_token = (isset($postdata['fcm_id']) && $postdata['fcm_id'] != '') ? trim($postdata['fcm_id']) : "";
            $topic_id = (isset($postdata['topic_id']) && $postdata['topic_id'] != '') ? trim($postdata['topic_id']) : "";

            if (!empty($topic_id)) {
                $params = [
                    'artist_id' => $artist_id,
                    'device_token' => $fcm_device_token,
                    'topic_id' => $topic_id
                ];
            } else {
                $artistconfig = \App\Models\Artistconfig::where('artist_id', '=', $artist_id)->first();

                if ($artistconfig) {

                    $test_env = (env('APP_ENV', 'stg') == 'production') ? "false" : "true";
                    $test_topic_id = (isset($artistconfig->fmc_default_topic_id_test) && $artistconfig->fmc_default_topic_id_test != '') ? $artistconfig->fmc_default_topic_id_test : "";
                    $production_topic_id = (isset($artistconfig->fmc_default_topic_id) && $artistconfig->fmc_default_topic_id != '') ? $artistconfig->fmc_default_topic_id : "";
                    $topic_id = ($test_env == 'true') ? $test_topic_id : $production_topic_id;

                    $params = [
                        'artist_id' => $artist_id,
                        'device_token' => $fcm_device_token,
                        'topic_id' => $topic_id
                    ];
                }
            }


            if ($fcm_device_token != "" && $topic_id != "") {
                $response = $this->pushnotification->subscribeUserToTopic($params);
            }

        } catch (Exception $e) {

            $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            Log::info('subscribeCustomerToArtistTopic : Fail ', $message);

        }
    }

    public function profile($request)
    {

        $error_messages = $results = [];

        if (empty($error_messages)) {
//            $results['customer'] = $this->customerRep->profile($request);
//            $customer_id = $this->jwtauth->customerIdFromToken();
//            $customerInfoLocalDbData = $this->getCustomerInfoForClientLevelDbData($customer_id);
//            $results = array_merge($results, $customerInfoLocalDbData);

            $customer_id                    =   $this->jwtauth->customerIdFromToken();

            $cacheParams                    =   [];
            $hash_name                      =   env_cache(Config::get('cache.hash_keys.customer_profile').$customer_id);
            $hash_field                     =   $customer_id;
            $cache_miss                     =   false;

            $cacheParams['hash_name']       =   $hash_name;
            $cacheParams['hash_field']      =   (string) $hash_field;

            $results                        =   $responses = $this->awsElasticCacheRedis->getHashData($cacheParams);
            if(empty($results)){
                $customerArr['customer']            =   $this->customerRep->profile($request);
                $customerInfoLocalDbData            =   $this->getCustomerInfoForClientLevelDbData($customer_id);
                $customerArr                        =   array_merge($customerArr, $customerInfoLocalDbData);
                $items                              =   ($customerArr) ? apply_cloudfront_url($customerArr) : [];
                $cacheParams['hash_field_value']    =   $items;
                $saveToCache                        =   $this->awsElasticCacheRedis->saveHashData($cacheParams);
                $cache_miss                         =   true;
                $results                            =   $this->awsElasticCacheRedis->getHashData($cacheParams);
            }

            $results['cache']               =   ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function getCoinsXp($request)
    {
        $error_messages = $results = [];

        $results = $this->customerRep->getCoinsXp();
        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function updateDeviceInfo($request)
    {
        $data = array_only($request->all(), ['fcm_id', 'device_id', 'platform']);
        $error_messages = $results = [];

        $artist_id = request()->header('artistid');
//        $customer = (request()->header('Authorization')) ? $this->jwtauth->customerFromToken() : [];
        $customer_id = (request()->header('Authorization')) ? $this->jwtauth->customerIdFromToken() : "";
//        $logout = false;


        $customerdeviceinfo_arr = Array(
            'customer_id' => $customer_id,
            'artist_id' => $artist_id,
            'platform' => $data['platform'],
            'fcm_id' => $data['fcm_id'],
            'fcm_device_token' => (isset($data['fcm_id']) && $data['fcm_id'] != '') ? trim($data['fcm_id']) : "",
            'device_id' => (isset($data['device_id']) && $data['device_id'] != '') ? trim($data['device_id']) : "",
            'last_visited' => Carbon::now(),
            'topic_id' => !empty($data['topic_id']) ? $data['topic_id'] : ''
        );

        $segment_id = (isset($data['segment_id']) && $data['segment_id'] != '') ? intval($data['segment_id']) : 1;

        if ($segment_id < 0) {
            $segment_id = 1;
        }

        array_set($customerdeviceinfo_arr, 'segment_id', $segment_id);

        $this->redisDb->updateDeviceInfos($customerdeviceinfo_arr);


        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function updateProfile($request)
    {
        $data = array_except($request->all(), ['photo']);
        $error_messages = $results = [];

//        $category_count = $this->customerRep->checkUniqueOnUpdate($id, 'email', $data['email']);
//        if ($category_count > 0) {
//            $error_messages[] = 'User with email id already exist : ' . trim($data['email']);
//        }

        if ($request->hasFile('photo')) {

//------------------------------------Kraken Image Compression--------------------------------------------
            $parmas = ['file' => $request->file('photo'), 'type' => 'customerprofile'];
            $photo  =   $this->kraken->uploadToAws($parmas);
            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                array_set($data, 'photo', $photo['results']);
                array_set($data, 'picture', $photo['results']['cover']);
            }
//------------------------------------Kraken Image Compression--------------------------------------------
        }

        if (empty($error_messages)) {
            $results['customer'] = $this->customerRep->updateProfile($data);
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function askToArtist($request)
    {

        $data = array_except($request->all(), []);
        $error_messages = $results = [];

        if (empty($error_messages)) {
            $results['ask_to_artist'] = $this->customerRep->askToArtist($data);
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function getQuestionsAskToArtist($request)
    {
        $requestData = $request->all();
        $error_messages = $results = [];

        if (empty($error_messages)) {
            $results['questions'] = $this->customerRep->getQuestionsAskToArtist($requestData);
        }

        return ['error_messages' => $error_messages, 'results' => $results];

    }

    public function getHistoryPurchases($request)
    {
        $error_messages = $results = [];
        $customer_id = $this->jwtauth->customerIdFromToken();
        $request['customer_id'] = $customer_id;
        $page = (isset($request['page']) && $request['page'] != '') ? trim($request['page']) : '1';

        if (empty($error_messages)) {
            $results = $this->customerRep->getHistoryPurchases($request);

        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function getHistoryPurchasesLists($request)
    {

        $error_messages = $results = [];

        $customer_id = $this->jwtauth->customerIdFromToken();
        $request['customer_id'] = $customer_id;

        $page = (isset($request['page']) && $request['page'] != '') ? trim($request['page']) : '1';
        $artist_id = (isset($request['artist_id']) && $request['artist_id'] != '') ? trim($request['artist_id']) : '';


        /*

        //OLD


        $cachetag_name = $request['customer_id'] . "_customerspendings";
        $env_cachetag = env_cache_tag_key($cachetag_name);              //  ENV_customerpurchases
//        $cachetag_key = $page.'_'.$request['customer_id'];          //  pageno_customerid
        $cachetag_key = !empty($artist_id) ? $artist_id . '_' . $page : 'all' . '_' . $page;          //  artistid_pagno

        $cache_time = Config::get('cache.cache_time');

        $buckets = Cache::tags($env_cachetag)->has($cachetag_key);

        if (!$buckets) {
            $responses = $this->customerRep->getHistoryPurchasesLists($request);
            $items = ($responses) ? $responses : [];
            $items = apply_cloudfront_url($items);
            Cache::tags($env_cachetag)->put($cachetag_key, $items, $cache_time);
        }
        $results = Cache::tags($env_cachetag)->get($cachetag_key);
        $results['cache'] = ['tags' => $env_cachetag, 'key' => $cachetag_key];

        */

        $cacheParams = [];
        $hash_name      =   env_cache(Config::get('cache.hash_keys.customer_spendings_lists').$customer_id);
        $hash_field     =   $page;
        $cache_miss     =   false;

        $cacheParams['hash_name']   =  $hash_name;
        $cacheParams['hash_field']  =  (string)$hash_field;


        $results = $responses = $this->awsElasticCacheRedis->getHashData($cacheParams);
        if (empty($results)) {
            $responses = $this->customerRep->getHistoryPurchasesLists($request);
            $items = ($responses) ? apply_cloudfront_url($responses) : [];
            $cacheParams['hash_field_value'] = $items;
            $saveToCache = $this->awsElasticCacheRedis->saveHashData($cacheParams);
            $cache_miss = true;
            $results  = $this->awsElasticCacheRedis->getHashData($cacheParams);
        }

        $results['cache']    = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function getHistoryRewards($request)
    {

        $error_messages = $results = [];
        $customer_id = $this->jwtauth->customerIdFromToken();
        $request['customer_id'] = $customer_id;

        if (empty($error_messages)) {
            $results = $this->customerRep->getHistoryRewards($request);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function getHistoryRewardsLists($request)
    {
        $error_messages = $results = [];
        $customer_id = $this->jwtauth->customerIdFromToken();
        $request['customer_id'] = $customer_id;
        $page = (isset($request['page']) && $request['page'] != '') ? trim($request['page']) : '1';
        $artist_id = (isset($request['artist_id']) && $request['artist_id'] != '') ? trim($request['artist_id']) : '';

        $cacheParams = [];
        $hash_name      =   env_cache(Config::get('cache.hash_keys.customer_rewards_lists').$customer_id);
        $hash_field     =   $page;
        $cache_miss     =   false;

        $cacheParams['hash_name']   =  $hash_name;
        $cacheParams['hash_field']  =  (string)$hash_field;


        $results = $responses = $this->awsElasticCacheRedis->getHashData($cacheParams);
        if (empty($results)) {
            $responses = $this->customerRep->getHistoryRewardsLists($request);
            $items = ($responses) ? apply_cloudfront_url($responses) : [];
            $cacheParams['hash_field_value'] = $items;
            $saveToCache = $this->awsElasticCacheRedis->saveHashData($cacheParams);
            $cache_miss = true;
            $results  = $this->awsElasticCacheRedis->getHashData($cacheParams);
        }

        $results['cache']    = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function getCustAutoSearch($request)
    {
        $error_messages = $results = [];
        $results = $this->customerRep->getCustAutoSearch($request);
        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function ordersReport($request)
    {
        $request = $request->all();
        $error_messages = $results = [];
        $results = $this->customerRep->ordersReport($request);
        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function forgetPassword($requestData)
    {
        $email = trim(strtolower($requestData['email']));
        $artist_id = $requestData['artist_id'];
        $error_messages =   [];
        $results = '';
        $error = false;

        $customer = \App\Models\Customer::where('email', $email)->where('status', 'active')->first();

        $artist = \App\Models\Cmsuser::where('_id', $artist_id)->where('status', 'active')->first(['first_name', 'last_name', 'photo']);
        $celeb_name = @$artist['first_name'] . " " . @$artist['last_name'];
        $celeb_photo = @$artist['photo'];

        if (!empty($customer)) {
            $digits = 6;
            $temp_password = random_numbers($digits);

            $data['password'] = $temp_password;
            $customer->update($data);

            $celeb_direct_app_download_link = '';
            $celeb_ios_app_download_link = '';
            $celeb_android_app_download_link = '';

            if (!empty($artist_id)) {
                $artist_config_info = \App\Models\Artistconfig::where('artist_id', $artist_id)->first();
                $celeb_android_app_download_link = ($artist_config_info && !empty($artist_config_info['android_app_download_link'])) ? trim($artist_config_info['android_app_download_link']) : '';
                $celeb_ios_app_download_link = ($artist_config_info && !empty($artist_config_info['ios_app_download_link'])) ? trim($artist_config_info['ios_app_download_link']) : '';
                $celeb_direct_app_download_link = ($artist_config_info && !empty($artist_config_info['direct_app_download_link'])) ? trim($artist_config_info['direct_app_download_link']) : '';
            }

//---------------------------------------Email-----------------------------------------------------------------
            $details_for_send_email['email'] = $email;
            $details_for_send_email['name'] = !empty($customer['first_name']) ? $customer['first_name'] : explode("@", $data['email'])[0];
            $details_for_send_email['password'] = $temp_password;
            $details_for_send_email['celeb_name'] = $celeb_name;
            $details_for_send_email['celeb_photo'] = $celeb_photo;
            $details_for_send_email['celeb_android_app_download_link'] = $celeb_android_app_download_link;
            $details_for_send_email['celeb_ios_app_download_link'] = $celeb_ios_app_download_link;
            $details_for_send_email['celeb_direct_app_download_link'] = $celeb_direct_app_download_link;

            $send_mail = $this->customermailer->forgotPassword($details_for_send_email);
//---------------------------------------Email-----------------------------------------------------------------

//--------------------------------------Redis Key Flush-------------------------------------------------------
            $customer_profile_key = Config::get('cache.keys.customerprofile') . $email;
            $env_customer_profile_key = env_cache_key($customer_profile_key);

            $redisClient = $this->redisDb->PredisConnection();

            $redisClient->del([$env_customer_profile_key]);
//--------------------------------------Redis Key Flush-------------------------------------------------------

            $results = 'We have sent password reset instructions to your email address.';

        } else {
            $results = 'Email is not available';

        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function customerChangePassword($requestData)
    {
        $email = $requestData['email'];
        $old_password = $requestData['old_password'];
        $new_password = $requestData['new_password'];
        $results = '';

        $customer = \App\Models\Customer::where('email', $email)
            ->where('status', 'active')
            ->first();

        if (!empty($customer)) {

            if (Hash::check(trim($old_password), $customer['password'])) {

                if ($old_password != $new_password) {

                    $data['password'] = $new_password;

                    $customer->update($data);

//--------------------------------------Redis Key Update-------------------------------------------------------
                    $customer_id = $customer->_id;
                    $customerArr = $customer->toArray();
                    $customerArr['password'] = $customer->password;
                    $this->redisDb->saveCustomerProfile($customer_id, $customerArr);
//--------------------------------------Redis Key Update-------------------------------------------------------

                    // Purge Account Customer Profile Cache
                    if($customer_id) {
                        $cache_params   = [];
                        $cache_params['customer_id'] = $customer_id;

                        $purge_result   = $this->awsElasticCacheRedis->purgeAccountCustomerCache($cache_params);
                    }

                    $results = 'Password updated successfully';

                } else {
                    $results = 'New password cannot be same as old password';
                }

            } else {
                $results = 'Old passowrd is incorrect';
            }

        } else {
            $results = 'Email is not available';
        }

        return ['results' => $results];
    }

    public function sendNotification($request)
    {
        $requestData = array_except($request->all(), ['_method', '_token']);

        $artist_id = $requestData['artist_id'];
        $customer_id = $requestData['customer_id'];

        $artist_name = \App\Models\Cmsuser::select('first_name', 'last_name')->where('_id', $artist_id)->first();

        if (isset($artist_name) && isset($artist_name['first_name']) && isset($artist_name['last_name'])) {
            $results['artist_name'] = $artist_name['first_name'] . ' ' . $artist_name['last_name'];
        }

        $customerdevices = \App\Models\Customerdeviceinfo::with('artist')
            ->where('customer_id', $customer_id)
            ->where('artist_id', $artist_id)
            ->first();

        $results['fcm_device_token'] = !empty($customerdevices) ? $customerdevices['fcm_device_token'] : '';
        $results['artist_id'] = $artist_id;
//        $results['topic_id'] = str_replace(' ', '', $results['artist_name']);
        $results['title'] = $requestData['title'];
        $results['body'] = $requestData['body'];
        $results['deeplink'] = $requestData['deeplink'];
        $results['priority'] = "high";

        $response = $this->customernotification->sendNotificationToCustomer($results);
        return $response;
    }



    public function purgeAndGetPurchaseContentsMetaIds($requestData){

        $error_messages         =   [];
        $results                =   [];
        $artist_id              =   $requestData['artist_id'];
        $customer_id            =   $requestData['customer_id'];
        $requestData['pruge']   =   'true';

        $results                =   $this->redisDb->purgeAndGetPurchaseContentsMetaIds($requestData);

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function getPurchaseContentsMetaIds($requestData){

        $error_messages         =   [];
        $results                =   [];
        $artist_id              =   $requestData['artist_id'];
        $customer_id            =   $requestData['customer_id'];
        $requestData['purge']   =   isset($requestData['purge']) ? $requestData['purge'] : '';

        $response               =   $this->redisDb->purgeAndGetPurchaseContentsMetaIds($requestData);
        if($response) {
            $results = (isset($response['results'])) ? $response['results'] : [];
            $error_messages = (isset($response['error_messages'])) ? $response['error_messages'] : [];
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }



    public function customerAddCoins($request)
    {
        $requestData = array_except($request->all(), ['_method', '_token']);

        $error_messages = $results = [];
        $results = $this->customerRep->customerAddCoins($requestData);

//------------------------------------------Sync Artist--------------------------------------------------
        $data['customer_id'] = $requestData['customer_id'];
        $data['artist_id'] = $requestData['artist_id'];

        $this->redisDb->syncCustomerArtist($data);
//------------------------------------------Sync Artist--------------------------------------------------

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function fetchCoinsReports($request)
    {
        if (!empty($request['customer_id'])) {
            $requestData = $request;
        } else {
            $requestData = $request->all();
        }

        $error_messages = $results = [];
        $results = $this->customerRep->fetchCoinsReports($requestData);
        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function banneduser($request)
    {
        $requestData = $request->all();

        $artist_id = $requestData['artist_id'];
        $customer_id = $requestData['customer_id'];

        $artist = \App\Models\Cmsuser::where('_id', $artist_id)->first(['first_name', 'last_name']);
        $artist = !empty($artist) ? $artist->toArray() : '';
        $artist_name = @$artist['first_name'] . " " . @$artist['last_name'];

        $customer = \App\Models\Customer::where('_id', $customer_id)->first();

        $data['status'] = 'banned';
        $data['remark_banned'] = $requestData['remark'];
        $data['blocked_by'] = $artist_name;

        $customer->update($data);

//==============================Redis Customer key Delete===============================================================================
        $email = $customer['email'];
        $customer_profile_key = Config::get('cache.keys.customerprofile') . $email;
        $env_customer_profile_key = env_cache_key($customer_profile_key);

        $redisClient = $this->redisDb->PredisConnection();
        $redisClient->del($env_customer_profile_key);
//==============================Redis Customer key Delete===============================================================================

        return ['results' => $customer];

    }


    public function blockContents($postData)
    {
        $requestData = $postData->all();

        $customer_id = !empty($requestData['customer_id']) ? $requestData['customer_id'] : '';
        $artist_id = !empty($requestData['artist_id']) ? $requestData['artist_id'] : '';
        $content_id = !empty($requestData['content_id']) ? $requestData['content_id'] : '';

        $moderationData = Array(
            'entity' => 'content',
            'entity_id' => $content_id,
        );

//============================================Blocked By Producer=======================================================
        if (empty($customer_id)) {

            $blocked_by = 'artist';
            $status = 'inactive';

//            $artist = \App\Models\Cmsuser::where('_id', $artist_id)->first(['first_name', 'last_name']);
//            $artist = !empty($artist) ? $artist->toArray() : '';
//            $artist_name = @$artist['first_name'] . " " . @$artist['last_name'];

            $contentObj = \App\Models\Content::where('_id', $content_id)->first();

            $parent_id = (!empty($contentObj['parent_id']) && $contentObj['parent_id'] != '') ? trim($contentObj['parent_id']) : "";
            $bucket_id = (!empty($contentObj['bucket_id']) && $contentObj['bucket_id'] != '') ? trim($contentObj['bucket_id']) : "";

            $data['status'] = $status;
            $data['remark'] = !empty($requestData['remark']) ? $requestData['remark'] : '';
            $data['blocked_by'] = $blocked_by;

            $contentObj->update($data);

            array_set($moderationData, 'artist_id', $artist_id);
            array_set($moderationData, 'blocked_by', $blocked_by);
            array_set($moderationData, 'status', $status);

//            $moderation = new \App\Models\Moderation($moderationData);
//            $moderation->save();

            //--------------------------------------------Purge Cache Key--------------------------------------------------------
            $purge_result = $this->awsElasticCacheRedis->purgeContentListCache(['content_id' => $content_id, 'bucket_id' => $bucket_id, 'parent_id' => $parent_id]);
            $purge_result = $this->awsElasticCacheRedis->purgeContentDetailCache(['content_id' => $content_id, 'bucket_id' => $bucket_id, 'parent_id' => $parent_id]);
            //--------------------------------------------Purge Cache Key--------------------------------------------------------


//============================================Blocked By Producer=======================================================

//============================================Blocked By Customer=======================================================
        } else {
            $blocked_by = 'customer';

            $results = \App\Models\Customer::where('_id', $customer_id)->first();

            if (!empty($results)) {
                $results->push('block_content_ids', trim(strtolower($content_id)), true);
                $this->redisDb->saveCustomerProfile($customer_id, $results->toArray()); // Block_content_ids Update
            }

            array_set($moderationData, 'customer_id', $customer_id);
            array_set($moderationData, 'blocked_by', $blocked_by);
            array_set($moderationData, 'status', 'active');

//            $moderation = new \App\Models\Moderation($moderationData);
//            $moderation->save();
        }
//============================================Blocked By Customer=======================================================
        return ['results' => $results];
    }


    public function blockComments($postData)
    {
        $requestData = $postData->all();

        $results   = [];

        $customer_id = !empty($requestData['customer_id']) ? $requestData['customer_id'] : '';
        $comment_id = !empty($requestData['comment_id']) ? $requestData['comment_id'] : '';

        $moderationData = Array(
            'entity' => 'comment',
            'entity_id' => $comment_id,
        );

//============================================Blocked By Producer=======================================================
        if (empty($customer_id)) {

            $status = 'inactive';
            $artist_id = !empty($requestData['artist_id']) ? $requestData['artist_id'] : '';

            $artist = \App\Models\Cmsuser::where('_id', $artist_id)->first(['first_name', 'last_name']);
            $artist = !empty($artist) ? $artist->toArray() : '';
            $artist_name = @$artist['first_name'] . " " . @$artist['last_name'];

            $commentObj = \App\Models\Comment::where('_id', $comment_id)->first();

            if (!empty($commentObj)) {

                $data['status'] = $status;
                $data['remark'] = !empty($requestData['remark']) ? $requestData['remark'] : '';
                $data['blocked_by'] = 'artist';

                $commentObj->update($data);

                // array_set($moderationData, 'artist_id', $artist_id);
                // array_set($moderationData, 'blocked_by', 'artist');
                // array_set($moderationData, 'status', $status);
                //
                // $moderation = new \App\Models\Moderation($moderationData);
                // $moderation->save();


                $content_id = $commentObj['entity_id'];
                $content = \App\Models\Content::where('_id', $content_id)->first();
                $bucket_id = (!empty($content['bucket_id']) && $content['bucket_id'] != '') ? trim($content['bucket_id']) : "";
                $parent_id = (!empty($content['parent_id']) && $content['parent_id'] != '') ? trim($content['parent_id']) : "";
                $content_id = (!empty($content['content_id']) && $content['content_id'] != '') ? trim($content['content_id']) : "";
                $comment_id = (!empty($comment_id) && $comment_id != '') ? trim($comment_id) : "";

//--------------------------------------------Purge Cache Key--------------------------------------------------------
                $purge_result = $this->awsElasticCacheRedis->purgeContentListCache(['content_id' => $content_id, 'bucket_id' => $bucket_id, 'parent_id' => $parent_id]);
                $purge_result = $this->awsElasticCacheRedis->purgeContentDetailCache(['content_id' => $content_id, 'bucket_id' => $bucket_id, 'parent_id' => $parent_id]);
                $purge_result = $this->awsElasticCacheRedis->purgeContentCommentListCache(['content_id' => $content_id]);
                $purge_result = $this->awsElasticCacheRedis->purgeContentCommentRepliesListCache(['content_id' => $content_id, 'comment_id' => $comment_id]);
//--------------------------------------------Purge Cache Key--------------------------------------------------------



                //--------------------------------------------Purge CF Cache ---------------------------
                if (env('APP_ENV', 'stg') == 'production') {
                    try {
                        $invalidate_result = $this->awscloudfrontService->invalidateComments();
                    } catch (Exception $e) {
                        $error_messages = ['error' => true, 'type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                        Log::info('AwsCloudfront - Invalidation  : Fail ', $error_messages);
                    }
                }

            }


//==============================Redis Customer key Delete===============================================================

//============================================Blocked By Customer=======================================================
        } else {

            $customerObj = \App\Models\Customer::where('_id', $customer_id)->first();

            if (!empty($customerObj)) {

                $customerObj->push('block_comment_ids', trim(strtolower($comment_id)), true);
                $this->redisDb->saveCustomerProfile($customer_id, $customerObj->toArray()); // Block_comments_ids Update


                array_set($moderationData, 'customer_id', $customer_id);
                array_set($moderationData, 'blocked_by', 'customer');
                array_set($moderationData, 'status', 'active');

//                $moderation = new \App\Models\Moderation($moderationData);
//                $moderation->save();
            }

        }
//============================================Blocked By Customer=======================================================
        return ['results' => $results];
    }



    public function blockCustomer($request)
    {
        $requestData = $request->all();

        $artist_id = $requestData['artist_id'];
        $customer_id = $requestData['customer_id'];

        $artist         = \App\Models\Cmsuser::where('_id', $artist_id)->first(['first_name', 'last_name']);
        $artist         = !empty($artist) ? $artist->toArray() : '';
        $artist_name    = @$artist['first_name'] . " " . @$artist['last_name'];

        $customerObj = \App\Models\Customer::where('_id', $customer_id)->first();

        if (!empty($customerObj)) {

            $data['status']         = 'inactive';
            $data['remark']         = !empty($requestData['remark']) ? $requestData['remark'] : '';
            $data['blocked_by']     = 'artist';

            $customerObj->update($data);


            //inactive comments for block comments
            $blockCommentsData = Array(
                'status' => 'inactive',
                'blocked_by' => 'artist',
                'remark' => !empty($requestData['remark']) ? $requestData['remark'] : ''
            );
            $inactive_comments = \App\Models\Comment::where('customer_id', $customer_id)->update($blockCommentsData);


            //--------------------------------------------Purge CF Cache ---------------------------
            if (env('APP_ENV', 'stg') == 'production') {
                try {
                    $invalidate_result = $this->awscloudfrontService->invalidateComments();
                } catch (Exception $e) {
                    $error_messages = ['error' => true, 'type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                    Log::info('AwsCloudfront - Invalidation  : Fail ', $error_messages);
                }
            }

        }

//        $moderationData = Array(
//            'entity' => 'customer',
//            'entity_id' => $customer_id,
//            'artist_id' => $artist_id,
//            'status' => 'active',
//            'blocked_by' => 'artist'
//        );
//
//        $moderation = new \App\Models\Moderation($moderationData);
//        $moderation->save();

        return ['results' => $customerObj];
    }
}
