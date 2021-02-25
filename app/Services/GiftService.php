<?php

namespace App\Services;

use Input, Config;
use App\Repositories\Contracts\GiftInterface;
use App\Repositories\Contracts\CustomerInterface;
use App\Repositories\Contracts\CustomerActivityInterface;
use App\Models\Gift as Gift;
use App\Services\Jwtauth;
use App\Services\RedisDb;
use App\Services\Image\Kraken;
use App\Services\AwsCloudfront;
use App\Services\Cache\AwsElasticCacheRedis;

class GiftService
{
    protected $repObj;
    protected $package;
    protected $activityRep;
    protected $customerRep;
    protected $jwtauth;
    protected $redisdb;
    protected $kraken;
    protected $awscloudfrontService;
    protected $awsElasticCacheRedis;


    public function __construct(
        Jwtauth $jwtauth,
        CustomerActivityInterface $activity,
        Gift $gift,
        GiftInterface $repObj,
        CustomerInterface $customer,
        RedisDb $redisdb,
        Kraken $kraken,
        AwsCloudfront $awscloudfrontService,
        AwsElasticCacheRedis $awsElasticCacheRedis
    )
    {
        $this->kraken = $kraken;
        $this->jwtauth = $jwtauth;
        $this->gift = $gift;
        $this->repObj = $repObj;
        $this->customerRep = $customer;
        $this->activityRep = $activity;
        $this->redisdb = $redisdb;
        $this->kraken = $kraken;
        $this->awscloudfrontService = $awscloudfrontService;
        $this->awsElasticCacheRedis = $awsElasticCacheRedis;
    }


    public function lists($request)
    {
        $requestData = $request->all();
        $error_messages = [];
        $results = [];
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? trim($requestData['artist_id']) : '';
        $type = (isset($requestData['type']) && $requestData['type'] != '') ? $requestData['type'] : 'paid';
        $live_type = (isset($requestData['live_type']) && $requestData['live_type'] != '') ? $requestData['live_type'] : 'general';
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? trim($requestData['platform']) : 'android';

        $cacheParams = [];
        $hash_name = env_cache(Config::get('cache.hash_keys.gifts_lists') . $artist_id);
        $hash_field = $type . ":" . $live_type . ":" . $platform;
        $cache_miss = false;

        $cacheParams['hash_name'] = $hash_name;
        $cacheParams['hash_field'] = (string)$hash_field;
        $cacheParams['expire_time'] = Config::get('cache.1_month') * 60;

        $gifts = $responses = $this->awsElasticCacheRedis->getHashData($cacheParams);
        if (empty($gifts)) {
            $responses = $this->repObj->lists($requestData);
            $items = ($responses) ? apply_cloudfront_url($responses) : [];
            $cacheParams['hash_field_value'] = $items;
            $saveToCache = $this->awsElasticCacheRedis->saveHashData($cacheParams);
            $cache_miss = true;
            $gifts = $this->awsElasticCacheRedis->getHashData($cacheParams);
        }

        $results['list'] = $gifts;
        $results['cache'] = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];
        $results['quantities'] = Config::get('app.quantities');
        $results['stickers_price'] = Config::get('app.stickers_price');

