<?php

namespace App\Services;

use Carbon\Carbon;
use Config, Log, Hash, File;
use GuzzleHttp\PrepareBodyMiddleware;

use Predis;
use Predis\Connection\Aggregate\RedisCluster;
use Illuminate\Support\Facades\Redis;
use Predis\Client as PredisClient;
use App\Services\Jwtauth;
use App\Services\ArtistService;
use App\Services\Kraken;
use App\Services\Gcp;
use App\Services\Cache\AwsElasticCacheRedis;
use App\Services\Image\Kraken as KrakenImage;

use App\Repositories\Contracts\BucketInterface;
use App\Repositories\Contracts\ContentInterface;
use App\Repositories\Contracts\PurchaseInterface;


Class RedisDb
{

    protected $redisClient;
    protected $env;
    protected $expire_time;
    protected $customer_profile_expire_time;
    protected $kraken;
    protected $jwtauth;
    protected $gcp;
    protected $bucketRepObj;
    protected $contentRepObj;
    protected $giftRepObj;
    protected $packageRepObj;
    protected $awsElasticCacheRedis;
    protected $krakenImage;

    private $cloudfront_image_base_url;


    public function __construct(
        BucketInterface $bucketRepObj,
        Jwtauth $jwtauth,
        ArtistService $artistService,
        Kraken $kraken,
        Gcp $gcp,
        AwsElasticCacheRedis $awsElasticCacheRedis,
        KrakenImage $krakenImage
    ){
        $this->bucketRepObj     = $bucketRepObj;
        $this->jwtauth          = $jwtauth;
        $this->artistservice    = $artistService;
        $this->kraken           = $kraken;
        $this->gcp              = $gcp;
        $this->awsElasticCacheRedis = $awsElasticCacheRedis;
        $this->krakenImage      = $krakenImage;

        $this->expire_time                      =   600; //in seconds
        $this->customer_profile_expire_time     =   (43200 * 60); // 30 days in seconds
        $this->content_expire_time              =   600; //in seconds
        $this->env                              =   env('APP_ENV', 'production');
        $this->get_from_db                      =   false;



//        if ($this->env == 'production') {
//            $parameters = Config::get('cache.production_parameters');
//
//        } else {
//            $parameters = Config::get('cache.staging_parameters');
//        }
//
//        $options = [
//            'cluster' => 'redis',
//            'parameters' => []
//        ];
//
//        $this->redisClient = new PredisClient($parameters, $options);


        // Put your AWS ElastiCache Configuration Endpoint here.
        $configuration_endpoint  = Config::get('product.'. env('PRODUCT') .'.cache.aws_elastic_cache_cluster_endpoint');

        $parameters  = [$configuration_endpoint];

        // Tell client to use 'cluster' mode.
        $options  = ['cluster' => 'redis'];
        // Create your redis client
        $this->redisClient = new PredisClient($parameters, $options);

        // Set Cloudfront URL for images form config
        $this->cloudfront_image_base_url = Config::get('product.'. env('PRODUCT') .'.cloudfront.urls.base_image');
    }


    public function PredisConnection()
    {
        return $this->redisClient;
    }


    public function createCustomer($customer_id)
    {
        if ($customer_id != '') {
            try {

                $customerInfoArr = $this->getCustomerInfoFromDB($customer_id);

                $this->saveCustomerProfile($customer_id, $customerInfoArr);

                $this->saveCustomerCoinsArtistWiseXP($customer_id, $customerInfoArr);

                $this->saveCustomerMetaIds($customer_id, $customerInfoArr);


            } catch (\Exception $e) {
                $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                \Log::info('RedisDb createCustomer: Fail ', $message);
            }
        }
    }

    public function customerRegister($postdata)
    {
        $error_messages = array();
        $status_code = 201;
        $data = array_except($postdata->all(), ['password_confirmation', 'image_url']);

        $email = trim(strtolower($data['email']));

        if (empty($data['first_name']) && empty($data['last_name'])) {
            $data['first_name'] = explode("@", $data['email'])[0];
        }

        $customer = \App\Models\Customer::where('email', '=', $email)->first();

        $photo = Config::get('kraken.customerprofile_photo');

        if(isset($postdata['picture']) && $postdata['picture']) {
            $parmas = ['url' => $postdata['picture'], 'type' => 'customerprofile'];

            $kraked_img = $this->krakenImage->uploadToAws($parmas);
            if(!empty($kraked_img) && !empty($kraked_img['success']) && $kraked_img['success'] === true && !empty($kraked_img['results'])){
                $photo  = $kraked_img['results'];

                array_set($data, 'photo', $photo);
                array_set($data, 'is_kraken', 1);

                $postdata['picture'] = isset($photo['cover']) ? $photo['cover'] : '';
            }
            else {
                unset($postdata['picture']);
            }
        }

        if (empty($postdata['picture'])) {

            if (empty($data['first_name'])) {
                array_set($data, 'picture',  $this->cloudfront_image_base_url . '/default/customersprofile/default.png');
            } else {
                array_set($data, 'picture', $this->cloudfront_image_base_url . '/default/customersprofile/' . strtolower(substr($data['first_name'], 0, 1)) . '.png');
            }

            $cover = $data['picture'];
            $width = 80;
            $height = 80;

            array_set($photo, 'thumb', $cover);
            array_set($photo, 'thumb_width', $width);
            array_set($photo, 'thumb_height', $height);

            array_set($photo, 'cover', $cover);
            array_set($photo, 'cover_width', $width);
            array_set($photo, 'cover_height', $height);

            array_set($data, 'photo', $photo);

        }

        if ($postdata['identity'] == 'email') {

            if (!empty($customer)) {
                $error_messages[] = 'Customer already register';
                $status_code = 202;
            }
        }
        if (empty($error_messages)) {

            if ($customer) {
                $account_link = $customer->account_link;
                $account_link[$data['identity']] = 1;
                $data['account_link'] = $account_link;
                $data = array_except($data, ['identity', 'password', 'password_confirmation', 'device_id', 'segment_id', 'fcm_id', 'platform', 'coins']);
                $customer = new \App\Models\Customer($data);
                $customer->save();

            } else {
                $account_link = array('email' => 0, 'google' => 0, 'facebook' => 0, 'twitter' => 0);
                $account_link[$data['identity']] = 1;
                $data['account_link'] = $account_link;
                $data['status'] = 'active';
                $data['coins'] = 0;
                $data = array_except($data, ['password_confirmation', 'device_id', 'segment_id', 'fcm_id', 'platform', 'coins']);
                $customer = new \App\Models\Customer($data);
                $customer->save();
            }


            $platform = (request()->header('platform')) ? trim(request()->header('platform')) : "";
            $artist = (request()->header('artistid')) ? trim(request()->header('artistid')) : "";

            if ($platform != '') {
                $customer->push('platforms', trim(strtolower($platform)), true);
            }

            if ($artist != '') {
                $customer->push('artists', trim(strtolower($artist)), true);
            }

            $data['customer_id'] = $customer['_id'];
            $data['artist_id'] = $artist;

            $this->syncCustomerArtist($data);

//-------------------------------------------Assign Channel While Register------------------------------------------------

            $customer_id = $customer['_id'];
            $artist_id = $artist;

            $this->artistservice->assignChannel($customer_id, $artist_id);

//-------------------------------------------Assign Channel While Register------------------------------------------------

            $results['customer'] = apply_cloudfront_url($customer);

            $results['token'] = $this->jwtauth->createLoginToken($customer);
        }

        $results['status_code'] = $status_code;

        return ['error_messages' => $error_messages, 'results' => $results];
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

//            $customerartist = \App\Models\Customerartist::where('customer_id', '=', $customer_id)->where('artist_id', '=', $artist_id)->first();

            return true;
        }

        return false;
    }


    public function saveCustomerProfile($customer_id, $customer = array())
    {
        if (!empty($customer)) {

            $purchase_stickers = !empty($customer['purchase_stickers']) ? $customer['purchase_stickers'] : null;
            array_set($customer, 'purchase_stickers', $purchase_stickers);

            $block_content_ids = (isset($customer['block_content_ids'])) ? implode(",", $customer['block_content_ids']) : '';
            array_set($customer, 'block_content_ids', $block_content_ids);

            $block_comments_ids = (isset($customer['block_comment_ids'])) ? implode(",", $customer['block_comment_ids']) : '';
            array_set($customer, 'block_comment_ids', $block_comments_ids);

            $photo = (isset($customer['photo'])) ? json_encode($customer['photo']) : '';
            array_set($customer, 'photo', $photo);

            array_set($customer, 'last_visited', Carbon::now());

        }

        $customerInfoArr = (!empty($customer)) ? $customer : $this->getCustomerInfoFromDB($customer_id);
        $key = Config::get('cache.keys.customerprofile') . $customerInfoArr['email'];
        $envkey = env_cache_key($key);

        $env_cache_customer_proifle_key_val = array_only($customerInfoArr, ['_id', 'google_id', 'facebook_id', 'identity', 'email', 'first_name', 'last_name', 'picture', 'gender', 'block_content_ids', 'block_comment_ids', 'last_visited', 'status', 'password', 'mobile', 'photo', 'purchase_stickers']);

        $this->redisClient->hmset($envkey, $env_cache_customer_proifle_key_val);
        $this->redisClient->expire($envkey, $this->customer_profile_expire_time);
    }


    public function getCustomerProfile($customer_id)
    {
        $key = Config::get('cache.keys.customerprofile') . $customer_id;
        $envkey = env_cache_key($key);

        $key_value = $this->redisClient->hget($envkey, 'customer');

        return $key_value;
    }

    public function saveCustomerCoinsArtistWiseXP($customer_id, $customer = array())
    {

        $customerInfoArr = (!empty($customer)) ? $customer : $this->getCustomerInfoFromDB($customer_id);
        $customerXPArtistWiseArr = $this->getCustomerArtistWiseXPFromDB($customer_id);

        $customer_conisxps_key = Config::get('cache.keys.customercoinsxp') . $customer_id;
        $env_cache_customer_conisxps_key = env_cache_key($customer_conisxps_key);

        $arrprofile_coins_xp = array_merge($customerInfoArr, $customerXPArtistWiseArr);
        $coustomer_conisxp_keys = array_merge(array_keys($customerXPArtistWiseArr), ['coins']);
        $env_cache_customer_conisxps_key_val = array_only($arrprofile_coins_xp, $coustomer_conisxp_keys);


        $this->redisClient->hmset($env_cache_customer_conisxps_key, $env_cache_customer_conisxps_key_val);
    }

    public function getCustomerCoinsArtistWiseXP($customer_id)
    {
        $key = Config::get('cache.keys.customercoinsxp') . $customer_id;
        $envkey = env_cache_key($key);
        $key_value = $this->redisClient->hgetall($envkey);

        return $key_value;
    }

    public function saveCustomerCoins($customer_id, $coins = null)
    {
        $key = Config::get('cache.keys.customercoinsxp') . $customer_id;
        $envkey = env_cache_key($key);

        if (empty($coins)) {
            $customer_coins = \App\Models\Customer::where('_id', $customer_id)->first(['coins']);
            $customer_coins = !empty($customer_coins['coins']) ? $customer_coins['coins'] : 0;

        } else {
            $customer_coins = $coins;
        }
        $store_values_into_keys = $this->redisClient->hset($envkey, 'coins', $customer_coins);
        $this->redisClient->expire($envkey, $this->expire_time);
        return $store_values_into_keys;

    }

    public function getCustomerCoins($customer_id)
    {
        $key = Config::get('cache.keys.customercoinsxp') . $customer_id;
        $envkey = env_cache_key($key);

        $coins_key_exists = $this->redisClient->hexists($envkey, 'coins');

        if (!$coins_key_exists) {
            $this->saveCustomerCoins($customer_id);
        }

        $response = $this->redisClient->hget($envkey, 'coins');

        $coins = intval($response);

        return $coins;
    }


    public function saveCustomerXpForAnArtist($customer_id, $artist_id, $xp = null)
    {
        $key = Config::get('cache.keys.customercoinsxp') . $customer_id;
        $envkey = env_cache_key($key);

        $customer_xp = \App\Models\Customerartist::where('customer_id', '=', $customer_id)->where('artist_id', '=', $artist_id)->first([
            'xp', 'fan_xp', 'comment_channel_no', 'gift_channel_no'
        ]);

        $customer_xp = !empty($customer_xp['xp']) ? $customer_xp['xp'] : 0;
        $customer_comments_channel = !empty($customer_xp['comment_channel_no']) ? $artist_id . ".c." . $customer_xp['comment_channel_no'] : $artist_id . ".c.0";
        $customer_gifts_channel = !empty($customer_xp['gift_channel_no']) ? $artist_id . ".g." . $customer_xp['comment_channel_no'] : $artist_id . ".g.0";


        $this->redisClient->hset($envkey, 'xp_' . $artist_id, $customer_xp);
        $this->redisClient->hset($envkey, 'comment_channel_name_' . $artist_id, $customer_comments_channel);
        $this->redisClient->hset($envkey, 'gift_channel_name_' . $artist_id, $customer_gifts_channel);
        $this->redisClient->expire($envkey, $this->expire_time);
    }


    public function getCustomerXpForAnArtist($customer_id, $artist_id)
    {
        $key = Config::get('cache.keys.customercoinsxp') . $customer_id;
        $envkey = env_cache_key($key);

        $xp_key_exists = $this->redisClient->hexists($envkey, 'xp_' . $artist_id);

        if (!$xp_key_exists) {
            $this->saveCustomerXpForAnArtist($customer_id, $artist_id);
        }

        $response['xp'] = intval($this->redisClient->hget($envkey, 'xp_' . $artist_id));
        $response['comment_channel_name'] = $this->redisClient->hget($envkey, 'comment_channel_name_' . $artist_id);
        $response['gift_channel_name'] = $this->redisClient->hget($envkey, 'gift_channel_name_' . $artist_id);

        return $response;
    }

    public function getCustomerCoinsXpForAnArtist($customer_id, $artist_id)
    {
        $key = Config::get('cache.keys.customercoinsxp') . $customer_id;
        $envkey = env_cache_key($key);

//                echo PHP_EOL.$env_cache_customer_proifle_key.PHP_EOL;

        $response = $this->redisClient->hgetall($envkey);

        return ['results' => $response];
    }

    public function saveCustomerMetaIds($customer_id, $customer = array())
    {

        $customerInfoArr = (!empty($customer)) ? $customer : $this->getCustomerInfoFromDB($customer_id);
        $customerMetaIdsArr = $this->getCustomerMetaIdsFromDB($customer_id);

        $customer_metaids_key = Config::get('cache.keys.customermetaids') . $customer_id;
        $env_cache_customer_metaids_key = env_cache_key($customer_metaids_key);

        $arrprofile_meatids = array_merge($customerInfoArr, $customerMetaIdsArr);
        $coustomer_metaids_keys = array_merge(array_keys($customerMetaIdsArr), ['block_content_ids']);
        $env_cache_customer_metaids_key_val = array_only($arrprofile_meatids, $coustomer_metaids_keys);

        $this->redisClient->hmset($env_cache_customer_metaids_key, $env_cache_customer_metaids_key_val);
    }

    public function getCustomerMetaIds($customer_id)
    {
        $key = Config::get('cache.keys.customermetaids') . $customer_id;
        $envkey = env_cache_key($key);
        $key_value = $this->redisClient->hgetall($envkey);

        return $key_value;
    }

    private function getCustomerInfoFromDB($customer_id)
    {
        $customerArr = [];
        if ($customer_id != '') {
            $customer = \App\Models\Customer::where('_id', $customer_id)->first();
            if ($customer) {
                $customerArr = [
                    '_id' => (isset($customer['_id'])) ? trim($customer['_id']) : '',
//                    'cid' => (isset($customer['cid'])) ? trim($customer['cid']) : '',
                    'google_id' => (isset($customer['google_id'])) ? strtolower(trim($customer['google_id'])) : '',
                    'facebook_id' => (isset($customer['facebook_id'])) ? strtolower(trim($customer['facebook_id'])) : '',
                    'identity' => (isset($customer['identity'])) ? strtolower(trim($customer['identity'])) : '',
                    'email' => (isset($customer['email'])) ? strtolower(trim($customer['email'])) : '',
                    'first_name' => (isset($customer['first_name'])) ? strtolower(trim($customer['first_name'])) : '',
                    'last_name' => (isset($customer['last_name'])) ? strtolower(trim($customer['last_name'])) : '',
                    'picture' => (isset($customer['picture'])) ? strtolower(trim($customer['picture'])) : '',
                    'coins' => (isset($customer['coins'])) ? intval($customer['coins']) : '',
                    'last_visited' => (isset($customer['last_visited'])) ? trim($customer['last_visited']) : '',
                    'block_content_ids' => (isset($customer['block_content_ids'])) ? implode(",", $customer['block_content_ids']) : '',
                    'block_comment_ids' => (isset($customer['block_comment_ids'])) ? implode(",", $customer['block_comment_ids']) : '',
                    'status' => (isset($customer['status'])) ? $customer['status'] : 'inactive',
                ];
            }// $customer
        }//$customer_id
        return $customerArr;
    }

    private function getCustomerArtistWiseXPFromDB($customer_id)
    {

        $customerArtistWiseXPArr = [];
        $customerArtistWiseXP = \App\Models\Customerartist::where('customer_id', '=', $customer_id)->get(['artist_id', 'xp', 'fan_xp'])->toArray();

        foreach ($customerArtistWiseXP as $item) {
            if (isset($item['artist_id'])) {
                $xpkey = 'xp_' . $item['artist_id'];
                $xp = (isset($item['xp'])) ? intval($item['xp']) : 0;
                $fanxpkey = 'fan_xp_' . $item['artist_id'];
                $fan_xp = (isset($item['fan_xp'])) ? intval($item['fan_xp']) : 0;

                $customerArtistWiseXPArr[$xpkey] = $xp;
                $customerArtistWiseXPArr[$fanxpkey] = $fan_xp;
            }
        }


        return $customerArtistWiseXPArr;
    }

    private function getCustomerMetaIdsFromDB($customer_id)
    {

        $customerArtistWiseMetaIdsArr = [];
        $artist_roles = \App\Models\Role::where('slug', 'artist')->lists('_id');
        $artist_role_ids = ($artist_roles) ? $artist_roles->toArray() : [];
        $artists = \App\Models\Cmsuser::where('status', '=', 'active')->whereIn('roles', $artist_role_ids)->get()->pluck('_id');
        $artist_ids = ($artists) ? $artists->toArray() : [];

        foreach ($artist_ids as $artist_id) {

            $like_content_ids_key = 'like_contentids_' . $artist_id;
            $purchase_content_ids_key = 'purchase_contentids_' . $artist_id;
            $like_content_ids = \App\Models\Like::where('customer_id', '=', $customer_id)->where('entity', 'content')->where("status", "active")->where('artist_id', $artist_id)->lists('entity_id')->toArray();
            $like_content_ids = ($like_content_ids) ? array_unique($like_content_ids) : [];
            $purchase_content_ids = \App\Models\Purchase::where('customer_id', '=', $customer_id)->where('entity', 'contents')->where('artist_id', $artist_id)->lists('entity_id')->toArray();
            $purchase_content_ids = ($purchase_content_ids) ? array_unique($purchase_content_ids) : [];

            $customerArtistWiseMetaIdsArr[$like_content_ids_key] = json_encode($like_content_ids);
            $customerArtistWiseMetaIdsArr[$purchase_content_ids_key] = json_encode($purchase_content_ids);
        }

        return $customerArtistWiseMetaIdsArr;
    }

    public function flushall($redisIP = null)
    {
        if (empty($redisIP)) {

            if ($this->env == 'production') {
                $parameters = Config::get('cache.production_parameters');
            } else {
                $parameters = Config::get('cache.staging_parameters');
            }
            $redis_connection = $parameters;

            $flush = '';
            foreach ($redis_connection as $key => $val) {
                $client = new \Predis\Client($val);
                $replication_info = $client->info('Replication');

                if (isset($replication_info) && isset($replication_info['Replication']) && isset($replication_info['Replication']['role']) && $replication_info['Replication']['role'] == 'master') {
                    $flush = $client->flushall();
                } else {
                    $flush = 'Error';
                }
            }
            return $flush;
        } else {
            $redisIP = 'tcp://' . $redisIP;
            $flush = '';
            $client = new \Predis\Client($redisIP);
            $replication_info = $client->info('Replication');

            if (isset($replication_info) && isset($replication_info['Replication']) && isset($replication_info['Replication']['role']) && $replication_info['Replication']['role'] == 'master') {
                $flush = $client->flushall();
            } else {
                $flush = 'Error';
            }
            return $flush;
        }
    }

    public function monitoringLogs()
    {


        if ($this->env == 'production') {
            $parameters = Config::get('cache.production_parameters');
        } else {
            $parameters = Config::get('cache.staging_parameters');
        }
        $redis_connection = $parameters;

        $info = [];
        foreach ($redis_connection as $key => $val) {
            $client = new \Predis\Client($val);

            $info[$key]['all_keys'] = $client->keys('*');
            $info[$key]['current_ip'] = $val;
            $info[$key]['replication'] = $client->info('Replication')['Replication'];
            $info[$key]['cluster'] = $client->info('Cluster')['Cluster'];
            $info[$key]['server'] = $client->info('Server')['Server'];
            $info[$key]['clients'] = $client->info('Clients')['Clients'];
            $info[$key]['memory'] = $client->info('Memory')['Memory'];

        }

        return $info;
    }


    public function customerAuthLogin($data)
    {
        $new_user   = false;
        $error_messages = [];
        $identity = trim($data['identity']);
        $email = strtolower(trim($data['email']));

        //------------------------Profile-------------------------------------

        $customer_profile_key = Config::get('cache.keys.customerprofile') . $email;
        $env_customer_profile_key = env_cache_key($customer_profile_key);
//        $env_customer_profile_key = 'raman@gmail.com';

        $customer_key_exists = $this->redisClient->exists($env_customer_profile_key); //Redis Key Exist
//        print_b($this->redisClient->del($env_customer_profile_key));

        if (!$customer_key_exists) {

            //if not exist
            $customer = \App\Models\Customer::where('email', '=', $email)->first(); //DB existance

            $customerarr = [];

            if (!empty($customer)) {
                $customer_id = $customer->_id;
                $customerarr = $customer->toArray();
                $customerarr['password'] = $customer->password;
            }

            if (empty($customer) && $identity != 'email') {

//                if ($identity == 'facebook') {
//                    $data['picture'] = 'https://graph.facebook.com/' . $data['facebook_id'] . '/picture?type=large';
//                }
//                if ($identity == 'google') {
//                    $data['picture'] = trim($data['profile_pic_url']);
//                }

                $cust_info = $this->customerRegister($data);
                $new_user       = true;
                $customerarr = $cust_info['results']['customer'];
                $customer_id = $customerarr['_id'];
            }

            if (!empty($customer) && isset($customer['status']) && $customer['status'] != 'active') {
                $error_messages[] = 'Your account has been suspended temporarily by celebrity.Please contact us on support@razrmedia.com';
            }

            if (!empty($customer) && isset($data['password']) && $data['password'] != '' && $identity == 'email') {
                if (!Hash::check(trim($data['password']), $customer['password'])) {
                    $error_messages[] = 'Invalid credentials, please try again';
                }
            }

            if (empty($customerarr)) {
                $error_messages[] = 'Customer doesnot exist';
            }

            if (empty($error_messages)) {
                $this->saveCustomerProfile($customer_id, $customerarr); // Store Data into Redis (Customer Profile)
            }
        }

        $customer_profile = $this->redisClient->hgetall($env_customer_profile_key); // Get data from Redis (Customer Profile)


        if($customer_profile && !isset($customer_profile['password'])){
            $customer = \App\Models\Customer::where('email', '=', $email)->first(); //DB existance
            $customer_id = $customer->_id;
            $customerArr = $customer->toArray();
            $customerArr['password'] = $customer->password;
            $this->saveCustomerProfile($customer_id, $customerArr);
            $customer_profile = $this->redisClient->hgetall($env_customer_profile_key); // Get data from Redis (Customer Profile)
        }

        if ($identity == 'email' && empty($error_messages)) {
            if (!Hash::check(trim($data['password']), $customer_profile['password'])) {
                $error_messages[] = 'Invalid credentials, please try again';
            }
        }

        if (empty($error_messages)) {

            $badges = [
                [
                    'name'  => 'super fan',
                    'level' => 1,
                    'icon'  => ( $this->cloudfront_image_base_url . '/default/badges/super-fan.png'),
                    'status'=> true
                ],
                [
                    'name'  => 'loyal fan',
                    'level' => 2,
                    'icon'  => ( $this->cloudfront_image_base_url . '/default/badges/loyal-fan.png'),
                    'status'=> false],
                [
                    'name'  => 'die hard fan',
                    'level' => 3,
                    'icon'  => ( $this->cloudfront_image_base_url . '/default/badges/die-hard-fan.png'),
                    'status'=> false
                ],
                [
                    'name'  => 'top fan',
                    'level' => 4,
                    'icon'  => ( $this->cloudfront_image_base_url . '/default/badges/top-fan.png'),
                    'status'=> false
                ]
            ];

            $customer_profile['badges'] = $badges;

            $customer_profile['photo'] = json_decode($customer_profile['photo']);

            //------------------------Profile-------------------------------------

            $customer_id = $customer_profile['_id'];
            $artist_id = $data['artist_id'];
            $platform = $data['platform'];

            //------------------------Coins & XP-------------------------------------

            $coinsxp = $this->getCustomerXpForAnArtist($customer_id, $artist_id);
            $coinsxp['coins'] = $this->getCustomerCoins($customer_id);

            //------------------------Coins & XP-------------------------------------


            //------------------------Meta Ids---------------------------------------


            //Manage Purchase Ids
            $cacheParams                    =   [];
            $hash_name                      =   env_cache(Config::get('cache.hash_keys.customer_temp_metaids').$customer_id);
            $hash_field                     =   'purchase_content_ids:'.$artist_id;
            $cache_miss                     =   false;

            $cacheParams['hash_name']       =   $hash_name;
            $cacheParams['hash_field']      =   (string) $hash_field;

            $purchase_content_ids_results   =   $this->awsElasticCacheRedis->getHashData($cacheParams);
            if(empty($purchase_content_ids_results)){
                $purchase_content_ids               =   \App\Models\Purchase::where('customer_id', '=', $customer_id)->where('entity', 'contents')->where('artist_id', $artist_id)->lists('entity_id')->toArray();
                $passbook_purchase_content_ids      =   \App\Models\Passbook::where('customer_id', '=', $customer_id)->where('entity', 'contents')->where('artist_id', $artist_id)->lists('entity_id')->toArray();
                $purchase_content_ids_unique        =   array_values(array_unique(array_merge($purchase_content_ids, $passbook_purchase_content_ids)));

                $cacheParams['hash_field_value']    =   $purchase_content_ids_unique;
                $saveToCache                        =   $this->awsElasticCacheRedis->saveHashData($cacheParams);
                $cache_miss                         =   true;
                $purchase_content_ids_results       =   $this->awsElasticCacheRedis->getHashData($cacheParams);
            }

            //Manage Like Ids
            $cacheParams                    =   [];
            $hash_name                      =   env_cache(Config::get('cache.hash_keys.customer_temp_metaids').$customer_id);
            $hash_field                     =   'like_content_ids';
            $cache_miss                     =   false;

            $cacheParams['hash_name']       =   $hash_name;
            $cacheParams['hash_field']      =   (string) $hash_field;

            $like_content_ids_results       =   $responses = $this->awsElasticCacheRedis->getHashData($cacheParams);
            if(empty($like_content_ids_results)){
                $like_content_ids                   =   \App\Models\Like::where('customer_id', '=', $customer_id)->where('entity', 'content')->where("status", "active")->where('artist_id', $artist_id)->lists('entity_id')->toArray();
                $like_content_ids_unique            =   array_values(array_unique(array_merge([], $like_content_ids)));

                $cacheParams['hash_field_value']    =   $like_content_ids_unique;
                $saveToCache                        =   $this->awsElasticCacheRedis->saveHashData($cacheParams);
                $cache_miss                         =   true;
                $like_content_ids_results           =   $this->awsElasticCacheRedis->getHashData($cacheParams);
            }


            $metaids['like_content_ids']        =   !empty($like_content_ids_results) ? $like_content_ids_results : [];
            $metaids['purchase_content_ids']    =   !empty($purchase_content_ids_results) ? $purchase_content_ids_results : [];


            $checkExistanceOfStickers           =   \App\Models\Passbook::where('artist_id', $artist_id)->where('customer_id', $customer_id)->where('entity', 'stickers')->first();

            $metaids['block_content_ids']       =   !empty($customer_profile['block_content_ids']) ? explode(",", $customer_profile['block_content_ids']) : [];
            $metaids['block_comment_ids']       =   !empty($customer_profile['block_comment_ids']) ? explode(",", $customer_profile['block_comment_ids']) : [];
            $metaids['purchase_stickers']       =   !empty($checkExistanceOfStickers) ? true : false;

            //------------------------Meta Ids-------------------------------------

            //------------------------Device Info----------------------------------

            $customerdeviceinfo_arr = Array(
                'customer_id' => $customer_id,
                'artist_id' => $artist_id,
                'platform' => $platform,
                'fcm_id' => (isset($data['fcm_id']) && $data['fcm_id'] != '') ? trim($data['fcm_id']) : "",
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

            $this->updateDeviceInfos($customerdeviceinfo_arr);

            //------------------------Device Info-------------------------------------
            $customer_profile = array_except($customer_profile, ['password', 'block_content_ids', 'block_comment_ids', 'purchase_stickers']);

            $response['customer'] = apply_cloudfront_url($customer_profile);
            $response['coinsxp'] = $coinsxp;
            $response['metaids'] = $metaids;
            $response['token'] = $this->jwtauth->createLoginToken($customer_profile);
            $response['new_user'] = $new_user;
        }

        return ['error_messages' => $error_messages, 'results' => !empty($response) ? $response : []];
    }


    public function metaids($customer_id, $artist_id)
    {
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

        $customerData = [];

        $customerData['like_content_ids'] = ($like_content_ids) ? $like_content_ids : [];
        $customerData['purchase_content_ids'] = ($purchase_content_ids) ? $purchase_content_ids : [];

        return $customerData;
    }


    public function updateDeviceInfos($customerdeviceinfo_arr)
    {
        $platform = $customerdeviceinfo_arr['platform'];
        $customer_id = $customerdeviceinfo_arr['customer_id'];
        $artist_id = $customerdeviceinfo_arr['artist_id'];
        $fcm_id = $customerdeviceinfo_arr['fcm_id'];


        $env_deviceinfo_key_lists = env_cache_key(Config::get('cache.keys.devicetokens'));

        $deviceinfo_key = Config::get('cache.keys.customerdeviceinfos') . $platform . '_' . $customer_id . '_' . $artist_id;

        $env_deviceinfo_key_hash = env_cache_key($deviceinfo_key); // KEYS for Customer Device Info

        $get_device_info_in_hash = $this->redisClient->hgetall($env_deviceinfo_key_hash);
        $exist_device_info_in_hash = in_array($fcm_id, $get_device_info_in_hash);

        if (!$exist_device_info_in_hash) {

            $this->redisClient->hmset($env_deviceinfo_key_hash, $customerdeviceinfo_arr);
            $this->redisClient->expire($env_deviceinfo_key_hash, 864000); // 10 Days in Seconds

            $exist_key = $this->redisClient->exists($env_deviceinfo_key_lists);

            if (!$exist_key) {
                $this->redisClient->rpush($env_deviceinfo_key_lists, $env_deviceinfo_key_hash);
            } else {
                $this->redisClient->rpushx($env_deviceinfo_key_lists, $env_deviceinfo_key_hash); //Push values when not exists in keys
            }

        }
    }

    public function getMetaIds($postData)
    {
        $error_message  =   [];
        $results        =   [];
        $customer_id    =   $postData['customer_id'];
        $artist_id      =   $postData['artist_id'];
        $metaids        =   [];

        if (!empty($customer_id)) {

            //----------------------------------------------------------Likes & Purchase Ids--------------------------------------------------------------------------------------
            $metaids_key = Config::get('cache.keys.customermetaids') . $customer_id;
            $env_metaids_key = env_cache_key($metaids_key); // KEYS for Metaids

            $metaids_field_exist_like_content_ids = $this->redisClient->hexists($env_metaids_key, 'like_content_ids');  //Redis Key Exist
            $metaids_field_exist_purchase_content_ids = $this->redisClient->hexists($env_metaids_key, 'purchase_content_ids');  //Redis Key Exist


            if (!$metaids_field_exist_like_content_ids) { //DB Data check
                $like_content_ids = \App\Models\Like::where('customer_id', '=', $customer_id)->where('entity', 'content')->where("status", "active")->where('artist_id', $artist_id)->lists('entity_id')->toArray();
                $this->redisClient->hset($env_metaids_key, 'like_content_ids', implode(",", $like_content_ids));
                $this->redisClient->expire($env_metaids_key, 600); // 10 minutes in Seconds
            }

            if (!$metaids_field_exist_purchase_content_ids) { //DB Data check
                $purchase_content_ids = \App\Models\Purchase::where('customer_id', '=', $customer_id)->where('entity', 'contents')->where('artist_id', $artist_id)->lists('entity_id')->toArray();
                $passbook_purchase_content_ids = \App\Models\Passbook::where('customer_id', '=', $customer_id)->where('entity', 'contents')->where('artist_id', $artist_id)->lists('entity_id')->toArray();
                $purchase_content_ids_unique  = array_unique(array_merge($purchase_content_ids, $passbook_purchase_content_ids));
                $this->redisClient->hset($env_metaids_key, 'purchase_content_ids', implode(",", $purchase_content_ids_unique));
                $this->redisClient->expire($env_metaids_key, 600); // 10 minutes in Seconds
            }

            $metaids['like_content_ids'] = $this->redisClient->hget($env_metaids_key, 'like_content_ids');
            $metaids['like_content_ids'] = !empty($metaids['like_content_ids']) ? explode(",", $metaids['like_content_ids']) : [];

            $metaids['purchase_content_ids'] = $this->redisClient->hget($env_metaids_key, 'purchase_content_ids');
            $metaids['purchase_content_ids'] = !empty($metaids['purchase_content_ids']) ? explode(",", $metaids['purchase_content_ids']) : [];
//----------------------------------------------------------Likes & Purchase Ids--------------------------------------------------------------------------------------

//----------------------------------------------------------Block Content Ids--------------------------------------------------------------------------------------
            $customerInfo = \App\Models\Customer::where('_id', $customer_id)->where('status', 'active')->first();
            $customerInfo = !empty($customerInfo) ? $customerInfo->toArray() : [];
            $email = $customerInfo['email'];

            $customer_profile_key = Config::get('cache.keys.customerprofile') . $email;
            $env_customer_profile_key = env_cache_key($customer_profile_key);

            $customer_key_exists = $this->redisClient->exists($env_customer_profile_key); //Redis Key Exist

            if (!$customer_key_exists) {
                $this->saveCustomerProfile($customer_id, $customerInfo); // Store Data into Redis (Customer Profile)
//                $checkExistanceOfStickers           = \App\Models\Passbook::where('artist_id', $artist_id)->where('customer_id', $customer_id)->where('entity', 'stickers')->first();
            }

            $customer_profile = $this->redisClient->hgetall($env_customer_profile_key); // Get data from Redis (Customer Profile)

            $metaids['block_content_ids'] = !empty($customer_profile['block_content_ids']) ? explode(",", $customer_profile['block_content_ids']) : [];
            $metaids['block_comment_ids'] = !empty($customer_profile['block_comment_ids']) ? explode(",", $customer_profile['block_comment_ids']) : [];
//----------------------------------------------------------Block Content Ids--------------------------------------------------------------------------------------

            $metaids['purchase_stickers'] = !empty($customer_profile['purchase_stickers']) ? true : false;

        }
        return ['error_messages' => $error_message, 'results' => $metaids];
    }





    public function purgeAndGetPurchaseContentsMetaIds($postData)
    {
        $error_message                  =   [];
        $results                        =   [];
        $purchase_content_ids_results   =   [];
        $customer_id                    =   !empty($postData['customer_id']) ? $postData['customer_id'] : "";
        $artist_id                      =   !empty($postData['artist_id']) ? $postData['artist_id'] : "";
        $purge                          =   !empty($postData['purge']) ? $postData['purge'] : "";

        if (!empty($customer_id)) {

            //Manage Purchase Ids
            $cacheParams                    =   [];
            $hash_name                      =   env_cache(Config::get('cache.hash_keys.customer_temp_metaids').$customer_id);
            $hash_field                     =   'purchase_content_ids:'. $artist_id;
            $cache_miss                     =   false;

            $cacheParams['hash_name']       =   $hash_name;
            $cacheParams['hash_field']      =   (string) $hash_field;

            if($purge == ''){
                $purchase_content_ids_results   =   $this->awsElasticCacheRedis->getHashData($cacheParams);
            }

            if(empty($purchase_content_ids_results)){
                $purchase_content_ids               =   \App\Models\Purchase::where('customer_id', '=', $customer_id)->where('entity', 'contents')->where('artist_id', $artist_id)->lists('entity_id')->toArray();
                $passbook_purchase_content_ids      =   \App\Models\Passbook::where('customer_id', '=', $customer_id)->where('entity', 'contents')->where('artist_id', $artist_id)->lists('entity_id')->toArray();
                $purchase_content_ids_unique        =   array_values(array_unique(array_merge($purchase_content_ids, $passbook_purchase_content_ids)));

                $cacheParams['hash_field_value']    =   $purchase_content_ids_unique;
                $saveToCache                        =   $this->awsElasticCacheRedis->saveHashData($cacheParams);
                $cache_miss                         =   true;
                $purchase_content_ids_results       =   $this->awsElasticCacheRedis->getHashData($cacheParams);
            }
            $results['purchase_content_ids_cache']  =   ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];
            $results['purchase_content_ids']        =   $purchase_content_ids_results;


        }
        return ['error_messages' => $error_message, 'results' => $results];
    }




    public function getMetaIdsV2($postData)
    {
        $error_message  =   [];
        $results        =   [];
        $customer_id    =   $postData['customer_id'];
        $artist_id      =   $postData['artist_id'];
        $metaids        =   [];

        if (!empty($customer_id)) {

            //Manage Purchase Ids
            $cacheParams                    =   [];
            $hash_name                      =   env_cache(Config::get('cache.hash_keys.customer_temp_metaids').$customer_id);
            $hash_field                     =   'purchase_content_ids:'.$artist_id;
            $cache_miss                     =   false;

            $cacheParams['hash_name']       =   $hash_name;
            $cacheParams['hash_field']      =   (string) $hash_field;

            $purchase_content_ids_results   =   $this->awsElasticCacheRedis->getHashData($cacheParams);
            if(empty($purchase_content_ids_results)){
                $purchase_content_ids               =   \App\Models\Purchase::where('customer_id', '=', $customer_id)->where('entity', 'contents')->where('artist_id', $artist_id)->lists('entity_id')->toArray();
                $passbook_purchase_content_ids      =   \App\Models\Passbook::where('customer_id', '=', $customer_id)->where('entity', 'contents')->where('artist_id', $artist_id)->lists('entity_id')->toArray();
                $purchase_content_ids_unique        =   array_values(array_unique(array_merge($purchase_content_ids, $passbook_purchase_content_ids)));

                $cacheParams['hash_field_value']    =   $purchase_content_ids_unique;
                $saveToCache                        =   $this->awsElasticCacheRedis->saveHashData($cacheParams);
                $cache_miss                         =   true;
                $purchase_content_ids_results       =   $this->awsElasticCacheRedis->getHashData($cacheParams);
            }
            $results['purchase_content_ids_cache']  =   ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];
            $results['purchase_content_ids']        =   $purchase_content_ids_results;


            //Manage Like Ids
            $cacheParams                    =   [];
            $hash_name                      =   env_cache(Config::get('cache.hash_keys.customer_temp_metaids').$customer_id);
            $hash_field                     =   'like_content_ids';
            $cache_miss                     =   false;

            $cacheParams['hash_name']       =   $hash_name;
            $cacheParams['hash_field']      =   (string) $hash_field;

            $like_content_ids_results       =   $responses = $this->awsElasticCacheRedis->getHashData($cacheParams);
            if(empty($like_content_ids_results)){
                $like_content_ids                   =   \App\Models\Like::where('customer_id', '=', $customer_id)->where('entity', 'content')->where("status", "active")->where('artist_id', $artist_id)->lists('entity_id')->toArray();
                $like_content_ids_unique            =   array_values(array_unique(array_merge([], $like_content_ids)));

                $cacheParams['hash_field_value']    =   $like_content_ids_unique;
                $saveToCache                        =   $this->awsElasticCacheRedis->saveHashData($cacheParams);
                $cache_miss                         =   true;
                $like_content_ids_results           =   $this->awsElasticCacheRedis->getHashData($cacheParams);
            }
            $results['like_content_ids_cache']  =   ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];
            $results['like_content_ids']        =   $like_content_ids_results;


            //Manage Hot & cold Like Ids
            $like_types = ['hot', 'cold'];
            foreach ($like_types as $key => $like_type) {
                $type_like_ids = $this->getCustomerEntityLikeIds($customer_id, $artist_id, $like_type, 'content');
                if($type_like_ids) {
                    foreach ($type_like_ids as $key => $value) {
                        $results[$key] = $value;
                    }
                }
            }



