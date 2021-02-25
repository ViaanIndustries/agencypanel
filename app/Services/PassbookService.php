<?php

namespace App\Services;

use Carbon\Carbon;
use Config, Log;
use App\Repositories\Contracts\PassbookInterface;
use App\Repositories\Contracts\CustomerInterface;
use App\Repositories\Contracts\PackageInterface;
use App\Services\RedisDb;
use App\Services\Cache\AwsElasticCacheRedis;

use App\Services\ArtistService;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

//https://github.com/Paytm-Payments/Paytm_Web_Sample_Kit_PHP/blob/master/PaytmKit/lib/encdec_paytm.php
require_once(public_path("paytm/lib/config_paytm.php"));
require_once(public_path("paytm/lib/encdec_paytm.php"));


class PassbookService
{


    protected $passbookRep;
    protected $customerRep;
    protected $packageRep;
    protected $redisdb;
    protected $awsElasticCacheRedis;
    protected  $artistService;

    public function __construct(
        PassbookInterface $passbookRep,
        CustomerInterface $customerRep,
        PackageInterface $packageRep,
        RedisDb $redisdb,
        AwsElasticCacheRedis $awsElasticCacheRedis,
        ArtistService $artistService
    ){
        $this->passbookRep = $passbookRep;
        $this->customerRep = $customerRep;
        $this->packageRep = $packageRep;
        $this->redisdb = $redisdb;
        $this->awsElasticCacheRedis = $awsElasticCacheRedis;
        $this->artistService = $artistService;

    }


    public function searchListing($request)
    {
        $requestData = $request->all();
        $results     = $this->passbookRep->searchListing($requestData);
        return $results;
    }


    public function convertOrderDataToOrderPassbookData($item)
    {

        $order_id                   =   (!empty($item['_id'])) ? trim($item['_id']) : '';
        $entity_id                  =   (!empty($item['package_id'])) ? trim($item['package_id']) : '';
        $customer_id                =   (!empty($item['customer_id'])) ? trim($item['customer_id']) : '';
        $artist_id                  =   (!empty($item['artist_id'])) ? trim($item['artist_id']) : '';
        $loggedin_user_id           =   (!empty($item['loggedin_user_id'])) ? trim($item['loggedin_user_id']) : '';
        $platform                   =   (!empty($item['platform'])) ? trim($item['platform']) : '';
        $platform_version           =   (!empty($item['platform_version'])) ? trim($item['platform_version']) : '';
        $xp                         =   (!empty($item['package_xp'])) ? intval($item['package_xp']) : 0;
        $coins                      =   (!empty($item['package_coins'])) ? intval($item['package_coins']) : 0;
        $total_coins                =   (!empty($item['package_coins'])) ? intval($item['package_coins']) : 0;
        $amount                     =   (!empty($item['package_price'])) ? floatval($item['package_price']) : 0;
        $quantity                   =   (!empty($item['quantity'])) ? intval($item['quantity']) : 1;
        $coins_before_txn           =   (!empty($item['coins_before_purchase'])) ? intval($item['coins_before_purchase']) : 0;
        $coins_after_txn            =   (!empty($item['coins_after_purchase'])) ? intval($item['coins_after_purchase']) : 0;
        $status                     =   (!empty($item['order_status'])) ? trim($item['order_status']) : '';
        $remark                     =   (!empty($item['remark'])) ? trim($item['remark']) : '';
        $passbook_applied           =   (isset($item['passbook_applied'])) ? $item['passbook_applied'] : false;
        $created_at                 =   (!empty($item['created_at'])) ? $item['created_at'] : '';
        $updated_at                 =   (!empty($item['updated_at'])) ? $item['updated_at'] : '';
        $reference_id               =   (!empty($item['reference_id'])) ? trim($item['reference_id']) : $order_id;

        $txn_meta_info              =   [];


        if($status == 'successful'){
            $status = 'success';
        }


        $txn_meta_info['remark']    = trim($remark);

        if(!empty($item['package_name'])){
            $txn_meta_info['package_name']   = trim($item['package_name']);
        }


        if(!empty($item['package_sku'])){
            $txn_meta_info['package_sku']   = trim($item['package_sku']);
        }

        if(!empty($item['vendor'])){
            $txn_meta_info['vendor']   = trim($item['vendor']);
        }

        if(!empty($item['vendor_order_id'])){
            $txn_meta_info['vendor_txn_id']   = trim($item['vendor_order_id']);
        }

        if(!empty($item['purchase_key'])){
            $txn_meta_info['vendor_txn_token']   = trim($item['purchase_key']);
        }

        if(!empty($item['receipt'])){
            $txn_meta_info['vendor_txn_token']   = trim($item['receipt']);
        }

        if(!empty($item['currency_code'])){
            $txn_meta_info['currency_code']   = trim($item['currency_code']);
        }

        if(!empty($item['transaction_price'])){
            $txn_meta_info['transaction_price']   = floatval($item['transaction_price']);
        }

        if(!empty($item['purchase_payload'])){
            $txn_meta_info['purchase_payload']   = $item['purchase_payload'];
        }

        if(!empty($item['failed_payload'])){
            $txn_meta_info['failed_payload']   = $item['failed_payload'];
        }


        if(!empty($item['pending_retry'])){
            $txn_meta_info['pending_retry']   = $item['pending_retry'];
        }

        //in a case retry by apple pay for duplicate transction cases
        if(!empty($item['parent_order_id'])){
            $txn_meta_info['parent_order_id']   = $item['parent_order_id'];
        }


        if($platform == ''){
            $customerObj = \App\Models\Customer::where('_id', $customer_id)->first();
            if(!empty($customerObj)){
                $platform  = (!empty($customerObj['platforms']) && !in_array('ios', $customerObj['platforms'])) ? 'ios' : 'android';
            }
        }
        if($platform == ''){
            $platform  =  'NOT_FIND';
        }


        $saveData = [
            'entity'                =>      'packages',
            'entity_id'             =>      $entity_id,
            'customer_id'           =>      $customer_id,
            'artist_id'             =>      $artist_id,
            'loggedin_user_id'      =>      $loggedin_user_id,
            'platform'              =>      $platform,
            'platform_version'      =>      $platform_version,
            'xp'                    =>      $xp,
            'coins'                 =>      $coins,
            'total_coins'           =>      $total_coins,
            'quantity'              =>      $quantity,
            'amount'                =>      $amount,
            'total_coins'           =>      $total_coins,
            'coins_before_txn'      =>      $coins_before_txn,
            'coins_after_txn'       =>      $coins_after_txn,
            'txn_type'              =>      'added',
            'status'                =>      $status,
            'txn_meta_info'         =>      $txn_meta_info,
            'created_at'            =>      $created_at,
            'updated_at'            =>      $updated_at,
            'reference_id'          =>      $reference_id,
            'passbook_applied'      =>      $passbook_applied
        ];


        return $saveData;

    }


