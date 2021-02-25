<?php

namespace App\Services\Cache;

use Carbon\Carbon;
use Config, Log, Hash, File, Cache;
use GuzzleHttp\PrepareBodyMiddleware;

use App\Repositories\Contracts\BucketInterface;
use App\Services\Cache\AwsElasticCacheRedis;


Class Bucket extends  AwsElasticCacheRedis {

    protected $buckcetrepObj;
    protected $cacheClient;

    public function __construct(
        BucketInterface $buckcetrepObj,
        AwsElasticCacheRedis $cacheClient
    ){
        $this->buckcetrepObj                =   $buckcetrepObj;
        $this->cacheClient                  =   $cacheClient->redisClient;
        $this->bucketlists_key              =   'b:lists';
        $this->bucketdetail_key             =   'b:detail';
        $this->bucketlists_limit            =   10;

        //600 in seconds // (43200 * 60); // 30 days in seconds
        $this->default_expire_time          =   600;

    }



    public function getBucketDetailId($buckectArr = array()){

        $bucket_detail_id      =   '';
        $bucket_detail_score   =   0;
        if(!empty($buckectArr['bucket_id']) && $buckectArr['bucket_id'] != ''){
            $bucket_detail_id       =    trim($buckectArr['bucket_id']);
        }
        if(!empty($buckectArr['_id']) && $buckectArr['_id'] != ''){
            $bucket_detail_id       =    trim($buckectArr['_id']);
        }
        return $bucket_detail_id;
    }


    public function getBucketDetailArtistId($buckectArr = array()){

        return $artist_id = (isset($buckectArr['artist_id']) && $buckectArr['artist_id'] != '') ? trim($buckectArr['artist_id']) : '';
    }


    public function getBucketDetailScore($buckectArr = array()){

        $bucket_detail_score   =   0;
        if(!empty($buckectArr['updated_at']) && $buckectArr['updated_at'] != ''){
            $bucket_detail_score       =    strtotime($buckectArr['updated_at']);
        }

//        var_dump($buckectArr['updated_at']);
//        var_dump($bucket_detail_score);
//        var_dump(date('Y:m:d H:i:s',$bucket_detail_score));

        return intval($bucket_detail_score);
    }



    public function getBucketDetailKey($bucket_id = ''){

        $key    =   env_cache($this->bucketdetail_key.':'.$bucket_id);

        return $key;
    }


    public function getBucketListKey($requestData){

        $artist_id       =   (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? trim($requestData['artist_id']) : '';
        $visiblity       =   (isset($requestData['visiblity']) && $requestData['visiblity'] != '') ? $requestData['visiblity'] : 'customer';
        $platform        =   (isset($requestData['platform']) && $requestData['platform'] != '') ? trim($requestData['platform']) : "android";
        $key             =   env_cache($this->bucketlists_key.':'.$artist_id.':'.$platform.':'.$visiblity);

        return $key;
    }



    public function getBucketListALlSortSetKeys($bucket_detail_artist_id){

        $sortSetKeys                =   [];
        $bucket_platforms           =   ['android','ios'];
        $bucket_visiblitys          =   ['customer', 'producer'];

        foreach ($bucket_platforms as $platform){
            foreach ($bucket_visiblitys as $visiblity){
                $requestData = ['platform' => trim($platform),  'visiblity' => trim($visiblity), 'artist_id' => $bucket_detail_artist_id];
                $sortSetKey =   $this->getBucketListKey($requestData);
                array_push($sortSetKeys, $sortSetKey);
            }
        }

        return $sortSetKeys;
    }



    public function getBucketListALlValidSortSetKeys($bucket_detail_artist_id, $buckectArr = array()){

        $sortSetKeys               =   [];
        $bucket_platforms          =   (!empty($buckectArr['platforms'])) ? trim($buckectArr['platforms']) : [];
        $bucket_visiblitys         =   (!empty($buckectArr['visiblity'])) ? trim($buckectArr['visiblity']) : [];

        foreach ($bucket_platforms as $platform){
            foreach ($bucket_visiblitys as $visiblity){
                $requestData = ['platform' => trim($platform),  'visiblity' => trim($visiblity), 'artist_id' => $bucket_detail_artist_id];
                $sortSetKey =   $this->getBucketListKey($requestData);
                array_push($sortSetKeys, $sortSetKey);
            }
        }

        return $sortSetKeys;
    }




    public function getStart($requestData){

        $page       =   (isset($requestData['page']) && $requestData['page'] != '') ? intval($requestData['page']) : 0;
        $start      =   intval(($page - 1) * $this->bucketlists_limit);

        return $start;
    }

    public function getOffest($requestData){

        $page       =   (isset($requestData['page']) && $requestData['page'] != '') ? intval($requestData['page']) : 0;
        $offest     =   intval($page * $this->bucketlists_limit ) - 1;
        return $offest;
    }




    public function paginate($requestData = array()){

        $requestData            =   $requestData;
        $bucketdetail_ids       =   [];
        $status                 =   200;
        $lists                  =   [];
        $error_message          =   [];

        try {

            $bucketlist_key                 =   $this->getBucketListKey($requestData);
            $start                          =   $this->getStart($requestData);
            $offest                         =   $this->getOffest($requestData);
            $perpage                        =   intval($this->bucketlists_limit);

            $requestData['bucketlist_key']  =   $bucketlist_key;
            $requestData['start']           =   $start;
            $requestData['offest']          =   $offest;
            $requestData['perpage']         =   $perpage;

            $bucketdetail_ids               =   $this->cacheClient->zrange($bucketlist_key, $start, $offest);

            // get reslust from db
            if(count($bucketdetail_ids) < $perpage){
                $resultSet                  =   $this->buckcetrepObj->lists($requestData);
                $bucketListResultSet        =   (!empty($resultSet['list'])) ? $resultSet['list'] : [];

                if(!empty($bucketListResultSet)){
                    foreach ($bucketListResultSet as $key => $bucketVal){
                        $this->saveBucketDetailHash($bucketVal);
                    }
                }
            }

            $bucketdetail_ids       =   $this->cacheClient->zrange($bucketlist_key, $start, $offest);
            $lists                  =   $this->getBucketListing($bucketdetail_ids);


        }catch (\Exception $e) {

            $status         =   500;
            $error_message  = ['error' => true, 'type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
        }

        $response = [
            'request' => $requestData,
            'bucketdetail_ids' => $bucketdetail_ids,
            'lists' => $lists,
            'status' => $status,
            'error_message' => $error_message
        ];
        return $response;
    }


    public function getBucketListing($bucketdetail_ids = array()){

        $lists = [];
        if(count($bucketdetail_ids) > 0){
            foreach ($bucketdetail_ids as $bucketdetail_id){
                \Log::info('getBucketListing bucketdetail_id  ==> '.$bucketdetail_id);
                $bucketdetail = $this->cacheClient->hmget($bucketdetail_id, 'value');

                // If Empty Get bucket detail from db

                if(!empty($bucketdetail)){
                    if(is_array($bucketdetail)){
                        array_push($lists, cache_unserialize(head($bucketdetail)));
                    }else{
                        array_push($lists, cache_unserialize($bucketdetail));
                    }
                }
            }
        }

        return $lists;
    }



    public function saveBucketDetailHash($buckectArr = array()){

        $bucket_detail_id       =   $this->getBucketDetailId($buckectArr);
        $bucketdetail_key       =   $this->getBucketDetailKey($bucket_detail_id);
        $bucketdetail_value     =   cache_serialize($buckectArr);

        //Save Bucket Detail As hash
        try{

            $this->cacheClient->hmset($bucketdetail_key, 'value', $bucketdetail_value);
            $this->cacheClient->expire($bucketdetail_key, $this->default_expire_time);

            //$set_fourth_value = $this->cacheClient->hmget($bucketdetail_key, 'value');
            \Log::info('saveBucketDetailHash  : Key => '. $bucketdetail_key);

        }catch (\Exception $e) {

            $error_message  = ['error' => true, 'type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            Log::info('saveBucketDetailHash  : Fail ', $error_message);
        }


        //First Unsync Or dettach ids from all sort sets
        $this->removeBucketDetailFromTags($buckectArr);

        //Push assignBucketDetailToTags
        $this->assignBucketDetailToTags($buckectArr);

    }


    public function assignBucketDetailToTags($buckectArr){

        $bucket_detail_id           =   $this->getBucketDetailId($buckectArr);
        $bucketdetail_key           =   $this->getBucketDetailKey($bucket_detail_id);
        $bucket_detail_score        =   intval($this->getBucketDetailScore($buckectArr));
        $bucket_detail_artist_id    =   $this->getBucketDetailArtistId($buckectArr);

        $all_bucket_list_sort_sets  =   $this->getBucketListALlValidSortSetKeys($bucket_detail_artist_id, $buckectArr);
        foreach ($all_bucket_list_sort_sets as $bucket_list_sort_set){
            $tagged = $this->cacheClient->zadd($bucket_list_sort_set, $bucket_detail_score, $bucketdetail_key);
            $info   = ['sortset' => $bucket_list_sort_set,  'score' => $bucket_detail_score, 'key' => $bucketdetail_key];
            \Log::info('assignBucketDetailToTags  ', $info);
            \Log::info('assignBucketDetailToTags => '.$tagged);
        }

    }



    public function removeBucketDetailFromTags($buckectArr){

        $bucket_detail_id           =   $this->getBucketDetailId($buckectArr);
        $bucketdetail_key           =   $this->getBucketDetailKey($bucket_detail_id);
        $bucket_detail_artist_id    =   $this->getBucketDetailArtistId($buckectArr);

        $all_bucket_list_sort_sets  =   $this->getBucketListALlSortSetKeys($bucket_detail_artist_id);

        foreach ($all_bucket_list_sort_sets as $bucket_list_sort_set){
            $removed = $this->cacheClient->zrem($bucket_list_sort_set, $bucketdetail_key);
            $info   = ['sortset' => $bucket_list_sort_set, 'key' => $bucketdetail_key];
            \Log::info('removeBucketDetilFromTags  ', $info);
            \Log::info('removeBucketDetilFromTags => '.$removed);
        }

    }



    public function flushAllBucketsTags($requestData = array()){




    }






//    public function create

    public function testUsecases()
    {


        //Create Bucket Detail Key
        //Create Update Bucket Detail Key
        //Create Delete Bucket Detail Key
        //Create Bucket Tag Key
        //Delete Bucket Tag  Key
        //Check Key Exist
        //Check Tag Key Exist

//
//        \Cache::tags('ltag')->put('ltagkey', ['1' => 1 , '2' => 3], 10);
//
//
//        $results = \Cache::tags('ltag')->get('ltagkey');
//
//        var_dump($results);
//
//        $exists = $this->cacheClient->exists('foo') ? 'yes' : 'no';
//        var_dump($exists);
//        $this->cacheClient->set('foo', 'bar');
//        $value = $this->cacheClient->get('foo');
//        var_dump($value);
//
//        $exists = $this->cacheClient->exists('foo');
//        var_dump($exists);
//

//        return $this->cacheClient->welcome();








    }







}