//----------------------------------------------------------Block Content Ids--------------------------------------------------------------------------------------
            $customerInfo = \App\Models\Customer::where('_id', $customer_id)->where('status', 'active')->first();
            $customerInfo = !empty($customerInfo) ? $customerInfo->toArray() : [];
            $email = $customerInfo['email'];

            $customer_profile_key       = Config::get('cache.keys.customerprofile') . $email;
            $env_customer_profile_key   = env_cache_key($customer_profile_key);

            $customer_key_exists = $this->redisClient->exists($env_customer_profile_key); //Redis Key Exist

            if (!$customer_key_exists) {
                $this->saveCustomerProfile($customer_id, $customerInfo); // Store Data into Redis (Customer Profile)
//                $checkExistanceOfStickers           = \App\Models\Passbook::where('artist_id', $artist_id)->where('customer_id', $customer_id)->where('entity', 'stickers')->first();
            }

            $customer_profile = $this->redisClient->hgetall($env_customer_profile_key); // Get data from Redis (Customer Profile)

            $results['block_content_ids'] = !empty($customer_profile['block_content_ids']) ? explode(",", $customer_profile['block_content_ids']) : [];
            $results['block_comment_ids'] = !empty($customer_profile['block_comment_ids']) ? explode(",", $customer_profile['block_comment_ids']) : [];