    public function saveToPassbook($item = []){

        $results                    =   [];
        $error_messages             =   [];

        try {

            $entity                     =   (!empty($item['entity'])) ? trim($item['entity']) : '';
            $entity_id                  =   (!empty($item['entity_id'])) ? trim($item['entity_id']) : '';
            $customer_id                =   (!empty($item['customer_id'])) ? trim($item['customer_id']) : '';
            $artist_id                  =   (!empty($item['artist_id'])) ? trim($item['artist_id']) : '';
            $platform                   =   (!empty($item['platform'])) ? strtolower(trim($item['platform'])) : '';
            $platform_version           =   (!empty($item['platform_version'])) ? trim($item['platform_version']) : '';
            $xp                         =   (!empty($item['xp'])) ? intval($item['xp']) : 0;
            $coins                      =   (!empty($item['coins'])) ? intval($item['coins']) : 0;
            $total_coins                =   (!empty($item['total_coins'])) ? intval($item['total_coins']) : 0;
            $quantity                   =   (!empty($item['quantity'])) ? intval($item['quantity']) : 1;
            $amount                     =   (!empty($item['amount'])) ? floatval($item['amount']) : 0;
            $coins_before_txn           =   (!empty($item['coins_before_txn'])) ? intval($item['coins_before_txn']) : 0;
            $coins_after_txn            =   (!empty($item['coins_after_txn'])) ? intval($item['coins_after_txn']) : 0;
            $txn_type                   =   (!empty($item['txn_type'])) ? trim($item['txn_type']) : 'added';
            $status                     =   (!empty($item['status'])) ? trim($item['status']) : '';
            $remark                     =   (!empty($item['remark'])) ? trim($item['remark']) : '';
            $passbook_applied           =   (isset($item['passbook_applied'])) ? $item['passbook_applied'] : false;
            $txn_meta_info              =   (!empty($item['txn_meta_info'])) ? $item['txn_meta_info'] : [];

            $created_at                 =   (!empty($item['created_at'])) ? $item['created_at'] : '';
            $updated_at                 =   (!empty($item['updated_at'])) ? trim($item['updated_at']) : '';

            $reference_id               =   (!empty($item['reference_id'])) ? trim($item['reference_id']) : 'NOT_EXIST';
            $loggedin_user_id           =   (!empty($item['loggedin_user_id'])) ? trim($item['loggedin_user_id']) : '';

            $customer_activity_id       = (!empty($item['customer_activity_id'])) ? trim($item['customer_activity_id']) : '';
            $referrer_customer_id       = (!empty($item['referrer_customer_id'])) ? trim($item['referrer_customer_id']) : '';
            $referral_customer_id       = (!empty($item['referral_customer_id'])) ? trim($item['referral_customer_id']) : '';

            $saveData = [
                'entity'                =>      $entity,
                'entity_id'             =>      $entity_id,
                'customer_id'           =>      $customer_id,
                'artist_id'             =>      $artist_id,
                'platform'              =>      $platform,
                'platform_version'      =>      $platform_version,
                'xp'                    =>      $xp,
                'coins'                 =>      $coins,
                'total_coins'           =>      $total_coins,
                'quantity'              =>      $quantity,
                'total_coins'           =>      $total_coins,
                'amount'                =>      $amount,
                'coins_before_txn'      =>      $coins_before_txn,
                'coins_after_txn'       =>      $coins_after_txn,
                'txn_type'              =>      $txn_type,
                'status'                =>      $status,
                'txn_meta_info'         =>      $txn_meta_info,
                'reference_id'          =>      $reference_id,
                'passbook_applied'      =>      $passbook_applied
            ];

            if($loggedin_user_id != ''){
                $saveData['loggedin_user_id'] = $loggedin_user_id;
            }

            if($created_at != ''){
                $saveData['created_at'] = $created_at;
            }

            if($updated_at != ''){
                $saveData['updated_at'] = $updated_at;
            }

            if($customer_activity_id) {
                $saveData['customer_activity_id'] = $customer_activity_id;
            }

            if($referrer_customer_id) {
                $saveData['referrer_customer_id'] = $referrer_customer_id;
            }

            if($referral_customer_id) {
                $saveData['referral_customer_id'] = $referral_customer_id;
            }

//            print_pretty($saveData);exit;

            if($reference_id != '' && $reference_id != 'NOT_EXIST'){
                $Exists      =  \App\Models\Passbook::where('entity', $entity)->where('reference_id', $reference_id)->first();
                if($Exists){
                    $passbookUpdate     =   $Exists->where('reference_id', $reference_id)->update($saveData);
                    $passbook           =   $Exists;
                }else{
                    $passbook       =   $this->passbookRep->store($saveData);
                }
            }else{
                $passbook           =   $this->passbookRep->store($saveData);
            }

            $results['passbook']    =   $passbook;

        } catch (\Exception $e) {

            $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), ''];
            \Log::info('saveToPassbook Error - ', $error_messages);
        }


        return ['error_messages' => $error_messages, 'results' => $results];

    }



    public function customerPassbookAdmin($request)
    {


        $error_messages =   [];
        $results        =   [];
        $requestData    =   array_except($request->all(),['artist_id']);
        $results        =   $this->passbookRep->customerPassbook($requestData);
        return ['error_messages' => $error_messages, 'results' => $results];

    }

    public function customerPassbook($request)
    {

        $requestData                =   array_except($request->all(),['artist_id','platform']);
        $error_messages             =   [];
        $results                    =   [];
        $customer_id                =   $request['customer_id'];
        $request['customer_id']     =   $customer_id;
        $page                       =   (isset($request['page']) && $request['page'] != '') ? trim($request['page']) : '1';
        $txn_type                   =   (isset($requestData['txn_type']) && $requestData['txn_type'] != '') ? trim($requestData['txn_type']) : '';

        $cacheParams                =   [];
        $hash_name                  =   env_cache(Config::get('cache.hash_keys.customer_passbook_lists').$customer_id);
        $hash_field                 =   $txn_type . $page;
        $cache_miss                 =   false;

        $cacheParams['hash_name']   =  $hash_name;
        $cacheParams['hash_field']  =  (string)$hash_field;

//        print_pretty($requestData);exit;
        $results = $responses = $this->awsElasticCacheRedis->getHashData($cacheParams);
        if (empty($results)) {
            $responses                          =   $this->passbookRep->customerPassbook($requestData);
            $items                              =   ($responses) ? apply_cloudfront_url($responses) : [];
            $cacheParams['hash_field_value']    =   $items;
            $saveToCache                        =   $this->awsElasticCacheRedis->saveHashData($cacheParams);
            $cache_miss                         =   true;
            $results                            =   $this->awsElasticCacheRedis->getHashData($cacheParams);
        }

        $results['cache']    = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function purchaseContent($request)
    {
        $error_messages     =   [];
        $results            =   [];
        $requestData        =   $request->all();

        $content_id             =   (isset($requestData['content_id']) && $requestData['content_id'] != '') ? trim($requestData['content_id']) : '';
        $selected_content       =   (!empty($requestData['selected_content'])) ? $requestData['selected_content'] : [];
        $customer_id            =   (isset($requestData['customer_id']) && $requestData['customer_id'] != '') ? trim($requestData['customer_id']) : '';
        $artist_id              =   (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? trim($requestData['artist_id']) : '';
        $platform               =   (isset($requestData['platform']) && $requestData['platform'] != '') ? strtolower(trim($requestData['platform'])) : '';
        $platform_version       =   (!empty($requestData['v'])) ? strtolower(trim($requestData['v'])) : '';
        $total_quantity         =   (isset($request['total_quantity'])) ? trim($request['total_quantity']) : 1;
        $coins                  = 0;

        // Find Content Coins
        $content_obj            = \App\Models\Content::where('_id', $content_id)->first();
        if($content_obj) {
            if(isset($content_obj->coins)) {
                $coins  = $content_obj->coins;
            }
            else {
                $error_messages[] = 'Content does not have coins set';
            }
        }
        else {
            $error_messages[] = 'Content does not exists';
        }

        $total_coins            =   $coins * $total_quantity;
        $xp                     =   (!empty($selected_content) && isset($selected_content['xp'])) ? ($selected_content['xp']) : 0;
        $customerObj            =   \App\Models\Customer::where('_id', $customer_id)->first();

        if (empty($customerObj)) {
            $error_messages[] = 'Customer does not exists';
        }

        if ($coins < 1) {
            $error_messages[] = 'Coins cannot be zero';
        }

        if (!isset($customerObj['coins']) || $customerObj['coins'] < $coins) {
            $error_messages[] = 'Not Enough Coins, Add More';
        }

        $purchaseAlreadyExsit = \App\Models\Passbook::where('customer_id', '=', $customer_id)->where('entity', 'contents')->where('entity_id', $content_id)->first();
        if ($purchaseAlreadyExsit) {
            /* NEED TO CHECK AT REDIS LEVEL AND RESPONSE BACK FOR PERFORMACE */
            /*
            $metaids_key        = Config::get('cache.keys.customermetaids') . $customer_id;
            $env_metaids_key    = env_cache_key($metaids_key); // Redis KEYS for Metaids
            $redisClient        = $this->redisdb->PredisConnection();
            $redisClient->hdel($env_metaids_key, ['purchase_content_ids']);
            */

            $error_messages[]   = 'Content already purchase';
            return ['error_messages' => $error_messages, 'results' => $results];
        }

        if (empty($error_messages)) {

            $coins_before_txn   =   (isset($customerObj->coins)) ? $customerObj->coins : 0;
            $coins_after_txn    =   (isset($customerObj->coins)) ? $customerObj->coins - $total_coins : $total_coins;
            if ($coins_after_txn < 0) {
                $coins_after_txn = 0;
            }
            $txn_meta_info      =   [];

            $SaveData = [
                'entity'                =>      'contents',
                'entity_id'             =>      $content_id,
                'customer_id'           =>      $customer_id,
                'loggedin_user_id'      =>      '',
                'artist_id'             =>      $artist_id,
                'platform'              =>      $platform,
                'platform_version'      =>      $platform_version,
                'xp'                    =>      $xp,
                'coins'                 =>      $coins,
                'total_coins'           =>      $total_coins,
                'quantity'              =>      $total_quantity,
                'amount'                =>      0,
                'coins_before_txn'      =>      $coins_before_txn,
                'coins_after_txn'       =>      $coins_after_txn,
                'txn_type'              =>      'paid',
                'status'                =>      'success',
                'txn_meta_info'         =>      $txn_meta_info,
                'reference_id'          =>      'NOT_EXIST',
                'passbook_applied'      =>      true
            ];


//            print_pretty($SaveData);exit;
            $savePassbookResult          =   $this->saveToPassbook($SaveData);
            $purchase_content            =   (!empty($savePassbookResult) && !empty($savePassbookResult['results']) && !empty($savePassbookResult['results']['passbook'])) ? $savePassbookResult['results']['passbook'] : [];


            if(!empty($purchase_content)){

                //UPDATE CUSTOMER CONIS ON SUCCESSFUL PURCHASE
                $customerObj = $this->customerRep->coinsWithdrawal($customer_id, $coins);

                //UPDATE CUSTOMER XP ON SUCCESSFUL PURCHASE
                $customerXpObj = $this->customerRep->xpDeposit($customer_id, $artist_id, $xp);

                //UPDATE CUSTOMER CONIS ON CACHE/REDIS SUCCESSFUL ORDER
                $this->redisdb->saveCustomerCoins($customer_id, $coins_after_txn);

                //########### PURGE CACHE
                $purge_result = $this->awsElasticCacheRedis->purgeCustomerPassbookListsCache(['customer_id' => $customer_id]);


                $purge_meta_ids = $this->awsElasticCacheRedis->purgeCustomerMetaIdsCache(['customer_id' => $customer_id]);


                $purge_account_meta_ids = $this->awsElasticCacheRedis->purgeAccountCustomerMetaIdsCache(['customer_id' => $customer_id]);
                // Purge Account Customer Profile
                $purge_a_c_meta = $this->awsElasticCacheRedis->purgeAccountCustomerProfileCache(['customer_id' => $customer_id]);
            }

            $results['purchase'] = $purchase_content;
        }


        return ['error_messages' => $error_messages, 'results' => $results];
    }




    public function sendGift($request)
    {

        $error_messages         =   [];
        $results                =   [];
        $requestData            =   $request->all();

        $customer_id            =   (isset($requestData['customer_id']) && $requestData['customer_id'] != '') ? trim($requestData['customer_id']) : '';
        $artist_id              =   (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? trim($requestData['artist_id']) : '';
        $platform               =   (isset($requestData['platform']) && $requestData['platform'] != '') ? strtolower(trim($requestData['platform'])) : '';
        $platform_version       =   (!empty($requestData['v'])) ? strtolower(trim($requestData['v'])) : '';
        $total_quantity         =   (isset($request['total_quantity'])) ? trim($request['total_quantity']) : 1;
        $gift_id                =   (isset($requestData['gift_id']) && $requestData['gift_id'] != '') ? trim($requestData['gift_id']) : '';
        $selected_gift          =   (!empty($requestData['selected_gift'])) ? $requestData['selected_gift'] : [];
        $coins                  =   ($selected_gift && isset($selected_gift['coins'])) ? intval($selected_gift['coins']) : 0;
        $total_coins            =   $coins * $total_quantity;
        $xp                     =   (!empty($selected_gift) && isset($selected_gift['xp'])) ? ($selected_gift['xp']) : 0;
        $gift_type              =   ($selected_gift && isset($selected_gift['type'])) ? strtolower(trim($selected_gift['type'])) : "";


        $customerObj            =   \App\Models\Customer::where('_id', $customer_id)->first();

        if (empty($customerObj)) {
            $error_messages[] = 'Customer does not exists';
        }

        if ($total_quantity < 1) {
            $error_messages[] = 'Total quantity cannot be zero';
        }

        if (!isset($customerObj['coins']) || $customerObj['coins'] < $total_coins) {
            $error_messages[] = 'Not Enough Coins, Add More';
        }


        if (empty($error_messages) && $gift_type == 'paid') {

            $coins_before_txn   =   (isset($customerObj->coins)) ? $customerObj->coins : 0;
            $coins_after_txn    =   (isset($customerObj->coins)) ? $customerObj->coins - $total_coins : $total_coins;
            if ($coins_after_txn < 0) {
                $coins_after_txn = 0;
            }
            $txn_meta_info      =   [];

            $SaveData = [
                'entity'                =>      'gifts',
                'entity_id'             =>      $gift_id,
                'customer_id'           =>      $customer_id,
                'loggedin_user_id'      =>      '',
                'artist_id'             =>      $artist_id,
                'platform'              =>      $platform,
                'platform_version'      =>      $platform_version,
                'xp'                    =>      $xp,
                'coins'                 =>      $coins,
                'total_coins'           =>      $total_coins,
                'quantity'              =>      $total_quantity,
                'amount'                =>      0,
                'coins_before_txn'      =>      $coins_before_txn,
                'coins_after_txn'       =>      $coins_after_txn,
                'txn_type'              =>      'paid',
                'status'                =>      'success',
                'txn_meta_info'         =>      $txn_meta_info,
                'reference_id'          =>      'NOT_EXIST',
                'passbook_applied'      =>      true
            ];

//            print_pretty($SaveData);exit;
            $savePassbookResult       =   $this->saveToPassbook($SaveData);
            $purchase_gift            =   (!empty($savePassbookResult) && !empty($savePassbookResult['results']) && !empty($savePassbookResult['results']['passbook'])) ? $savePassbookResult['results']['passbook'] : [];

            if(!empty($purchase_gift)){

                //UPDATE CUSTOMER CONIS ON SUCCESSFUL PURCHASE
                $customerObj = $this->customerRep->coinsWithdrawal($customer_id, $coins);

                //UPDATE CUSTOMER XP ON SUCCESSFUL PURCHASE
                $customerXpObj = $this->customerRep->xpDeposit($customer_id, $artist_id, $xp);

                //UPDATE CUSTOMER CONIS ON CACHE/REDIS SUCCESSFUL ORDER
                $this->redisdb->saveCustomerCoins($customer_id, $coins_after_txn);

                //########### PURGE CACHE
                $purge_result = $this->awsElasticCacheRedis->purgeCustomerPassbookListsCache(['customer_id' => $customer_id]);

            }

            $results['purchase'] = $purchase_gift;
        }


        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function puchaseStickers($request)
    {

        $error_messages         =   [];
        $results                =   [];
        $requestData            =   $request->all();

        $customer_id            =   (isset($requestData['customer_id']) && $requestData['customer_id'] != '') ? trim($requestData['customer_id']) : '';
        $artist_id              =   (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? trim($requestData['artist_id']) : '';
        $platform               =   (isset($requestData['platform']) && $requestData['platform'] != '') ? strtolower(trim($requestData['platform'])) : '';
        $platform_version       =   (!empty($requestData['v'])) ? strtolower(trim($requestData['v'])) : '';
        $total_quantity         =   (isset($request['total_quantity'])) ? trim($request['total_quantity']) : 1;
        $coins                  =   intval(Config::get('app.stickers_price'));
        $total_coins            =   $coins * $total_quantity;
        $xp                     =   0;

        $customerObj            =   \App\Models\Customer::where('_id', $customer_id)->first();

        if (empty($customerObj)) {
            $error_messages[] = 'Customer does not exists';
        }

        if ($total_quantity < 1) {
            $error_messages[] = 'Total quantity cannot be zero';
        }

        if (!isset($customerObj['coins']) || $customerObj['coins'] < $total_coins) {
            $error_messages[] = 'Not Enough Coins, Add More';
        }


        $checkExistanceOfStickers = \App\Models\Passbook::where('artist_id', $artist_id)->where('customer_id', $customer_id)->where('entity', 'stickers')->first();
        if ($checkExistanceOfStickers) {
            $error_messages[]   = 'Stickers already Available against this customer with artist';
            return ['error_messages' => $error_messages, 'results' => $results];
        }


        if (empty($error_messages)) {

            $coins_before_txn   =   (isset($customerObj->coins)) ? $customerObj->coins : 0;
            $coins_after_txn    =   (isset($customerObj->coins)) ? $customerObj->coins - $total_coins : $total_coins;
            if ($coins_after_txn < 0) {
                $coins_after_txn = 0;
            }
            $txn_meta_info      =   [];

            $SaveData = [
                'entity'                =>      'stickers',
                'entity_id'             =>      '',
                'customer_id'           =>      $customer_id,
                'loggedin_user_id'      =>      '',
                'artist_id'             =>      $artist_id,
                'platform'              =>      $platform,
                'platform_version'      =>      $platform_version,
                'xp'                    =>      $xp,
                'coins'                 =>      $coins,
                'total_coins'           =>      $total_coins,
                'quantity'              =>      $total_quantity,
                'amount'                =>      0,
                'coins_before_txn'      =>      $coins_before_txn,
                'coins_after_txn'       =>      $coins_after_txn,
                'txn_type'              =>      'paid',
                'status'                =>      'success',
                'txn_meta_info'         =>      $txn_meta_info,
                'reference_id'          =>      'NOT_EXIST',
                'passbook_applied'      =>      true
            ];

//            print_pretty($SaveData);exit;
            $savePassbookResult       =   $this->saveToPassbook($SaveData);
            $purchase_gift            =   (!empty($savePassbookResult) && !empty($savePassbookResult['results']) && !empty($savePassbookResult['results']['passbook'])) ? $savePassbookResult['results']['passbook'] : [];

            if(!empty($purchase_gift)){

                //UPDATE CUSTOMER CONIS ON SUCCESSFUL PURCHASE
                $customerObj = $this->customerRep->coinsWithdrawal($customer_id, $coins);

                //UPDATE CUSTOMER XP ON SUCCESSFUL PURCHASE
                $customerXpObj = $this->customerRep->xpDeposit($customer_id, $artist_id, $xp);

                //UPDATE CUSTOMER CONIS ON CACHE/REDIS SUCCESSFUL ORDER
                $this->redisdb->saveCustomerCoins($customer_id, $coins_after_txn);

                //########### PURGE CACHE
                $purge_result = $this->awsElasticCacheRedis->purgeCustomerPassbookListsCache(['customer_id' => $customer_id]);

            }

            $results['purchase'] = $purchase_gift;
        }


        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Saves Live Event Purchases in Passbook
     *
     * @param   array       $request
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function purchaselive($request) {
        $error_messages     =   [];
        $results            =   [];
        $requestData        =   $request->all();
        $entity_id              =   (isset($requestData['entity_id']) && $requestData['entity_id'] != '') ? trim($requestData['entity_id']) : '';
        $customer_id            =   (isset($requestData['customer_id']) && $requestData['customer_id'] != '') ? trim($requestData['customer_id']) : '';
        $artist_id              =   (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? trim($requestData['artist_id']) : '';
        $platform               =   (isset($requestData['platform']) && $requestData['platform'] != '') ? strtolower(trim($requestData['platform'])) : '';
        $platform_version       =   (!empty($requestData['v'])) ? strtolower(trim($requestData['v'])) : '';
        $total_quantity         =   (isset($request['total_quantity'])) ? trim($request['total_quantity']) : 1;
        $coins                  =   (isset($requestData['coins'])) ? intval($requestData['coins']) : 0;
        $total_coins            =   $coins * $total_quantity;
        $xp                     =   0;

        $customerObj            =   \App\Models\Customer::where('_id', $customer_id)->first();

        if (empty($customerObj)) {
            $error_messages[] = 'Customer does not exists';
        }

        if ($coins < 1) {
            $error_messages[] = 'Coins cannot be zero';
        }

        if (!isset($customerObj['coins']) || $customerObj['coins'] < $coins) {
            $error_messages[] = 'Not Enough Coins, Add More';
        }

        $purchaseAlreadyExsit = \App\Models\Passbook::where('customer_id', '=', $customer_id)->where('entity', 'lives')->where('entity_id', $entity_id)->first();
        if ($purchaseAlreadyExsit) {
            /* NEED TO CHECK AT REDIS LEVEL AND RESPONSE BACK FOR PERFORMACE */
            /*
            $metaids_key        = Config::get('cache.keys.customermetaids') . $customer_id;
            $env_metaids_key    = env_cache_key($metaids_key); // Redis KEYS for Metaids
            $redisClient        = $this->redisdb->PredisConnection();
            $redisClient->hdel($env_metaids_key, ['purchase_content_ids']);
            */

            $error_messages[]   = 'Live Event already purchase';
            return ['error_messages' => $error_messages, 'results' => $results];
        }

        if (empty($error_messages)) {

            $coins_before_txn   =   (isset($customerObj->coins)) ? $customerObj->coins : 0;
            $coins_after_txn    =   (isset($customerObj->coins)) ? $customerObj->coins - $total_coins : $total_coins;
            if ($coins_after_txn < 0) {
                $coins_after_txn = 0;
            }
            $txn_meta_info      =   [];

            $SaveData = [
                'entity'                =>  'lives',
                'entity_id'             =>  $entity_id,
                'customer_id'           =>  $customer_id,
                'loggedin_user_id'      =>  '',
                'artist_id'             =>  $artist_id,
                'platform'              =>  $platform,
                'platform_version'      =>  $platform_version,
                'xp'                    =>  $xp,
                'coins'                 =>  $coins,
                'total_coins'           =>  $total_coins,
                'quantity'              =>  $total_quantity,
                'amount'                =>  0,
                'coins_before_txn'      =>  $coins_before_txn,
                'coins_after_txn'       =>  $coins_after_txn,
                'txn_type'              =>  'paid',
                'status'                =>  'success',
                'txn_meta_info'         =>  $txn_meta_info,
                'reference_id'          =>  'NOT_EXIST',
                'passbook_applied'      =>  true
            ];

            $savePassbookResult          = $this->saveToPassbook($SaveData);
            $purchase_content            = (!empty($savePassbookResult) && !empty($savePassbookResult['results']) && !empty($savePassbookResult['results']['passbook'])) ? $savePassbookResult['results']['passbook'] : [];


            if(!empty($purchase_content)){

                //UPDATE CUSTOMER CONIS ON SUCCESSFUL PURCHASE
                $customerObj = $this->customerRep->coinsWithdrawal($customer_id, $coins);

                //UPDATE CUSTOMER XP ON SUCCESSFUL PURCHASE
                $customerXpObj = $this->customerRep->xpDeposit($customer_id, $artist_id, $xp);

                //UPDATE CUSTOMER CONIS ON CACHE/REDIS SUCCESSFUL ORDER
                $this->redisdb->saveCustomerCoins($customer_id, $coins_after_txn);

                //########### PURGE CACHE
                $purge_result = $this->awsElasticCacheRedis->purgeCustomerPassbookListsCache(['customer_id' => $customer_id]);


                $metaids_key = Config::get('cache.keys.customermetaids') . $customer_id;
                $env_metaids_key = env_cache_key($metaids_key); // Redis KEYS for Metaids

                $redisClient = $this->redisdb->PredisConnection();
                $redisClient->hdel($env_metaids_key, ['purchase_live_ids']);


                // Purge Account Customer Profile
                $purge_a_c_meta = $this->awsElasticCacheRedis->purgeAccountCustomerProfileCache(['customer_id' => $customer_id]);
            }

            $results['purchase'] = $purchase_content;
        }


        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Save/Capture Order in passbook
     * Copied method from Order Service -> passbookCaptureOrder()
     *
     * @param   \Illuminate\Http\Request
     *
     * @return  array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-10-01
     */
    public function captureOrder($requestData){
        $error_messages = [];
        $results        = [];

        $customer_id        = (isset($requestData['customer_id']) && $requestData['customer_id'] != '') ? trim($requestData['customer_id']) : '';
        $artist_id          = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? trim($requestData['artist_id']) : '';
        $platform           = (isset($requestData['platform']) && $requestData['platform'] != '') ? strtolower(trim($requestData['platform'])) : '';
        $platform_version   = (!empty($requestData['v'])) ? strtolower(trim($requestData['v'])) : '';
        $vendor             = (isset($requestData['vendor']) && $requestData['vendor'] != '') ? trim($requestData['vendor']) : '';
        $transaction_price  = (isset($requestData['transaction_price']) && $requestData['transaction_price'] != '') ? float_value($requestData['transaction_price']) : float_value(0);
        $package_id         = (isset($requestData['package_id']) && $requestData['package_id'] != '') ? trim($requestData['package_id']) : '';
        $selected_package   = (!empty($requestData['selected_package'])) ? $requestData['selected_package'] : [];
        $currency_code      = (isset($requestData['currency_code']) && $requestData['currency_code'] != '') ? trim($requestData['currency_code']) : '';
        $vendor_order_id    = (isset($requestData['vendor_order_id']) && $requestData['vendor_order_id'] != '') ? trim($requestData['vendor_order_id']) : '';
        $purchase_payload   = (isset($requestData['purchase_payload']) && $requestData['purchase_payload'] != '') ? $requestData['purchase_payload'] : '';
        $package_sku        = (isset($requestData['package_sku']) && $requestData['package_sku'] != '') ? trim($requestData['package_sku']) : '';
        $product_name       = (isset($requestData['product_name']) && $requestData['product_name'] != '') ? trim($requestData['product_name']) : '';
        $valid_transaction  = (isset($requestData['purchase_payload']) && isset($requestData['purchase_payload']['valid_transaction']) && $requestData['purchase_payload']['valid_transaction'] != '') ? intval($requestData['purchase_payload']['valid_transaction']) : 0;
        $service_account    = (isset($requestData['ser_acc']) && $requestData['ser_acc'] != '') ? trim($requestData['ser_acc']) : '';
        $receipt            = (isset($requestData['receipt']) && $requestData['receipt'] != '') ? trim($requestData['receipt']) : '';
        $purchase_key       = (isset($requestData['purchase_key']) && $requestData['purchase_key'] != '') ? trim($requestData['purchase_key']) : '';
        $failed_payload     = (!empty($requestData['failed_payload'])) ? $requestData['failed_payload'] : [];
        $pending_retry      = (!empty($requestData['pending_retry']) && $requestData['pending_retry'] == '1') ? '1' : '';
        $parent_order_id    = '';
        $referral_domain    = (!empty($requestData['referral_domain']) && $requestData['referral_domain']) ? trim($requestData['referral_domain']) : '';

        if ($vendor == 'apple_wallet') {
            $purchase_key = $receipt;
        }

        if ($vendor == 'paytm') {
            $purchase_key = $vendor_order_id;
        }

        if ($vendor == 'offiline') {
            $purchase_key = $vendor_order_id;
        }

        $orderData = [
            'customer_id'       => $customer_id,
            'artist_id'         => $artist_id,
            'platform'          => $platform,
            'platform_version'  => $platform_version,
            'vendor'            => $vendor,
            'transaction_price' => $transaction_price,
            'currency_code'     => $currency_code,
            'vendor_order_id'   => $vendor_order_id,
            'package_sku'       => $package_sku,
            'product_name'      => $product_name,
            'purchase_key'      => $purchase_key
        ];

        if($pending_retry != '') {
            $orderData['pending_retry'] = $pending_retry;
        }

        if($referral_domain != '') {
            $orderData['referral_domain'] = $referral_domain;
        }

        if ($valid_transaction == 1) {
            $orderAlreadyExist = \App\Models\Passbook::where("txn_meta_info.vendor", $vendor)->where("txn_meta_info.vendor_txn_token", $purchase_key)->first();

            if ($orderAlreadyExist) {
                $error_messages[] = 'Order already exists';
                return ['error_messages' => $error_messages, 'results' => $results];
            }

            //CHECK VENDOR ORDER ID UNIQUNESS SINCE GOOGLE PURCHASE TOKEN OR APPLE RECIPT CAN BE UNIQUE BUT VENDOR ORDER ID MIGHT ALREADY EXIST
            $vendorOrderIdAlreadyExist = \App\Models\Passbook::where("txn_meta_info.vendor", $vendor)->where("txn_meta_info.vendor_txn_id", trim($vendor_order_id))->first();
            if($vendorOrderIdAlreadyExist) {
                $parent_order_id = (isset($vendorOrderIdAlreadyExist['_id'])) ? trim($vendorOrderIdAlreadyExist['_id']) : 'NOT_FIND';
            }
        }

        if($valid_transaction == 1) {
            $order_status = ($parent_order_id == '') ? 'success' : 'duplicate';
        }
        else{
            $order_status = 'failed';
        }

        if(!empty($selected_package) && empty($selected_package['_id'])) {
            $selected_package = $selected_package;
        }
        else
        {
            $selected_package = $this->packageRep->find($package_id);
        }

        $selected_packageSku    = (!empty($selected_package) && isset($selected_package['sku'])) ? ($selected_package['sku']) : 0;
        $selected_packagePrice  = (!empty($selected_package) && isset($selected_package['price'])) ? float_value($selected_package['price']) : 0;
        $selected_packageCoins  = (!empty($selected_package) && isset($selected_package['coins'])) ? ($selected_package['coins']) : 0;
        $selected_packageXp     = (!empty($selected_package) && isset($selected_package['xp'])) ? ($selected_package['xp']) : 0;

        if(!empty($failed_payload)) {
            $orderData['failed_payload'] = $failed_payload;
        }

        if ($parent_order_id != '') {
            $orderData['parent_order_id'] = $parent_order_id;
        }

        if (empty($selected_package)) {
            $error_messages[] = 'Wrong Package';
        }

        $customerObj = \App\Models\Customer::where('_id', $customer_id)->first();

        if($order_status == 'success'){
            $coins_before_purchase  = (isset($customerObj->coins)) ? $customerObj->coins : 0;
            $coins_after_purchase   = (isset($customerObj->coins)) ? $customerObj->coins + $selected_packageCoins : $selected_packageCoins;
        }
        else{
            $coins_before_purchase  = (isset($customerObj->coins)) ? $customerObj->coins : 0;
            $coins_after_purchase   = $coins_before_purchase;
        }

        if(!empty($service_account)) {
            $orderData['service_account'] = $service_account;
        }

        $orderData['package_id']            = $package_id;
        $orderData['package_sku']           = $package_sku;
        $orderData['package_price']         = $selected_packagePrice;
        $orderData['package_coins']         = $selected_packageCoins;
        $orderData['package_xp']            = $selected_packageXp;
        $orderData['order_status']          = $order_status;
        $orderData['coins_before_purchase'] = $coins_before_purchase;
        $orderData['coins_after_purchase']  = $coins_after_purchase;
        $orderData['purchase_payload']      = $purchase_payload;
        $orderData['artist_id']             = $artist_id;
        $orderData['reference_id']          = 'NOT_EXIST';
        $orderData['passbook_applied']      = true;

        if (empty($error_messages)) {
            $passbookSaveData               = $this->convertOrderDataToOrderPassbookData($orderData);
            $savePassbookResult             = $this->saveToPassbook($passbookSaveData);
            $purchase_package               = (!empty($savePassbookResult) && !empty($savePassbookResult['results']) && !empty($savePassbookResult['results']['passbook'])) ? $savePassbookResult['results']['passbook'] : [];
            $results['order']               = $purchase_package;
            $results['valid_transaction']   = $valid_transaction;
            $results['available_coins']     = $coins_after_purchase;

            if ($order_status == 'success') {

                //UPDATE CUSTOMER CONIS ON SUCCESSFUL ORDER
                $customerObj = $this->customerRep->coinsDeposit($customer_id, $selected_packageCoins);

                //UPDATE CUSTOMER XP ON SUCCESSFUL ORDER
                $customerXpObj = $this->customerRep->xpDeposit($customer_id, $artist_id, $selected_packageXp);

                //UPDATE CUSTOMER CONIS ON CACHE/REDIS SUCCESSFUL ORDER
                $this->redisdb->saveCustomerCoins($customer_id, $coins_after_purchase);


                //########### PURGE CACHE
                $purge_result = $this->awsElasticCacheRedis->purgeCustomerPassbookListsCache(['customer_id' => $customer_id]);


                $non_emailer_artist = ['598aa3d2af21a2355d686de2'];

                //########### EMAIL PROCCES ON SUCCESSFUL ORDER SART
                if(!empty($purchase_package) && !empty($purchase_package['_id']) && !in_array($artist_id, $non_emailer_artist)) {
                    $order_id   = $purchase_package['_id'];
                    $order_info = \App\Models\Passbook::with('package', 'customer', 'artist')->where('_id', $order_id)->first();
                    $order_info = $order_info ? $order_info->toArray() : [];

                    // New Code to send email
                    $celebname      = '';
                    $customer_name  = generate_fullname($order_info['customer']);
                    $customer_email = strtolower(trim($order_info['customer']['email']));

                    // If Customer Email Exits Then Send Email Notification
                    if($customer_email) {
                        $payload = [];
                        // Get Email Default Template Data
                        if($artist_id) {
                            $payload = $this->artistService->getEmailTemplateDefaultData($artist_id);
                            if($payload) {
                                $celebname = isset($payload['celeb_name']) ? $payload['celeb_name'] : '';
                            }
                        }

                        $subject_line   = "Your BOLLYFAME order id $order_id for $celebname has been completed";
                        if(env('PRODUCT') && (trim(strtolower(env('PRODUCT'))) == 'hsw')) {
                            $subject_line   = "Your BollyFame order id $order_id has been completed";
                        }


                        // Generate Email Template specific data
                        $payload['customer_email']      = $customer_email;
                        $payload['customer_name']       = $customer_name;
                        $payload['customer_coins']      = @$coins_after_purchase;

                        $payload['package_name']        = $order_info['package']['name'];
                        $payload['transaction_id']      = $order_info['txn_meta_info']['vendor_txn_id'];
                        $payload['currency_code']       = $order_info['txn_meta_info']['currency_code'];
                        $payload['vendor']              = $order_info['txn_meta_info']['vendor'];
                        $payload['transaction_price']   = $order_info['txn_meta_info']['transaction_price'];
                        $payload['transaction_date']    = Carbon::parse($order_info['created_at'])->format('M j\\, Y h:i A');

                        $payload['email_header_template']   = 'emails.' . env('PRODUCT') . '.common.header';
                        $payload['email_body_template']     = 'emails.' . env('PRODUCT') . '.customer.customerorder';
                        $payload['email_footer_template']   = 'emails.' . env('PRODUCT') . '.common.footer';
                        $payload['email_subject']           =  $subject_line;
                        $payload['user_email']              =  $customer_email;
                        $payload['user_name']               =  $customer_name;
                        $payload['bcc_emailids']            =  Config::get('product.' . env('PRODUCT') . '.mail.bcc_for_transaction');
                        $payload['send_from']               =  Config::get('product.' . env('PRODUCT') . '.mail.from_for_transaction');

                        $jobData = [
                            'label'     => 'CustomerOrderConfirm',
                            'type'      => 'process_email',
                            'payload'   => $payload,
                            'status'    => 'scheduled',
                            'delay'     => 0,
                            'retries'   => 0
                        ];

                        $recodset = new \App\Models\Job($jobData);
                        $recodset->save();
                    }
                }
                //########### EMAIL PROCCES FOR SUCCESSFUL ORDER SART

            }
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Save/Capture Offline Order
     *
     * @param   \Illuminate\Http\Request
     *
     * @return  array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-10-01
     */
    public function captureOfflineOrder($request) {
        $error_messages = [];
        $results        = [];
        $data           = $request->all();
        $passbook_data  = [];
        $passbook       = null;
        $package        = null;
        $customer       = null;
        $customer_id    = '';
        $artist_id      = isset($data['artist_id']) ? trim($data['artist_id']) : '';
        $package_id     = isset($data['package_id']) ? trim($data['package_id']) : '';
        $customer_mobile_country_code = isset($data['user_country_code']) ? intval(trim($data['user_country_code'])) : 91;
        $customer_mobile    = isset($data['user_mobile']) ? trim($data['user_mobile']) : '';

        $entity             = 'packages';
        $entity_id          = $package_id;
        $transaction_price  = isset($data['transaction_price']) ?  trim($data['transaction_price']) : '';
        $platform           = isset($data['platform']) ? $data['platform'] : '';
        $platform_version   = isset($data['v']) ? $data['v'] : '';
        $currency_code      = isset($data['currency_code']) ? $data['currency_code'] : 'INR';
        $retailer_id        = isset($data['retailer_id']) ? $data['retailer_id'] : '';
        $retailer_mobile    = isset($data['retailer_mobile']) ? $data['retailer_mobile'] : '';
        $retailer_state     = isset($data['retailer_state']) ? $data['retailer_state'] : '';
        $retailer_zone      = isset($data['retailer_zone']) ? $data['retailer_zone'] : '';
        $receiver_role      = isset($data['receiver_role']) ? $data['receiver_role'] : '';
        $vendor_order_id    = isset($data['vendor_order_id']) ? $data['vendor_order_id'] : '';
        $vendor             = isset($data['vendor']) ? $data['vendor'] : '';
        $type               = isset($data['type']) ? $data['type'] : '';

        $purchase_payload   = $data;
        $valid_transaction  = 0;

        $purchase_payload['currency_code'] = $currency_code;
        $purchase_payload['vendor_txn_id'] = $vendor_order_id;


        try {

            // Validate Offline Transcation
            $transction_data = [];
            $transction_data['package_id']      = $package_id;
            $transction_data['vendor_order_id'] = $vendor_order_id;
            $transction = $this->vadidateOfflinePurchaseStatus($transction_data);

            if($transction) {
                if(isset($transction['results']) && $transction['results']['valid_transaction']) {
                    $valid_transaction = $transction['results']['valid_transaction'];
                }

                if(isset($transction['error_messages']) && !empty($transction['error_messages'])) {
                    $purchase_payload['valid_transaction_error'] = $transction['error_messages'];
                }
            }

            $purchase_payload['valid_transaction'] = $valid_transaction;

            if($package_id) {
                // Check whether Package exists or not
                $package = $this->packageRep->find($package_id);
                if(!$package) {
                    $error_messages[] = 'Package Id is invalid';
                }

                // Find Customer By Mobile Number
                if($customer_mobile_country_code && $customer_mobile) {

                    $customer = \App\Models\Customer::where('mobile_country_code', intval($customer_mobile_country_code))->where('mobile', $customer_mobile)->first();
                    if($customer) {
                        $customer_id = $customer->_id;
                    }
                    else {
                        // Register Customer in system
                        $customer_data = [];
                        $customer_data['identity']              = 'email';
                        $customer_data['email']                 = '';
                        $customer_data['first_name']            = $customer_mobile;
                        $customer_data['last_name']             = '';
                        $customer_data['mobile_country_code']   = $customer_mobile_country_code;
                        $customer_data['mobile']                = $customer_mobile;
                        $customer_data['mobile_verified']       = 'true';
                        $customer_data['status']                = 'active';
                        $customer_data['email_verified']        = 'false';
                        $customer_data['email_otp']             = rand(100000, 999999);
                        $customer_data['email_otp_generated_at']= Carbon::now();

                        $customer = \App\Models\Customer::create($customer_data);
                        if($customer) {
                            $customer_id = $customer->_id;
                            //$this->accountService->syncCustomerArtist($customer_id, $artist_id);
                        }
                    }
                }

                // Save Customer Offline Order in passbook
                $passbook_data = [
                    'entity'            => $entity,
                    'entity_id'         => $entity_id,
                    'customer_id'       => $customer_id,
                    'artist_id'         => $artist_id,
                    'package_id'        => $package_id,
                    'platform'          => $platform,
                    'platform_version'  => $platform_version,
                    'vendor_order_id'   => $vendor_order_id,
                    'purchase_payload'  => $purchase_payload,
                    'vendor'            => $vendor,
                    'type'              => $type,
                    'currency_code'     => $currency_code,
                    'transaction_price' => $transaction_price,
                ];

                $capture_order  = $this->captureOrder($passbook_data);

                if($capture_order && isset($capture_order['results']) && !empty($capture_order['results']['order'])) {

                    $passbook_order = $capture_order['results']['order'];
                    if($passbook_order) {
                        $results['transId']                 = $passbook_order['_id'];
                        $results['transaction_status']      = $passbook_order['status'];
                    }
                }

                if($capture_order && isset($capture_order['error_messages']) && !empty($capture_order['error_messages'])) {
                    $error_messages = $capture_order['error_messages'];
                }
            }
            else {
                $error_messages[] = 'Package Id is required';
            }
        }
        catch (Exception $e) {

        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Validate Offline purchase status
     *
     *
     * @param   requestData
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-10-04
     */
    public function vadidateOfflinePurchaseStatus($requestData) {
        $error_messages = [];
        $results        = [];
        $message        = '';
        $response_results= [];
        $offline_response= [];

        $env                = (isset($requestData['env'])) ? strtolower(trim($requestData['env'])) : 'test';
        $package_id         = (isset($requestData['package_id']) && $requestData['package_id'] != '') ? trim($requestData['package_id']) : '';
        $vendor_order_id    = (isset($requestData['vendor_order_id']) && $requestData['vendor_order_id'] != '') ? trim($requestData['vendor_order_id']) : '';

        $selected_package = $this->packageRep->find($package_id);

        if (!$selected_package) {
            $results['valid_transaction'] = 0;
            $results['message'] = 'Package does not exist';
            return ['error_messages' => $error_messages, 'results' => $results];
        }

        try {
            $results['valid_transaction'] = 0;

            $base_uri = '';
            if ($env == 'prod') {
                $base_uri = 'https://agentapi.lagaoboli.com';
            }
            else {
                $base_uri = 'https://agentapi.lagaoboli.com';
            }

            $resource   = 'getTransactionById?transactionId=' . $vendor_order_id;
            $url        = $base_uri . '/' . $resource;

            $headers = [
                // All POST requests arguments must be passed as json with the Content-Type set as application/json.
                'Content-Type' => 'application/json',
            ];

            //
            // Get captured payment details
            $http_client = new Client([
                                        'base_uri'  => $base_uri,
                                        'headers'   => $headers,
                                        ]);


            try {
                $request = $http_client->request('GET', $resource, []);
                $response = $request->getBody()->getContents();
                $offline_response = [
                    'status' => $request->getStatusCode(),
                    'reason' => $request->getReasonPhrase(),
                    'results' => json_decode($response, true),
                ];

            }
            catch (RequestException $e) {
                $error = $e->getResponse();
                $ret_status = 400;
                $ret_reason = 'RequestException error';
                if ($error) {
                    $ret_status = $error->getStatusCode();
                    $ret_reason = $error->getReasonPhrase();
                }
                $offline_response = [
                    'status' => $ret_status,
                    'reason' => $ret_reason
                ];
            }
            catch (Exception $e) {
                $message = array(
                    'type' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                );
                $offline_response = [
                    'status' => 400,
                    'reason' => $message['type'] . ' : ' . $message['message'] . ' in ' . $message['file'] . ' on ' . $message['line']
                ];
            }

            if($offline_response) {

                if(isset($offline_response['status']) && $offline_response['status'] == 200) {
                    if(isset($offline_response['results'])) {
                        $results = $offline_response['results'];
                        if(isset($results['status'])) {
                            switch (strtolower(trim($results['status']))) {
                                case 'success':
                                    $results['valid_transaction'] = 1;
                                    break;

                                case 'fail':
                                    $results['valid_transaction'] = 0;
                                    break;

                                default:
                                    $results['valid_transaction'] = 0;
                                    break;
                            } // END switch
                        }
                    }
                }
            }
        }
        catch (\Exception $e) {
            $message                        = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            $results['error']               = $message;
            $results['valid_transaction']   = 0;
        }


        if($results['valid_transaction'] != 1) {
            if(isset($results['error'])) {
                if(isset($results['error']['message'])) {
                    $error_messages[] = $results['error']['message'];
                }
            }
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }





     public function searchContentReport($request)
      {

        $requestData = $request;
        $results     =  $this->passbookRep->searchListingVideoContent($requestData);
        return $results;
      }

}
