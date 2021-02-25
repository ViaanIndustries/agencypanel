<?php

namespace App\Services\Cache;

use Carbon\Carbon;
use Config, Log, Hash, File;
use GuzzleHttp\PrepareBodyMiddleware;
use Predis;
use Predis\Connection\Aggregate\RedisCluster;
use Predis\Client as PredisClient;


Class AwsElasticCacheRedis
{

    protected $redisClient;
    protected $env;
    protected $expire_time;
    protected $customer_profile_expire_time;

    public function __construct() {
        $this->expire_time                      =   600; //10 min in seconds
        $this->customer_profile_expire_time     =   (43200 * 60); // 30 days in seconds
        $this->content_expire_time              =   600; //in seconds
        $this->env                              =   env('APP_ENV', 'production');
        $this->get_from_db                      =   false;

        $this->awsElasticCacheCluster();
    }


    public function gcpCustomCacheCluster(){

        // Put your AWS ElastiCache Configuration Endpoint here.
        $configuration_endpoint  = Config::get('product.'. env('PRODUCT') .'.cache.aws_elastic_cache_cluster_endpoint');

        $parameters  = [$configuration_endpoint];

        $options = [
            'cluster' => 'redis',
            'parameters' => []
        ];

        $this->redisClient = new PredisClient($parameters, $options);

    }

    public function awsElasticCacheCluster() {

        // Put your AWS ElastiCache Configuration Endpoint here.
        $configuration_endpoint  = Config::get('product.'. env('PRODUCT') .'.cache.aws_elastic_cache_cluster_endpoint');

        $parameters  = [$configuration_endpoint];

        // Tell client to use 'cluster' mode.
        $options  = ['cluster' => 'redis'];

        // Create your redis client
        $this->redisClient = new PredisClient($parameters, $options);
    }


    public function PredisConnection(){
        return $this->redisClient;
    }


    public function saveHashData($params = [])
    {
        $hash_name          =   (!empty($params['hash_name']) && $params['hash_name'] != '') ? trim($params['hash_name']) : '';
        $hash_field         =   (!empty($params['hash_field']) && $params['hash_field'] != '') ? trim($params['hash_field']) : '';
//        $hash_field_value   =   (!empty($params['hash_field_value']) && $params['hash_field_value'] != '') ? serialize($params['hash_field_value']) : [];
        $hash_field_value   =   (!empty($params['hash_field_value']) && $params['hash_field_value'] != '') ? serialize($params['hash_field_value']) : serialize([]);
        $expire_time        =   (!empty($params['expire_time']) && $params['expire_time'] != '') ? intval($params['expire_time']) : $this->expire_time;

        try{
            if ($hash_name != '' && $hash_field != '') {
//                \Log::info('saveHashData  ', ['hash_name' => $hash_name,  'hash_field' => $hash_field, 'expire_time' => $expire_time, 'hash_field_value' => $hash_field_value]);

                $this->redisClient->hmset($hash_name, $hash_field, $hash_field_value);
                $this->redisClient->expire($hash_name, $expire_time);

            } else {
                \Log::info('saveHashData : Error ', 'hash_name or hash_field should not be null;');
            }

        } catch (\Exception $e) {
            $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            \Log::info('saveHashData Error ===>', $message);
        }
    }


    public function getHashData($params = [])
    {

        $hash_value             =   [];
        $hash_name              =   (!empty($params['hash_name']) && $params['hash_name'] != '') ? trim($params['hash_name']) : '';
        $hash_field             =   (!empty($params['hash_field']) && $params['hash_field'] != '') ? (string)trim($params['hash_field']) : '';

//        \Log::info('getHashData  ', ['hash_name' => $hash_name,  'hash_field' => $hash_field]);

        try{
            if($this->redisClient->exists($hash_name) && $this->redisClient->hexists($hash_name, $hash_field)){

                /*
                $hash_result    =   $this->redisClient->hmget($hash_name, $hash_field);
                var_dump(($hash_result));echo"<br><br><br><br>";
                var_dump(unserialize(head($hash_result)));echo"<br><br><br><br>";
                var_dump(unserialize($hash_result));echo"<br><br><br><br>";
                exit;
                */

                $hash_result    =   $this->redisClient->hmget($hash_name, $hash_field);
                if(!empty($hash_result)){
                    $hash_value              =  unserialize(head($hash_result));
                }

//                \Log::info('getHashData hash_result ===>  '. json_encode($hash_result));
            }
        } catch (\Exception $e) {
            $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            \Log::info('getHashData Error ===>', $message);
        }

        return $hash_value;
    }



    public function deleteHash($params = [])
    {

        $hash_result    =   false;
        $hash_name      =   (!empty($params['hash_name']) && $params['hash_name'] != '') ? trim($params['hash_name']) : '';
        \Log::info('deleteHash  ', ['hash_name' => $hash_name]);

        try{
            if($this->redisClient->exists($hash_name)){
                $hash_result    =   $this->redisClient->del($hash_name);
                if($hash_result){
                    $hash_result = true;
                }
                \Log::info('deleteHash hash_result ===>  '. json_encode($hash_result));
            }
        } catch (\Exception $e) {
            $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            \Log::info('deleteHash Error ===>', $message);
        }

        return $hash_result;
    }

    public function purgeArtistPastEventListCache($params) {

        $ret = false;
        $artist_id = (!empty($params['artist_id'])) ? trim($params['artist_id']) : '';

        $platforms  = Config::get('app.platforms');

        if($artist_id) {
            $cache_params   = [];
            $hash_name      = env_cache(Config::get('cache.hash_keys.artist_live_past').$artist_id);
            $cache_params   = ['hash_name' => $hash_name];

            try {
                $purge_cache   =   $this->deleteHash($cache_params);
                if($purge_cache) {
                    $ret = true;
                    \Log::info('Purge Cache - Live Artist Past Event List', $cache_params);
                }
            }
            catch (\Exception $e) {
                $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                \Log::info('Purge Cache - Live Artist Past Event List  : Failed ', $error_messages);

                $ret = false;
            }


            foreach ($platforms as $p_key => $platform) {
                $hash_name = env_cache(Config::get('cache.hash_keys.artist_live_past') . $artist_id  . ':' . trim(strtolower($p_key)));

                $cacheParams = ['hash_name' => $hash_name];
                try {
                    $purge_cache   =   $this->deleteHash($cacheParams);

                    if($purge_cache) {
                        $ret = true;
                    }
                } catch (\Exception $e) {
                    $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                    \Log::info('Purge Cache - Live Artist Past Event List  : Failed ', $error_messages);

                    $ret = false;
                }
            }
        }

        return $ret;
    }

    public function purgeArtistUpcomingEventListCache($params) {
        $ret = false;

        $artist_id = (!empty($params['artist_id'])) ? trim($params['artist_id']) : '';

        $platforms  = Config::get('app.platforms');

        if($artist_id) {

            $cache_params   = [];
            $hash_name      = env_cache(Config::get('cache.hash_keys.artist_live_upcoming').$artist_id);
            $cache_params   = ['hash_name' => $hash_name];

            try {
                $purge_cache   =   $this->deleteHash($cache_params);
                if($purge_cache) {
                    $ret = true;
                }
            }
            catch (\Exception $e) {
                $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                \Log::info('Purge Cache - Live Artist Upcoming Event List  : Failed ', $error_messages);

                $ret = false;
            }

            foreach ($platforms as $p_key => $platform) {

                $hash_name = env_cache(Config::get('cache.hash_keys.artist_live_upcoming').$artist_id . ':' . trim(strtolower($p_key)) );

                $cacheParams = ['hash_name' => $hash_name];

                try {
                    $purge_cache   =   $this->deleteHash($cacheParams);
                    if($purge_cache) {
                        $ret = true;
                    }
                } catch (\Exception $e) {
                    $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                    \Log::info('Purge Cache - Live Artist Upcoming Event List  : Failed ', $error_messages);

                    $ret = false;
                }
            }
        }

        return $ret;
    }

    public function purgeAllArtistEventListCache($params) {

        $ret = true;
        $purge_cache_upcoming = $this->purgeArtistUpcomingEventListCache($params);

        $purge_cache_past = $this->purgeArtistPastEventListCache($params);

        if($purge_cache_upcoming == false || $purge_cache_past == false) {
            $ret = false;
        }

        return $ret;
    }



    public function purgeBucketListCache($params)
    {

        $artist_id          =   (!empty($params['artist_id'])) ? trim($params['artist_id']) : '';
        $platforms          =   Config::get('app.platforms');
        $visiblitys         =   Config::get('app.visiblitys');

        $languages          =   (!empty($paramas['languages'])) ? $paramas['languages'] : ['en'];


        foreach ($platforms as $pkey => $platform){
            foreach ($visiblitys as $vkey => $visiblity){
                foreach($languages as $lkey => $language_code){
                    $platform       =   trim(strtolower($pkey));
                    $visiblity      =   trim(strtolower($vkey));
                    $language_code  =   trim(strtolower($language_code));
                    $hash_name      =   env_cache(Config::get('cache.hash_keys.bucket_lists').$artist_id.':'.$platform . ':'.$visiblity.':'.$language_code);

                    // \Log::info('hash name print ==>>' .$hash_name);

                    $cacheParams    =   ['hash_name' => $hash_name];
                    try {
                        $purge_cache   =   $this->deleteHash($cacheParams);
                    } catch (\Exception $e) {
                        $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                        \Log::info('Purge Cache - Bucket List  : Fail ', $error_messages);
                    }
                }
            }
        }

    }


    public function purgeContentListCache($params)
    {

        $bucket_id          =   (!empty($params['bucket_id'])) ? trim($params['bucket_id']) : '';
        $parent_id          =   (!empty($params['parent_id'])) ? trim($params['parent_id']) : '';
        $platforms          =   Config::get('app.platforms');
        $visiblitys         =   Config::get('app.visiblitys');
        $languages          =   (!empty($params['languages'])) ? $params['languages'] : ['en'];

        foreach ($platforms as $pkey => $platform){
            foreach ($visiblitys as $vkey => $visiblity){
                foreach($languages as $lket => $language_code) {
                    $platform       =   trim(strtolower($pkey));
                    $visiblity      =   trim(strtolower($vkey));
                    $language_code  =   trim(strtolower($language_code));
                    $hash_name      =   env_cache(Config::get('cache.hash_keys.content_lists').$bucket_id.':'.$platform . ':'.$visiblity.':'.$parent_id . ':' .$language_code);
                    $cacheParams    =   ['hash_name' => $hash_name];
                    try {
                        $purge_cache   =   $this->deleteHash($cacheParams);
                    } catch (\Exception $e) {
                        $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                        \Log::info('Purge Cache - Content List  : Fail ', $error_messages);
                    }
                }
            }
        }
    }


    public function purgeContentDetailCache($params)
    {

        $content_id     =   (!empty($params['content_id'])) ? trim($params['content_id']) : '';
        $hash_name      =   env_cache(Config::get('cache.hash_keys.content_detail').$content_id);
        $cacheParams    =   ['hash_name' => $hash_name];
        try {
            $purge_cache   =   $this->deleteHash($cacheParams);
        } catch (\Exception $e) {
            $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            \Log::info('Purge Cache - Content Detail  : Fail ', $error_messages);
        }
    }




    public function purgeCustomerPurchasePackagesListsCache($params)
    {

        $customer_id     =   (!empty($params['customer_id'])) ? trim($params['customer_id']) : '';
        $hash_name      =   env_cache(Config::get('cache.hash_keys.customer_purchase_packages_lists').$customer_id);
        $cacheParams    =   ['hash_name' => $hash_name];
        try {
            $purge_cache   =   $this->deleteHash($cacheParams);
        } catch (\Exception $e) {
            $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            \Log::info('purgeCustomerPurchasePackagesListsCache  : Fail ', $error_messages);
        }
    }


    public function purgeCustomerSpendingsListsCache($params)
    {

        $customer_id     =   (!empty($params['customer_id'])) ? trim($params['customer_id']) : '';
        $hash_name      =   env_cache(Config::get('cache.hash_keys.customer_spendings_lists').$customer_id);
        $cacheParams    =   ['hash_name' => $hash_name];
        try {
            $purge_cache   =   $this->deleteHash($cacheParams);
        } catch (\Exception $e) {
            $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            \Log::info('purgeCustomerSpendingsListsCache  : Fail ', $error_messages);
        }
    }


    public function purgeCustomerRewardsListsCache($params)
    {

        $customer_id     =   (!empty($params['customer_id'])) ? trim($params['customer_id']) : '';
        $hash_name      =   env_cache(Config::get('cache.hash_keys.customer_rewards_lists').$customer_id);
        $cacheParams    =   ['hash_name' => $hash_name];
        try {
            $purge_cache   =   $this->deleteHash($cacheParams);
        } catch (\Exception $e) {
            $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            \Log::info('purgeCustomerRewardsListsCache  : Fail ', $error_messages);
        }
    }



    public function purgeCustomerPassbookListsCache($params)
    {

        $customer_id     =   (!empty($params['customer_id'])) ? trim($params['customer_id']) : '';
        $hash_name      =   env_cache(Config::get('cache.hash_keys.customer_passbook_lists').$customer_id);
        $cacheParams    =   ['hash_name' => $hash_name];
        try {
            $purge_cache   =   $this->deleteHash($cacheParams);
        } catch (\Exception $e) {
            $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            \Log::info('purgeCustomerPassbookListsCache  : Fail ', $error_messages);
        }

        // Purge Customer
        $this->purgeAccountCustomerProfileCache($params);
    }


    /**
     * Purge Cache for Customer Meta Ids ()
     *
     * @param   array $params
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-26
     */
    public function purgeCustomerMetaIdsCache($params) {
        $ret = true;
        $error_message = '';

        $customer_id= isset($params['customer_id']) ? $params['customer_id'] : '';
        $artist_id  = isset($params['artist_id']) ? $params['artist_id'] : '';
        if($customer_id) {
            $cache_params   = [];
            $hash_name      = env_cache(Config::get('cache.keys.customermetaids') . $customer_id);
            $cache_params   = ['hash_name' => $hash_name];
            try {
                $purge_cache   =   $this->deleteHash($cache_params);
            }
            catch (\Exception $e) {
                $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                \Log::info('Purge Cache - Customer Meta Ids (' . $customer_id . ') : Fail ', $error_messages);
            }

            // Also Delete Temp Meta IDs cache
            $cache_params   = [];
            $hash_name      = env_cache(Config::get('cache.hash_keys.customer_temp_metaids') . $customer_id);
            $cache_params   = ['hash_name' => $hash_name];
            try {
                $purge_cache   =   $this->deleteHash($cache_params);
            }
            catch (\Exception $e) {
                $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                \Log::info('Purge Cache - Customer Meta Ids Temp (' . $customer_id . ') : Fail ', $error_messages);
            }
        }
        else {
            \Log::info('Trying to Purge Cache - Customer Meta Ids without customer_id ', []);
        }

        return $ret;
    }



    public function purgeContentCommentListCache($params)
    {

        $content_id         =   (!empty($params['content_id'])) ? trim($params['content_id']) : '';

        $hash_name      =   env_cache(Config::get('cache.hash_keys.content_comments_lists').$content_id);
        $cacheParams    =   ['hash_name' => $hash_name];
        try {
            $purge_cache   =   $this->deleteHash($cacheParams);
        } catch (\Exception $e) {
            $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            \Log::info('purgeContentCommentListCache  : Fail ', $error_messages);
        }

    }




    public function purgeContentCommentRepliesListCache($params)
    {

        $comment_id         =   (!empty($params['comment_id'])) ? trim($params['comment_id']) : '';

        $hash_name      =   env_cache(Config::get('cache.hash_keys.content_commentreplies_lists').$comment_id);
        $cacheParams    =   ['hash_name' => $hash_name];
        try {
            $purge_cache   =   $this->deleteHash($cacheParams);
        } catch (\Exception $e) {
            $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            \Log::info('purgeContentCommentRepliesListCache : Fail ', $error_messages);
        }

    }



    public function purgeArtistConfigCache($params)
    {
        $artist_id      =   (!empty($params['artist_id'])) ? trim($params['artist_id']) : '';
        $hash_name      =   env_cache(Config::get('cache.hash_keys.artist_config').$artist_id);
        $cacheParams    =   ['hash_name' => $hash_name];
        try {
            $purge_cache   =   $this->deleteHash($cacheParams);
        } catch (\Exception $e) {
            $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            \Log::info('purgeArtistConfigCache  : Fail ', $error_messages);
        }
    }


    public function purgeArtistLeaderBoardsCache($params)
    {
        $artist_id      =   (!empty($params['artist_id'])) ? trim($params['artist_id']) : '';
        $hash_name      =   env_cache(Config::get('cache.hash_keys.artist_leaderboards').$artist_id);
        $cacheParams    =   ['hash_name' => $hash_name];
        try {
            $purge_cache   =   $this->deleteHash($cacheParams);
        } catch (\Exception $e) {
            $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            \Log::info('purgeArtistLeaderBoardsCache  : Fail ', $error_messages);
        }
    }



    public function purgePackageListCache($params)
    {
        $artist_id      =   (!empty($params['artist_id'])) ? trim($params['artist_id']) : '';
        $platforms      =   Config::get('app.platforms');

        foreach ($platforms as $pkey => $platform){
            $platform       =   trim(strtolower($pkey));
            $hash_name      =   env_cache(Config::get('cache.hash_keys.packages_lists').$artist_id.':'.$platform );
            $cacheParams    =   ['hash_name' => $hash_name];
            try {
                $purge_cache   =   $this->deleteHash($cacheParams);
            } catch (\Exception $e) {
                $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                \Log::info('purgePackageListCache : Fail ', $error_messages);
            }

        }


    }


    public function purgePaytmPackageListCache($params)
    {
        $artist_id      =   (!empty($params['artist_id'])) ? trim($params['artist_id']) : '';
        $platforms      =   Config::get('app.platforms');
        foreach ($platforms as $pkey => $platform){
            $platform       =   trim(strtolower($pkey));
            $hash_name      =   env_cache(Config::get('cache.hash_keys.paytmpackages_lists').$artist_id.':'.$platform );
            $cacheParams    =   ['hash_name' => $hash_name];
            try {
                $purge_cache   =   $this->deleteHash($cacheParams);
            } catch (\Exception $e) {
                $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                \Log::info('purgePackageListCache : Fail ', $error_messages);
            }

        }
    }



    public function purgeGiftListCache($params)
    {
        $artist_id      =   (!empty($params['artist_id'])) ? trim($params['artist_id']) : '';
        $hash_name      =   env_cache(Config::get('cache.hash_keys.gifts_lists').$artist_id);
        $cacheParams    =   ['hash_name' => $hash_name];
        try {
            $purge_cache   =   $this->deleteHash($cacheParams);
        } catch (\Exception $e) {
            $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            \Log::info('purgeGiftListCache  : Fail ', $error_messages);
        }
    }



    public function purgeHomePageListCache($params)
    {
        $platforms          =   Config::get('app.platforms');

        foreach ($platforms as $pkey => $platform){
            $platform       =   trim(strtolower($pkey));
            $hash_name      =   env_cache(Config::get('cache.hash_keys.homepage_listing').$platform);
            $cacheParams    =   ['hash_name' => $hash_name];
            try {
                $purge_cache   =   $this->deleteHash($cacheParams);
            }
            catch (\Exception $e) {
                $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                \Log::info('Purge Cache - Home Page List  : Fail ', $error_messages);
            }
        }
    }

    public function purgeLanguageListCache($params = [])
    {
        $hash_name      =   env_cache(Config::get('cache.hash_keys.language_list'));
        $cacheParams    =   ['hash_name' => $hash_name];
        try {
            $purge_cache   =   $this->deleteHash($cacheParams);
        } catch (\Exception $e) {
            $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            \Log::info('purgeLanguageListCache  : Fail ', $error_messages);
        }
    }


    public function purgeAccountCustomerProfileCache($params) {
        $ret        = true ;
        $customer_id= '';
        $platforms  = Config::get('app.platforms');

        $customer_id= isset($params['customer_id']) ? $params['customer_id'] : '';
        if($customer_id) {
            //
            $cache_params   = [];
            $hash_name      = env_cache(Config::get('cache.hash_keys.account_profile') . $customer_id);
            $cache_params   = ['hash_name' => $hash_name];
            try {
                $purge_cache   =   $this->deleteHash($cache_params);
            }
            catch (\Exception $e) {
                $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                \Log::info('Purge Cache - Account Customer Profile (' . $customer_id . ') : Fail ', $error_messages);
            }

            // Then delete cache for all platforms
            if($platforms) {
                foreach ($platforms as $pkey => $platform){
                    $cache_params_p = [];
                    $platform       = trim(strtolower($pkey));
                    $hash_name      = env_cache(Config::get('cache.hash_keys.account_profile') . $customer_id . ':' . $platform);
                    $cache_params_p = ['hash_name' => $hash_name];
                    try {
                        $purge_cache   =   $this->deleteHash($cache_params_p);
                    }
                    catch (\Exception $e) {
                        $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                        \Log::info('Purge Cache - Account Customer Profile (' . $customer_id . ')  & platform ( ' . $platform . ') : Fail ', $error_messages);
                    }
                }
            }
        }

        return $ret;
    }

    /**
     * Purge Cache for Account Customer Meta Ids ()
     *
     * @param   array $params
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function purgeAccountCustomerMetaIdsCache($params) {
        $ret = true;
        $error_message = '';

        $customer_id= isset($params['customer_id']) ? $params['customer_id'] : '';
        $artist_id  = isset($params['artist_id']) ? $params['artist_id'] : '';
        if($customer_id) {
            $cache_params   = [];
            $hash_name      = env_cache(Config::get('cache.hash_keys.account_metaids') . $customer_id);
            $cache_params   = ['hash_name' => $hash_name];
            try {
                $purge_cache   =   $this->deleteHash($cache_params);
            }
            catch (\Exception $e) {
                $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                \Log::info('Purge Cache - Account Customer Meta Ids (' . $customer_id . ') : Fail ', $error_messages);
            }
        }
        else {
            \Log::info('Trying to Purge Cache - Account Customer Meta Ids without customer_id ', []);
        }

        return $ret;
    }

    /**
     * Purge Cache for Account Customer i.e.
     *
     * @param   array   $params
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function purgeAccountCustomerCache($params) {
        $ret = true;
        $error_message = '';

        try {
            // First Purge Account Customer Profile Cache
            $purge_profile = $this->purgeAccountCustomerProfileCache($params);

            // Then Purge Account By Email. getCustomerIdByMobile
            $purge_mobile_list = $this->purgeAccountCustomerIdByEmailCache($params);

            // Then Purge Account By Mobile No. getCustomerIdByMobile
            $purge_mobile_list = $this->purgeAccountCustomerIdByMobileCache($params);

            // Then Purge Customer Coins XP Cache
        }
        catch (\Exception $e) {
            $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            \Log::info('Purge Cache - Account Customer Cache : Fail ', $error_messages);
        }

        return $ret;
    }

    /**
     * Purge Cache for finding Customer Id By Mobile
     *
     * @param   array $params
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function purgeAccountCustomerIdByMobileCache($params) {
        $ret = true;
        $error_message  = '';
        $platforms      = Config::get('app.platforms');

        $cache_params   = [];
        $hash_name      = env_cache(Config::get('cache.hash_keys.account_by_mobile'));
        $cache_params   = ['hash_name' => $hash_name];
        try {
            $purge_cache   =   $this->deleteHash($cache_params);
        }
        catch (\Exception $e) {
            $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            \Log::info(__METHOD__  . 'Purge Cache - Account Customer Id By Mobile No.', $error_messages);
        }


        // Then delete cache for all platforms
        if($platforms) {
            foreach ($platforms as $pkey => $platform){
                $cache_params_p = [];
                $platform       = trim(strtolower($pkey));
                $hash_name      = env_cache(Config::get('cache.hash_keys.account_by_mobile') . ':' . $platform );
                $cache_params_p = ['hash_name' => $hash_name];
                try {
                    $purge_cache   =   $this->deleteHash($cache_params_p);
                }
                catch (\Exception $e) {
                    $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                    \Log::info(__METHOD__  . 'Purge Cache - Account Customer Id By Mobile No. & platform ( ' . $platform . ') : Fail ', $error_messages);
                }
            }
        }

        return $ret;
    }


    /**
     * Purge Cache for finding Customer Id By Email
     *
     * @param   array $params
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-31
     */
    public function purgeAccountCustomerIdByEmailCache($params) {
        $ret = true;
        $error_message  = '';
        $platforms      = Config::get('app.platforms');
        $email          = isset($params['email']) ? strtolower(trim($params['email'])) : '';

        $cache_params   = [];
        $hash_name      = env_cache(Config::get('cache.hash_keys.account_by_email'));
        $cache_params   = ['hash_name' => $hash_name];
        if($email) {
            $cache_params[''] = $email;
        }

        try {
            $purge_cache   =   $this->deleteHash($cache_params);
        }
        catch (\Exception $e) {
            $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            \Log::info(__METHOD__  . 'Purge Cache - Account Customer Id By Email.', $error_messages);
        }

        // Then delete cache for all platforms
        if($platforms) {
            foreach ($platforms as $pkey => $platform){
                $cache_params_p = [];
                $platform       = trim(strtolower($pkey));
                $hash_name      = env_cache(Config::get('cache.hash_keys.account_by_mobile') . ':' . $platform );
                $cache_params_p = ['hash_name' => $hash_name];
                try {
                    $purge_cache   =   $this->deleteHash($cache_params_p);
                }
                catch (\Exception $e) {
                    $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                    \Log::info(__METHOD__  . 'Purge Cache - Account Customer Id By Email ( ' . $platform . ') : Fail ', $error_messages);
                }
            }
        }

        return $ret;
    }

    /**
     * Purge Cache for Customer Coins XP
     *
     * @param   array $params
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-19
     */
    public function purgeCustomerCoinsXp($params) {
        $ret = true;
        $error_message = '';

        $customer_id= isset($params['customer_id']) ? $params['customer_id'] : '';
        if($customer_id) {
            $cache_params   = [];
            $hash_name      = env_cache(Config::get('cache.keys.customercoinsxp') . $customer_id);
            $cache_params   = ['hash_name' => $hash_name];
            try {
                $purge_cache   =   $this->deleteHash($cache_params);
            }
            catch (\Exception $e) {
                $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                \Log::info('Purge Cache - Customer Coins XP for customer_id (' . $customer_id . ') : Fail ', $error_messages);
            }
        }
        else {
            \Log::info('Trying to Purge Cache - Customer Coins XP  without customer_id ', []);
        }

        return $ret;
    }

    /**
     * Purge Cache for contestant_artist_sortby
     *
     * @param   array $params
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-19
     */
    public function purgeContestantArtistSortby($params = []) {
        $ret = true;
        $error_message  = '';
        $platforms      = Config::get('app.platforms');
        $sort_by_array  = ['hot', 'cold'];

        foreach ($sort_by_array as $key => $sort_by) {
            // Then delete cache for all platforms
            if($platforms) {
                foreach ($platforms as $pkey => $platform){
                    $cache_params_p = [];
                    $platform       = trim(strtolower($pkey));
                    $hash_name      = env_cache(Config::get('cache.hash_keys.contestant_artist_sortby')) . $sort_by . ':' . $platform;
                    $cache_params_p = ['hash_name' => $hash_name];
                    try {
                        $purge_cache   =   $this->deleteHash($cache_params_p);
                    }
                    catch (\Exception $e) {
                        $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                        \Log::info(__METHOD__  . 'Purge Cache - Contestant Artist Sortby ' . $sort_by . ' & platform ( ' . $platform . ') : Fail ', $error_messages);
                    }
                }
            }
        }

        return $ret;
    }


    /**
     * Purge All Cast Related Cache
     *
     * @param   array $params
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-23
     */
    public function purgeCastCache($params = []) {
        $ret = true;
        $error_message = '';

        $cache_params   = [];
        $hash_name      = env_cache(Config::get('cache.hash_keys.cast_search'));
        $cache_params   = ['hash_name' => $hash_name];
        try {
            $purge_cache = $this->deleteHash($cache_params);
        }
        catch (\Exception $e) {
            $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            \Log::info('Purge Cache - Search Cast : Fail ', $error_messages);
        }

        return $ret;
    }


    /**
     * Purge All Reward Program Related Cache w.r.t. artist
     *
     * @param   array $params
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-27
     */
    public function purgeAllArtistRewardProgramListCache($params = []) {
        $ret = true;
        $error_message  = '';
        $artist_id      = isset($params['artist_id']) ? $params['artist_id'] : '';

        if($artist_id) {
            $cache_params   = [];
            $hash_name      = env_cache(Config::get('cache.hash_keys.rewardprogram_list') . $artist_id);
            $cache_params   = ['hash_name' => $hash_name];
            try {
                $purge_cache = $this->deleteHash($cache_params);
            }
            catch (\Exception $e) {
                $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                \Log::info('Purge Cache - Search Cast : Fail ', $error_messages);
            }
        }

        return $ret;
    }


    /**
     * Purge All Customer Activites Related Cache w.r.t. artist
     *
     * @param   array $params
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-27
     */
    public function purgeAllCustomerActivityByArtistWiseListCache($params = []) {
        $ret = true;
        $error_message  = '';
        $customer_id    = isset($params['customer_id']) ? $params['customer_id'] : '';
        $artist_id      = isset($params['artist_id']) ? $params['artist_id'] : '';

        if($customer_id && $artist_id) {
            $cache_params   = [];
            $hash_name      = env_cache(Config::get('cache.hash_keys.customer_artistwise_activity_list') . $customer_id . ':' . $artist_id);
            $cache_params   = ['hash_name' => $hash_name];
            try {
                $purge_cache = $this->deleteHash($cache_params);
            }
            catch (\Exception $e) {
                $error_messages = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                \Log::info('Purge Cache - All Customer Activity By ArtistWise : Fail ', $error_messages);
            }
        }

        return $ret;
    }

}