//----------------------------------------------------------Block Content Ids--------------------------------------------------------------------------------------

            $results['purchase_stickers'] = !empty($customer_profile['purchase_stickers']) ? true : false;

        }
        return ['error_messages' => $error_message, 'results' => $results];
    }







    public function saveLike($postData)
    {
        $error_messages = [];

        $customer_id = $this->jwtauth->customerFromToken()['_id'];

        $entity = isset($postData['entity']) ? trim($postData['entity']) : 'content';
        $type = isset($postData['type']) ? trim($postData['type']) : 'like';

        $entity_id = isset($postData['entity_id']) ? trim($postData['entity_id']) : '';
        if(empty($entity_id)) {
            $entity_id = isset($postData['content_id']) ? trim($postData['content_id']) : '';
        }
        $artist_id = isset($postData['artist_id']) ? trim($postData['artist_id']) : '';
        $created_at = Carbon::now();


        $likeData = [
            'entity' => $entity,
            'entity_id' => $entity_id,
            'artist_id' => $artist_id,
            'customer_id' => $customer_id,
            'type' => $type,
            'created_at' => $created_at,
        ];

        $env_likes_key_lists = env_cache_key(Config::get('cache.keys.customerentitylikeslisting'));

        $likes_key = Config::get('cache.keys.customerentitylikes') . $customer_id . '_' . $entity_id;
        $env_likes_key_hash = env_cache_key($likes_key); // KEYS for Customer Likes

//        $exist_likes_in_hash = $this->redisClient->exists($env_likes_key_hash);

//        if (!$exist_likes_in_hash) {

        $this->redisClient->hmset($env_likes_key_hash, $likeData);
        $this->redisClient->expire($env_likes_key_hash, 864000); // 10 Days in Seconds
        $exist_key = $this->redisClient->exists($env_likes_key_lists);

        if (!$exist_key) {
            $this->redisClient->rpush($env_likes_key_lists, $env_likes_key_hash);
        } else {
            $this->redisClient->rpushx($env_likes_key_lists, $env_likes_key_hash); //Push values when not exists in keys
        }
//        }

        return ['error_messages' => $error_messages, 'results' => null];
    }


    public function saveComment($postData)
    {
        $error_messages = [];

        $customer_id = $this->jwtauth->customerFromToken()['_id'];
        $content_id = $postData['content_id'];
        $comment = isset($postData['comment']) ? trim($postData['comment']) : '';
        $commented_by = isset($postData['commented_by']) ? trim($postData['commented_by']) : 'customer';
        $type = !empty($postData['type']) ? trim($postData['type']) : 'text';
        $created_at = Carbon::now();

        $commentData = [
            'content_id' => $content_id,
            'customer_id' => $customer_id,
            'comment' => $comment,
            'commented_by' => $commented_by,
            'type' => $type,
            'created_at' => $created_at,
        ];

        $env_comments_key_lists = env_cache_key(Config::get('cache.keys.customercommentslisting'));

        $comments_key = Config::get('cache.keys.customercomments') . $customer_id . '_' . $content_id . '_' . $comment;
        $env_comments_key_hash = env_cache_key($comments_key); // KEYS for Customer Comments

        $this->redisClient->hmset($env_comments_key_hash, $commentData);
        $this->redisClient->expire($env_comments_key_hash, 864000); // 10 Days in Seconds

        $this->redisClient->rpush($env_comments_key_lists, $env_comments_key_hash);

//            $exist_key = $this->redisClient->exists($env_comments_key_lists);
//
//            if (!$exist_key) {
//                $this->redisClient->rpush($env_comments_key_lists, $env_comments_key_hash);
//            } else {
//                $this->redisClient->rpushx($env_comments_key_lists, $env_comments_key_hash); //Push values when not exists in keys
//            }

        return ['error_messages' => $error_messages, 'results' => null];

    }


    public function submitPollResult($request)
    {
        $requestData = $request->all();

        $customer_id = $requestData['cust_id'];
        $content_id = $requestData['content_id'];
        $option_id = $requestData['option_id'];

        $content_expire_check = \App\Models\Content::where('_id', $content_id)->where('status', 'active')->project(['_id' => 0])->first(['expired_at']);
        $content_expire_check = !empty($content_expire_check) ? $content_expire_check->toArray()['expired_at'] : '';

        $expired_at = $content_expire_check;

        $expire = strtotime($expired_at);
        $today = strtotime("today midnight");

        if ($today >= $expire) {
            $result = 'Poll Expired';

//            $contents[$contentKey]['is_expired'] = 'true';
//            $contents[$contentKey]['human_readable_expired_at'] = '';
//            $contents[$contentKey]['human_date_diff_for_expire'] = '';
//                unset($contents);
        } else {

            $env_pollresults_key_lists = env_cache_key(Config::get('cache.keys.pollresultslisting'));

            $pollresults_key = Config::get('cache.keys.pollresults') . $customer_id . '_' . $content_id . '_' . $option_id;
            $env_pollresults_key_hash = env_cache_key($pollresults_key); // KEYS for Poll results

            $exist_key = $this->redisClient->exists($env_pollresults_key_lists);

            if (!$exist_key) {
                $this->redisClient->hmset($env_pollresults_key_hash, $requestData);
                $this->redisClient->expire($env_pollresults_key_hash, 864000); // 10 Days in Seconds
                $this->redisClient->rpush($env_pollresults_key_lists, $env_pollresults_key_hash);
            }
            $result = 'pollresult added succesfully';

//            $contents[$contentKey]['is_expired'] = 'false';
//            $contents[$contentKey]['human_readable_expired_at'] = Carbon\Carbon::parse($expired_at)->format('F j\\, Y H:i');
//            $contents[$contentKey]['human_date_diff_for_expire'] = Carbon\Carbon::parse($expired_at)->diffForHumans();
        }

        return ['error_messages' => null, 'results' => $result];
    }





    public function getBuckectListingFromDb($requestData = array())
    {


        $results = $this->bucketRepObj->lists($requestData);

        return $results;
    }



    public function getBuckectListing($requestData = array())
    {

//        print_pretty($requestData);exit;

        $hash_key        =   (isset($requestData['hash_key']) && $requestData['hash_key'] != '') ? trim($requestData['hash_key']) : "";
        $hash_field      =   (isset($requestData['hash_field']) && $requestData['hash_field'] != '') ? trim($requestData['hash_field']) : "";


        if($hash_key == '' || $hash_field == ''){
            $this->get_from_db = true;
        }

        //if redis connection fine
        if($this->get_from_db != true){

            $bucket_hashkey_exists = $this->redisClient->exists($hash_key); //Redis Key Exist

            if(!$bucket_hashkey_exists){
                $this->get_from_db = true;
            }

            if($this->redisClient->exists($hash_key) && !$this->redisClient->hexists($hash_key, $hash_field)){
                $this->get_from_db = true;
            }

            try {

                $results = $this->redisClient->hgetall($hash_key);

            }catch (Exception $e) {

                $this->get_from_db = true;

                $error_messages = ['error' => true, 'type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                Log::info('RedisDb - getBuckectListing  : Fail ', $error_messages);

            }


        }

        //If hash or field doest not exist
        if($this->get_from_db){

            $results    =   $this->getBuckectListingFromDb($requestData);

            try {

                $this->redisClient->hset($hash_key, $hash_field, $results);
                $this->redisClient->expire($hash_key, $this->content_expire_time);
                $results = $this->redisClient->hgetall($hash_key);

            } catch (Exception $e) {

                $error_messages = ['error' => true, 'type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                Log::info('RedisDb - getBuckectListing  : Fail ', $error_messages);

            }
        }

        return $results;
    }


    public function purgeBucketListing($requestData = array()){


        $error_message = $results = [];


        return ['error_messages' => null, 'results' => $results];
    }





    public function CutomerLogin($requestData = array()){



    }


    public function CutomerWallet($requestData = array()){



    }




    public function CutomerMetaInfo($requestData = array()){



    }


    public function artistContestantRegister($postdata)
    {
        $error_messages = array();
        $status_code    = 201;
        $data           = array_except($postdata->all(), ['password_confirmation']);
        $contestant_id  = null;

        $email          = trim(strtolower($data['email']));
        $identity       = (isset($data['identity']) && $data['identity'] != '') ? trim($data['identity']) : 'email';

        if (empty($data['first_name']) && empty($data['last_name'])) {
            $data['first_name'] = explode("@", $data['email'])[0];
        }

        $contestant = \App\Models\ArtistContestant::where('email', '=', $email)->first();

        if ($identity == 'email') {
            if (!empty($contestant)) {
                $error_messages[] = 'Artist Contestant already register';
                $status_code = 202;
            }
        }

        if (empty($error_messages)) {

            // Contestant Profile Photo
            if ($postdata->hasFile('photo')) {
                $parmas = ['file' => $postdata->file('photo'), 'type' => 'artistcontestant'];
                $photo  = $this->krakenImage->uploadToAws($parmas);
                if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                    array_set($data, 'photo', $photo['results']);
                    array_set($data, 'picture', $photo['results']['cover']);
                }
            }

            if($contestant) {
                $data = array_except($data, ['password_confirmation', 'platform']);
            }
            else {
                $data['status'] = 'registered';
            }

            $data = array_except($data, ['password_confirmation', 'contents']);
            $contestant         = new \App\Models\ArtistContestant($data);
            $contestant_saved   = $contestant->save();

            if($contestant_saved) {
                $contestant = \App\Models\ArtistContestant::where('email', '=', $email)->first();
                if($contestant) {
                    $contestant_id = $contestant['_id'];
                }

                if($contestant_id) {
                    // Save Contestant Paid Content /// Can be Photos or videos
                    if ($postdata->hasFile('contents')) {
                        $contestant_contents = $postdata->file('contents');
                        foreach ($contestant_contents as $key => $content) {
                            $content_data = [];
                            $content_data['contestant_id']   = $contestant_id;

                            $name = $contestant_id . ' photo ' . ($key + 1);
                            // Find Content Type and save content accordingly
                            $content_data['type']    = 'photo';
                            $content_data['name']    = $name;
                            $content_data['caption'] = $name;
                            $content_data['slug']    = '';
                            $content_data['ordering']= ($key + 1);
                            $content_data['coins']   = 10;
                            $content_data['status']  = 'active';

                            switch ($content_data['type']) {
                                case 'photo':
                                    $content_parmas = ['file' => $content, 'type' => 'artistcontestantcontent'];
                                    $content_photo  = $this->krakenImage->uploadToAws($content_parmas);
                                    if(!empty($content_photo) && !empty($content_photo['success']) && $content_photo['success'] === true && !empty($content_photo['results'])){
                                        array_set($content_data, 'photo', $content_photo['results']);
                                        array_set($content_data, 'picture', $content_photo['results']['cover']);
                                    }
                                    break;

                                case 'video':
                                    /*
                                    @@TODO - Video
                                    //upload to local drive
                                    $upload = $request->file('video');
                                    $folder_path = 'uploads/contents/video/';
                                    $obj_path = public_path($folder_path);
                                    $obj_extension = $upload->getClientOriginalExtension();
                                    $imageName = time() . '_' . str_slug($upload->getRealPath()) . '.' . $obj_extension;
                                    $fullpath = $obj_path . $imageName;
                                    $upload->move($obj_path, $imageName);
                                    chmod($fullpath, 0777);

                                    //upload to aws
                                    $object_source_path = $fullpath;
                                    $object_upload_path = $imageName;
                                    $s3 = Storage::disk('s3_armsrawvideos');
                                    $response = $s3->put($object_upload_path, file_get_contents($object_source_path), 'public');

                                    $vod_job_data = ['status' => 'submitted', 'object_name' => $imageName, 'object_path' => $object_upload_path, 'object_extension' => $obj_extension, 'bucket' => 'armsrawvideos'];
                                    array_set($data, 'vod_job_data', $vod_job_data);
                                    array_set($data, 'video_status', 'uploaded');

                                    @unlink($fullpath);
                                    */
                                    break;
                                default:
                                    # code...
                                    break;
                            }

                            $contestant_content = new \App\Models\ArtistContestantContent($content_data);
                            $contestant_media   = $contestant_content->save();
                        }
                    }
                }
            }

            $results['artistcontestant']    = apply_cloudfront_url($contestant);

            /*
            // @@TODO - Send Welcome Mail
            $details_for_send_email['email'] = $email;
            $details_for_send_email['name'] = !empty($customer['first_name']) ? $customer['first_name'] : explode("@", $data['email'])[0];
            $details_for_send_email['password'] = $temp_password;
            $details_for_send_email['celeb_name'] = $celeb_name;
            $details_for_send_email['celeb_photo'] = $celeb_photo;
            $details_for_send_email['celeb_android_app_download_link'] = $celeb_android_app_download_link;
            $details_for_send_email['celeb_ios_app_download_link'] = $celeb_ios_app_download_link;
            $details_for_send_email['celeb_direct_app_download_link'] = $celeb_direct_app_download_link;

            $send_mail = $this->customermailer->forgotPassword($details_for_send_email);
            */
        }

        $results['status_code'] = $status_code;

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Save Entity Like data in Redis
     *
     * @param   array   $request Service Method Request Data
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-27
     */
    public function saveEntityLike($postData)
    {
        $error_messages = [];

        $entity         = isset($postData['entity']) ? trim($postData['entity']) : 'content';
        $entity_id      = isset($postData['entity_id']) ? trim($postData['entity_id']) : '';
        $artist_id      = isset($postData['artist_id']) ? trim($postData['artist_id']) : '';
        $customer_id    = $this->jwtauth->customerFromToken()['_id'];
        $type           = isset($postData['type']) ? trim($postData['type']) : 'normal';
        $created_at     = Carbon::now();

        $likeData = [
            'entity'        => $entity,
            'entity_id'     => $entity_id,
            'artist_id'     => $artist_id,
            'customer_id'   => $customer_id,
            'type'          => $type,
            'created_at'    => $created_at,
        ];

        $env_likes_key_lists= env_cache_key(Config::get('cache.keys.customerentitylikeslisting'));

        $likes_key          = Config::get('cache.keys.customerentitylikes') . $customer_id . '_' . $entity_id;
        $env_likes_key_hash = env_cache_key($likes_key); // KEYS for Customer Entity Likes

        $this->redisClient->hmset($env_likes_key_hash, $likeData);
        $this->redisClient->expire($env_likes_key_hash, 864000); // 10 Days in Seconds

        $exist_key = $this->redisClient->exists($env_likes_key_lists);

        if (!$exist_key) {
            $this->redisClient->rpush($env_likes_key_lists, $env_likes_key_hash);
        }
        else {
            $this->redisClient->rpushx($env_likes_key_lists, $env_likes_key_hash); //Push values when not exists in keys
        }

        return ['error_messages' => $error_messages, 'results' => null];
    }

    /**
     * Entity Entity Like Ids from Database
     *
     * @param   string   $customer_id
     * @param   string   $artist_id
     * @param   string   $like_type     (normal | hot | cold)
     * @param   string   $entity        (content | contestant)
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-28
     */
    public function getCustomerEntityLikeIds($customer_id, $artist_id, $like_type = 'normal', $entity = 'content')
    {
        $results = [];

        $like_type  = strtolower($like_type);
        $prefix     = '';

        switch ($like_type) {
            case 'hot':
                $prefix = 'hot_';
                break;

            case 'cold':
                $prefix = 'cold_';
                break;

            case 'like':
            case 'normal':
            default:
                $prefix = '';
                break;
        }

        $cache_params   = [];
        $hash_name      = env_cache(Config::get('cache.hash_keys.customer_temp_metaids') . $customer_id);
        $hash_field     = $prefix . 'like_content_ids';
        $cache_miss     = false;

        $cache_params['hash_name']   = $hash_name;
        $cache_params['hash_field']  = (string) $hash_field;

        $like_content_ids_results   = $this->awsElasticCacheRedis->getHashData($cache_params);
        if(empty($like_content_ids_results)){
            $artist_ids = [];

            // If Artist Id is BollyFame (5cd5847918a01f2564732022)
            // Then then get all contestant Artists content like data
            if($artist_id == '5cd5847918a01f2564732022') {
                $all_contestant = \App\Models\Cmsuser::where('status', '=', 'active')->where('is_contestant', 'true')->get()->pluck('_id');
                if($all_contestant) {
                    $artist_ids = $all_contestant->toArray();
                }
            }

            $artist_ids[] = $artist_id;
            $like_content_ids       = \App\Models\Like::where('customer_id', '=', $customer_id)->where('entity', 'content')->where("status", "active")->whereIn('artist_id', $artist_ids)->where('type', $like_type)->lists('entity_id')->toArray();
            $like_content_ids_unique= array_values(array_unique(array_merge([], $like_content_ids)));

            $cache_params['hash_field_value']   = $like_content_ids_unique;
            $saveToCache                        = $this->awsElasticCacheRedis->saveHashData($cache_params);
            $cache_miss                         = true;
            $like_content_ids_results           = $this->awsElasticCacheRedis->getHashData($cache_params);
        }

        $results[$prefix . 'like_content_ids_cache']  = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];
        $results[$prefix . 'like_content_ids']        = $like_content_ids_results;

        return $results;
    }

    /**
     * Save Customer Content View
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-1
     */
    public function saveView($postData) {
        $error_messages = [];

        $customer_id    = $this->jwtauth->customerFromToken()['_id'];
        $content_id     = isset($postData['content_id']) ? trim($postData['content_id']) : '';
        $artist_id      = isset($postData['artist_id']) ? trim($postData['artist_id']) : '';
        $created_at     = Carbon::now();

        $save_data = [
            'content_id'    => $content_id,
            'artist_id'     => $artist_id,
            'customer_id'   => $customer_id,
            'created_at'    => $created_at,
        ];

        $env_cache_key_lists = env_cache_key(Config::get('cache.hash_keys.content_view_lists'));

        $save_key  = Config::get('cache.hash_keys.content_view') . $customer_id . '_' . $content_id;
        $env_save_key_hash = env_cache_key($save_key); // KEYS for Customer Content View

        $this->redisClient->hmset($env_save_key_hash, $save_data);
        $this->redisClient->expire($env_save_key_hash, 864000); // 10 Days in Seconds

        $exist_key = $this->redisClient->exists($env_cache_key_lists);

        if (!$exist_key) {
            $this->redisClient->rpush($env_cache_key_lists, $env_save_key_hash);
        }
        else {
            $this->redisClient->rpushx($env_cache_key_lists, $env_save_key_hash); //Push values when not exists in keys
        }

        return ['error_messages' => $error_messages, 'results' => null];
    }


    /**
     * Save Customer Content View
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-1
     */
    public function saveShare($postData) {
        $error_messages = [];

        $customer_id    = $this->jwtauth->customerFromToken()['_id'];
        $content_id     = isset($postData['content_id']) ? trim($postData['content_id']) : '';
        $artist_id      = isset($postData['artist_id']) ? trim($postData['artist_id']) : '';
        $created_at     = Carbon::now();

        $save_data = [
            'content_id'    => $content_id,
            'artist_id'     => $artist_id,
            'customer_id'   => $customer_id,
            'created_at'    => $created_at,
        ];

        $env_cache_key_lists = env_cache_key(Config::get('cache.hash_keys.content_share_lists'));

        $save_key  = Config::get('cache.hash_keys.content_share') . $customer_id . '_' . $content_id;
        $env_save_key_hash = env_cache_key($save_key); // KEYS for Customer Content View

        $this->redisClient->hmset($env_save_key_hash, $save_data);
        $this->redisClient->expire($env_save_key_hash, 864000); // 10 Days in Seconds

        $exist_key = $this->redisClient->exists($env_cache_key_lists);

        if (!$exist_key) {
            $this->redisClient->rpush($env_cache_key_lists, $env_save_key_hash);
        }
        else {
            $this->redisClient->rpushx($env_cache_key_lists, $env_save_key_hash); //Push values when not exists in keys
        }

        return ['error_messages' => $error_messages, 'results' => null];
    }
}
