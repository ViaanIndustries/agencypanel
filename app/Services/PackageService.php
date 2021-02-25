<?php

namespace App\Services;

use Input;
use Redirect;
use Config;
use Session;
use Cache;
use App\Repositories\Contracts\PackageInterface;

use App\Models\Package as Package;
use App\Services\Image\Kraken;
use App\Services\Jwtauth;
use App\Services\AwsCloudfront;
use App\Services\Cache\AwsElasticCacheRedis;

class PackageService
{
    protected $repObj;
    protected $package;
    protected $jwtauth;
    protected $kraken;
    protected $awscloudfrontService;
    protected $awsElasticCacheRedis;

    public function __construct(
        Package $package,
        PackageInterface $repObj,
        Jwtauth $jwtauth,
        Kraken $kraken,
        AwsCloudfront $awscloudfrontService,
        AwsElasticCacheRedis $awsElasticCacheRedis
    )
    {
        $this->package = $package;
        $this->repObj = $repObj;
        $this->jwtauth = $jwtauth;
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
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? trim(strtolower($requestData['platform'])) : 'android';

        $cacheParams = [];
        $hash_name      =   env_cache(Config::get('cache.hash_keys.packages_lists').$artist_id.":".$platform);
        $hash_field     =   $artist_id;
        $cache_miss     =   false;

        $cacheParams['hash_name']   =  $hash_name;
        $cacheParams['hash_field']  =  (string)$hash_field;
        $cacheParams['expire_time'] =  Config::get('cache.1_month') * 60;


        $packages = $responses = $this->awsElasticCacheRedis->getHashData($cacheParams);
        if (empty($packages)) {
            $responses = $this->repObj->lists($requestData);
            $items = ($responses) ? apply_cloudfront_url($responses) : [];
            $cacheParams['hash_field_value'] = $items;
            $saveToCache = $this->awsElasticCacheRedis->saveHashData($cacheParams);
            $cache_miss = true;
            $packages  = $this->awsElasticCacheRedis->getHashData($cacheParams);
        }

        $results['list']    = $packages;
        $results['cache']    = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];


        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function pyatmpackages($request)
    {
        $requestData = $request->all();
        $error_messages = [];
        $results = [];
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? trim($requestData['artist_id']) : '';
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? trim(strtolower($requestData['platform'])) : 'android';

        $cacheParams = [];
        $hash_name      =   env_cache(Config::get('cache.hash_keys.paytmpackages_lists').$artist_id.":".$platform);
        $hash_field     =   $artist_id;
        $cache_miss     =   false;

        $cacheParams['hash_name']   =  $hash_name;
        $cacheParams['hash_field']  =  (string)$hash_field;
        $cacheParams['expire_time'] =  Config::get('cache.1_month') * 60;


        $packages = $responses = $this->awsElasticCacheRedis->getHashData($cacheParams);
        if (empty($packages)) {
            $responses = $this->repObj->pyatmpackages($requestData);
            $items = ($responses) ? apply_cloudfront_url($responses) : [];
            $cacheParams['hash_field_value'] = $items;
            $saveToCache = $this->awsElasticCacheRedis->saveHashData($cacheParams);
            $cache_miss = true;
            $packages  = $this->awsElasticCacheRedis->getHashData($cacheParams);
        }

        $results['list']    = $packages;
        $results['cache']    = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];



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

    public function getPackageListing($request)
    {
        $error_messages = $results = [];
        $response = $this->repObj->getPackageListing($request);
        // $results        =   ($response);
        $results = apply_cloudfront_url($response);
        // var_dump($results);exit;
        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function find($id)
    {
        $results = $this->repObj->find($id);
        return $results;
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

        //upload photo
        if ($request->hasFile('photo')) {
            $parmas     =   ['file' => $request->file('photo'), 'type' => 'packages'];
            $photo      =   $this->kraken->uploadToAws($parmas);
            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                array_set($data, 'photo', $photo['results']);
            }
        }


//        if (empty($error_messages)) {

//        $already_exist = [];
        $already_exist = \App\Models\Package::select()->where('coins', (int)$data['coins'])->whereIn('platforms', $data['platforms'])->whereIn('artists', $data['artists'])->first();

        if (empty($already_exist)) {

            $results['package'] = $this->repObj->store($data);
            $artist_id      =   $results['package']['artists'][0];
            $purge_result   =   $this->awsElasticCacheRedis->purgePackageListCache(['artist_id' => $artist_id]);
            $purge_result   =   $this->awsElasticCacheRedis->purgePaytmPackageListCache(['artist_id' => $artist_id]);
            $purge_result   =   $this->awsElasticCacheRedis->purgeArtistConfigCache(['artist_id' => $artist_id]);

        } else {
            $error_messages[] = 'Package name ' .$data['name'].' already exist having '.$data['coins'].' coins with platforms '.implode(",",$data['platforms']).' for this artist ';
        }
//        }


        if (env('APP_ENV', 'stg') == 'production') {
            try {
                $invalidate_result = $this->awscloudfrontService->invalidatePackages();
            } catch (Exception $e) {
                $error_messages = [
                    'error' => true,
                    'type' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ];
                Log::info('AwsCloudfront - Invalidation  : Fail ', $error_messages);
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

        //upload photo
        if ($request->hasFile('photo')) {
            $parmas     =   ['file' => $request->file('photo'), 'type' => 'packages'];
            $photo      =   $this->kraken->uploadToAws($parmas);
            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                array_set($data, 'photo', $photo['results']);
            }
        }

        $already_exist = \App\Models\Package::select('_id')->where('coins', (int)$data['coins'])->whereIn('artists', $data['artists'])->whereIn('platforms', $data['platforms'])->whereNotIn('_id', [$id])->first();

        if (empty($already_exist)) {
            $results['package'] = $this->repObj->update($data, $id);

            $platform = $results['package']['platforms'];
            $artist_id = $results['package']['artists'][0];

            $purge_result   =   $this->awsElasticCacheRedis->purgePackageListCache(['artist_id' => $artist_id]);
            $purge_result   =   $this->awsElasticCacheRedis->purgePaytmPackageListCache(['artist_id' => $artist_id]);
            $purge_result   =   $this->awsElasticCacheRedis->purgeArtistConfigCache(['artist_id' => $artist_id]);

        }else{
            $error_messages[] = 'Package name ' .$data['name'].' already exist having '.$data['coins'].' coins with platforms '.implode(",",$data['platforms']).' for this artist ';
        }


        if (env('APP_ENV', 'stg') == 'production') {
            try {
                $invalidate_result = $this->awscloudfrontService->invalidatePackages();
            } catch (Exception $e) {
                $error_messages = [
                    'error' => true,
                    'type' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ];
                Log::info('AwsCloudfront - Invalidation  : Fail ', $error_messages);
            }
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function destroy($id)
    {
        $results = $this->repObj->destroy($id);
        return $results;
    }


}