        return ['error_messages' => $error_messages, 'results' => $results];

    }

    public function index($request)
    {
        $requestData = $request->all();

        $results = $this->repObj->index($requestData);
        return $results;
    }


    public function paginate()
    {
        $error_messages = $results = [];
        $results = $this->repObj->paginateForApi();

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function activeLists()
    {
        $error_messages = $results = [];
        $results = $this->repObj->activeLists();

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function getArtistGiftListing($request)
    {
        $error_messages = $results = [];
        $response = $this->repObj->getArtistGiftListing($request);
        $results = apply_cloudfront_url($response);
        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function find($id)
    {
        $results = $this->repObj->find($id);
        return $results;
    }

    public function sendGift($request)
    {
        $error_messages = $results = [];
        $gift_id = $request['gift_id'];
        $artist_id = $request['artist_id'];
        $platform = $request['platform'];
        $customer_id = $this->jwtauth->customerIdFromToken();

        //get gifts from cache
        $giftObj = $this->repObj->find($gift_id);
        $total_quantity = (isset($request['total_quantity'])) ? trim($request['total_quantity']) : 1;
        $free_gifts = [];

        // get coins from cache
        $customer = \App\Models\Customer::where('_id', $customer_id)->first();

        if (empty($customer)) {
            $error_messages[] = 'Customer does not exists';
        }

        if (empty($giftObj)) {
            $error_messages[] = 'Gift does not exists';
        }

        if (!empty($error_messages)) {
            return ['error_messages' => $error_messages, 'results' => $results];
        }

        $gift_type = ($giftObj && isset($giftObj['type'])) ? strtolower(trim($giftObj['type'])) : "";

//        print_pretty($customer);var_dump($gift_type);exit;


        if ($gift_type != '') {

            // Manage for paid gifts
            if ($gift_type == 'paid') {

                $coins = (isset($giftObj['coins'])) ? intval($giftObj['coins']) : 0;
                $total_coins = $coins * $total_quantity;


                if ($customer && isset($customer['coins']) && $customer['coins'] < $total_coins) {
                    $error_messages[] = 'Not Enough Coins, Add More';
                }

                if (!empty($error_messages)) {
                    return ['error_messages' => $error_messages, 'results' => $results];
                }

                $coins_before_purchase = (isset($customer->coins)) ? $customer->coins : 0;
                $coins_after_purchase = (isset($customer->coins)) ? $customer->coins - $total_coins : 0;


                if ($coins_after_purchase < 0) {
                    $coins_after_purchase = 0;
                }
                $data = ['coins' => $coins_after_purchase];
                $customerArtistObj = $customer->update($data);

                $this->redisdb->saveCustomerCoins($customer_id, $coins_after_purchase);


                //insert customer purchase content data
                $purchaseData = [
                    'entity' => 'gifts',
                    'entity_id' => $giftObj['_id'],
                    'customer_id' => $customer_id,
                    'artist_id' => $artist_id,
                    'coins' => $total_coins,
                    'coin_of_one' => intval($giftObj['coins']), //coin_of_one_gift
                    'total_quantity' => intval($total_quantity),
                    'platform' => $platform,
                    'coins_before_purchase' => $coins_before_purchase,
                    'coins_after_purchase' => $coins_after_purchase,
                    'passbook_applied' => true

                ];

                $purchaseObj = new \App\Models\Purchase($purchaseData);
                $purchaseObj->save();

////================================================Cahce Flush==================================================
                $purge_result = $this->awsElasticCacheRedis->purgeCustomerSpendingsListsCache(['customer_id' => $customer_id]);

// will used later by using queues
//                $activityData = [
//                    'name' => 'send_gift',
//                    'customer_id' => $customer_id,
//                    'artist_id' => $artist_id,
//                    'entity' => 'gifts',
//                    'entity_id' => $giftObj['_id'],
//                    'total_quantity' => intval($total_quantity),
//                    'total_coins' => intval($total_coins),
//                    'coin_of_one' => intval($giftObj['coins']), //coin_of_one_gift
//                    'platform' => $platform,
//                    'coins_before_purchase' => $coins_before_purchase,
//                    'coins_after_purchase' => $coins_after_purchase
//                ];
//
//                $activityObj = $this->activityRep->store($activityData);

                $results = $purchaseObj;
                $results['coins_before_purchase'] = $coins_before_purchase;
                $results['coins_after_purchase'] = $coins_after_purchase;

            }//Paid


            // Manage for free gifts
//            if ($gift_type == 'free') {
//                $customerartistObj    =   \App\Models\Customerartist::where('customer_id','=', $customer_id)->where('artist_id','=', $artist_id)->first();
//            }//Free


        }//gift type

        return ['error_messages' => $error_messages, 'results' => $results];

    }

    public function puchaseStickers($request)
    {
        $error_messages = $results = [];
        $customer_id = $this->jwtauth->customerIdFromToken();
        $artist_id = $request['artist_id'];
        $platform = $request['platform'];

        $entity = 'stickers';

        if (!empty($entity) && !empty($artist_id) && !empty($customer_id)) {

            $customer = \App\Models\Customer::where('_id', $customer_id)->where('status', 'active')->first();
            $total_coins = Config::get('app.stickers_price');

            if (empty($customer) || $customer['coins'] < $total_coins) {
                $error_messages[] = 'Not Enough Coins, Add More';
            }

            if (empty($error_messages)) {
                $checkExistanceOfStickers = \App\Models\Purchase::where('artist_id', $artist_id)->where('customer_id', $customer_id)->where('entity', $entity)->first();

                if (!empty($checkExistanceOfStickers)) {
                    $error_messages[] = 'Stickers already Available against this customer with artist';
                }
            }

            if (empty($error_messages)) {

                $coins_before_purchase = (isset($customer->coins)) ? $customer->coins : 0;
                $coins_after_purchase = (isset($customer->coins)) ? $customer->coins - $total_coins : 0;

                if ($coins_after_purchase < 0) {
                    $coins_after_purchase = 0;
                }

                $data = [
                    'coins' => $coins_after_purchase,
                    'purchase_stickers' => true
                ];

                $customer->update($data);


//=============================================Customer Login and Wallet Balance Update============================================
                $this->redisdb->saveCustomerCoins($customer_id, $coins_after_purchase);

                $customer = !empty($customer) ? $customer->toArray() : [];
                $this->redisdb->saveCustomerProfile($customer_id, $customer);
//=============================================Customer Login and Wallet Balance Update============================================

                $purchaseData = [
                    'entity' => $entity,
                    'customer_id' => $customer_id,
                    'artist_id' => $artist_id,
                    'coins' => $total_coins,
                    'platform' => $platform,
                    'coins_before_purchase' => $coins_before_purchase,
                    'coins_after_purchase' => $coins_after_purchase,
                    'passbook_applied' => true
                ];

                $purchaseObj = new \App\Models\Purchase($purchaseData);
                $purchaseObj->save();

                $results = $purchaseObj;

                ////===Cahce Flush===
                $purge_result = $this->awsElasticCacheRedis->purgeCustomerSpendingsListsCache(['customer_id' => $customer_id]);


            }

        } else {
            $error_messages[] = 'Something is missing';
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function show($id)
    {
        $error_messages = $results = [];
        if (empty($error_messages)) {
            $results['role'] = $this->repObj->find($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function store($request)
    {
        $data = $request->all();
        $error_messages = $results = [];
        array_set($data, 'slug', str_slug($data['name']));


        //upload original gif photo
        if ($request->hasFile('picture')) {
            $parmas = ['file' => $request->file('picture'), 'type' => 'gifts'];
            $photo = $this->kraken->uploadPhotoToAws($parmas);
            if (!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results']['ObjectURL'])) {
                array_set($data, 'picture', $photo['results']['ObjectURL']);
               $size =  $this->calculateImageSize($photo['results']['ObjectURL']);
               array_set($data, 'image_size', $size);
            }
        }

          //upload thumb photo
          if ($request->hasFile('photo')) {
            $parmas = ['file' => $request->file('photo'), 'type' => 'gifts'];
            $photo = $this->kraken->uploadToAws($parmas);
            if (!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])) {
                array_set($data, 'photo', $photo['results']);
            }
        }


        if (empty($error_messages)) {

            $gift = $this->repObj->store($data);
            $results['gift'] = $gift;

            //Purge Redis
            $artist_ids = (!empty($gift['artists'])) ? $gift['artists'] : [];
            foreach ($artist_ids as $artist_id) {
                $purge_result = $this->awsElasticCacheRedis->purgeGiftListCache(['artist_id' => $artist_id]);
            }

            if (env('APP_ENV', 'stg') == 'production') {
                try {
                    $invalidate_result = $this->awscloudfrontService->invalidateGifts();
                } catch (Exception $e) {
                    $error_messages = [
                        'error' => true, 'type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()
                    ];
                    Log::info('AwsCloudfront - Invalidation  : Fail ', $error_messages);
                }
            }
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function update($request, $id)
    {
        $data = $request->all();
        $error_messages = $results = [];
        $slug = str_slug($data['name']);
        array_set($data, 'slug', $slug);

        //upload original gif photo
        if ($request->hasFile('picture')) {
            $parmas = ['file' => $request->file('picture'), 'type' => 'gifts'];
            $photo = $this->kraken->uploadPhotoToAws($parmas);
            if (!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results']['ObjectURL'])) {
                array_set($data, 'picture', $photo['results']['ObjectURL']);
               $size =  $this->calculateImageSize($photo['results']['ObjectURL']);
               array_set($data, 'image_size', $size);
            }
        }

          //upload thumb photo
          if ($request->hasFile('photo')) {
            $parmas = ['file' => $request->file('photo'), 'type' => 'gifts'];
            $photo = $this->kraken->uploadToAws($parmas);
            if (!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])) {
                array_set($data, 'photo', $photo['results']);
            }
        }

        if (empty($error_messages)) {

            $gift = $this->repObj->update($data, $id);
            $results['gift'] = $gift;

            //Purge Redis
            $artist_ids = (!empty($gift['artists'])) ? $gift['artists'] : [];
            foreach ($artist_ids as $artist_id) {
                $purge_result = $this->awsElasticCacheRedis->purgeGiftListCache(['artist_id' => $artist_id]);
            }

            if (env('APP_ENV', 'stg') == 'production') {
                try {
                    $invalidate_result = $this->awscloudfrontService->invalidateGifts();
                } catch (Exception $e) {
                    $error_messages = [
                        'error' => true, 'type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()
                    ];
                    Log::info('AwsCloudfront - Invalidation  : Fail ', $error_messages);
                }
            }
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function destroy($id)
    {
        $results = $this->repObj->destroy($id);
        return $results;
    }


    public function calculateImageSize($url)
    {
        $ch = curl_init(convert_to_cloudfront_url($url));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_NOBODY, TRUE);     
        $data = curl_exec($ch);
        $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
       
        curl_close($ch);
        return $size;

    }


}