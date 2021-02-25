<?php

namespace App\Services;

use Input, Config;
use App\Repositories\Contracts\BucketInterface;
use App\Repositories\Contracts\BucketlangInterface;
use App\Models\Bucket as Bucket;
use App\Services\Image\Kraken;
use App\Services\AwsCloudfront;
use App\Services\Cache\AwsElasticCacheRedis;
use App\Services\ArtistService;



class BucketService
{
    protected $bucket;
    protected $repObj;
    protected $repLangObj;
    protected $kraken;
    protected $awscloudfrontService;
    protected $awsElasticCacheRedis;
    protected $artistservice;

    public function __construct(
        Bucket $bucket,
        BucketInterface $repObj,
        BucketlangInterface $repLangObj,
        Kraken $kraken,
        AwsCloudfront $awscloudfrontService,
        AwsElasticCacheRedis $awsElasticCacheRedis,
        ArtistService $artistservice
    )
    {
        $this->bucket = $bucket;
        $this->repObj = $repObj;
        $this->repLangObj = $repLangObj;
        $this->kraken = $kraken;
        $this->awscloudfrontService = $awscloudfrontService;
        $this->awsElasticCacheRedis = $awsElasticCacheRedis;
        $this->artistservice = $artistservice;
    }


    public function index($artistid)
    {
        $results = $this->repObj->listing($artistid);
        return $results;
    }


    public function find($id)
    {
        $results = $this->repObj->find($id);
        return $results;
    }


    public function rootBuckets($artistid)
    {
        $error_messages = $results = [];
        $results = $this->repObj->rootBuckets($artistid);
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
        $results = $this->repObj->activelistswithslug();

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function lists($request)
    {
        $requestData = $request->all();
        $error_messages = [];
        $results    = [];
        $artist_id  = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? trim($requestData['artist_id']) : '';
        $visiblity  = (isset($requestData['visiblity']) && $requestData['visiblity'] != '') ? $requestData['visiblity'] : 'customer';
        $platform   = (isset($requestData['platform']) && $requestData['platform'] != '') ? trim($requestData['platform']) : "android";
        $page       = (isset($requestData['page']) && $requestData['page'] != '') ? trim($requestData['page']) : 1;

        $default_language = "";
        $artist_default_lang = $this->artistservice->getConfigLanguages($artist_id);
        foreach ($artist_default_lang as $key => $default_lang) {
            if($default_lang['is_default'] == true) {
                $default_language = $default_lang['code_2'];
            }
        }

        $language_code = (isset($requestData['lang']) && $requestData['lang'] != '') ? trim(strtolower($requestData['lang'])) : $default_language;
        $cacheParams                    =   [];
        $hash_name                      =   env_cache(Config::get('cache.hash_keys.bucket_lists').$artist_id.':'.$platform . ':'.$visiblity.':'.$language_code);
        $hash_field                     =   $page;
        $cache_miss                     =   false;

        $cacheParams['hash_name']       =   $hash_name;
        $cacheParams['hash_field']      =   (string) $hash_field;

        $results                        = [];//  $responses = $this->awsElasticCacheRedis->getHashData($cacheParams);

        if(empty($results)){
            $responses                          =   $this->repObj->lists($requestData);
            $items                              =   ($responses) ? apply_cloudfront_url($responses) : [];
            $cacheParams['hash_field_value']    =   $items;
            $saveToCache                        =   $this->awsElasticCacheRedis->saveHashData($cacheParams);
            $cache_miss                         =   true;
            $results                            =   $responses = $this->awsElasticCacheRedis->getHashData($cacheParams);
        }

        $results['cache']               =   ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        return ['error_messages' => $error_messages, 'results' => $results];
    }



    public function getArtistBucketListing($artist_id, $visiblity)
    {
        $error_messages = $results = [];

        $artist_id = (isset($artist_id) && $artist_id != '') ? trim($artist_id) : '';
        $visiblity = (isset($visiblity) && $visiblity != '') ? $visiblity : 'customer';

        $env_cachetag = env_cache_tag_key('bucketsold');
        $cachetag_key = $visiblity . "_" . $artist_id;
        $cache_time = Config::get('cache.1_year');

        $buckets = Cache::tags($env_cachetag)->has($cachetag_key);

        if (!$buckets) {
            $response = $this->repObj->getArtistBucketListing($artist_id, $visiblity);
            $results = apply_cloudfront_url($response);

            Cache::tags($env_cachetag)->put($cachetag_key, $results, $cache_time);
        }
        $results = Cache::tags($env_cachetag)->get($cachetag_key);
        $results['cache'] = ['tags' => $env_cachetag, 'key' => $cachetag_key, 'time' => $cache_time];

//         print_pretty($results);exit;
        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function show($id)
    {
        $error_messages = $results = [];
        if (empty($error_messages)) {
            $results['bucket'] = $this->repObj->find($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function validateSlugData($title, $artist_id) {

        $bucket_lang_data_cnt = \App\Models\Bucketlang::active()->where('artist_id', $artist_id)->where('slug', $title)->count();

        if($bucket_lang_data_cnt > 0) {
            $slug_data = $title. (intval($bucket_lang_data_cnt) + 1);
        } else {
            $slug_data = $title;
        }

        $slug_data = preg_replace('#[ -]+#', '-', $slug_data);

        return strtolower($slug_data);
    }


    public function store($request)
    {
        // var_dump($this);die;
        $data = $request->all();
        $error_messages = $results = [];
        $visiblity = (!empty($data['visiblity'])) ? $data['visiblity'] : ['customer'];
        $platform = (!empty($data['platforms'])) ? $data['platforms'] : ['android'];

        array_set($data, 'slug', str_slug($data['name']));
        array_set($data, 'visiblity', $visiblity);
        array_set($data, 'platforms', $platform);

        //upload photo
        if ($request->hasFile('photo')) {
            $parmas     =   ['file' => $request->file('photo'), 'type' => 'buckets'];
            $photo      =   $this->kraken->uploadToAws($parmas);
            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                array_set($data, 'photo', $photo['results']);
            }
        }

        if (empty($error_messages)) {
            $bucket             =   $this->repObj->store($data);

            $bucket_id = $bucket->_id;
            $artist_id = $bucket->artist_id;

            $slug = $this->validateSlugData($data['name'], $artist_id);

            $lang_data['bucket_id'] = $bucket_id;
            $lang_data['artist_id'] = $artist_id;
            $lang_data['language_id'] = $data['language_id'];
            $lang_data['title'] = $data['name'];
            $lang_data['caption'] = $data['caption'];
            $lang_data['status'] = $data['status'];
            $lang_data['is_default_language'] = true;
            $lang_data['slug'] = $slug;

            $bucketlang         =   $this->repLangObj->store($lang_data);

            $results['bucket']  =   $bucket;
            $results['bucketlang'] = $bucketlang;

            $languages          =   $this->artistservice->getArtistCode2LanguageArray($artist_id);

            array_set($data, 'languages', $languages);

            $artist_id          =   (!empty($bucket) && isset($bucket['artist_id'])) ? $bucket['artist_id'] : '';
            $purge_result       =   $this->awsElasticCacheRedis->purgeBucketListCache($data);
            $purge_result       =   $this->awsElasticCacheRedis->purgeArtistConfigCache(['artist_id' => $artist_id]);

            if (env('APP_ENV', 'stg') == 'production') {
                try {
                    $invalidate_result = $this->awscloudfrontService->invalidateBuckets();
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
        $unique_count = $this->repObj->checkUniqueOnUpdate($id, 'slug', $slug);
        // if ($unique_count > 0) {
        //     $error_messages[] = 'Bucket with name already exist : ' . str_replace("-", " ", ucwords($slug));
        // }
        array_set($data, 'slug', $slug);

        $visiblity = (!empty($data['visiblity'])) ? $data['visiblity'] : ['customer'];
        array_set($data, 'visiblity', $visiblity);


        $platform = (!empty($data['platforms'])) ? $data['platforms'] : ['android'];
        array_set($data, 'platforms', $platform);

        //upload photo
        if ($request->hasFile('photo')) {
            $parmas     =   ['file' => $request->file('photo'), 'type' => 'buckets'];
            $photo      =   $this->kraken->uploadToAws($parmas);
            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                array_set($data, 'photo', $photo['results']);
            }
        }

        // parent id cant same as bucket id value
        if (isset($data['parent_id']) &&
            $data['parent_id'] != "" &&
            $data['parent_id'] != "0" &&
            $data['parent_id'] != 0 &&
            trim($data['parent_id']) == trim($id)
        ) {
            $error_messages[] = 'Bucket parent_id cant be same bucket id for : ' . str_replace("-", " ", ucwords($slug));
        }

        if (empty($error_messages)) {


            $bucket             =   $this->repObj->update($data, $id);

            $bucket_id = $bucket->_id;
            $artist_id = $bucket->artist_id;

            $slug = $this->validateSlugData($data['name'], $artist_id);

            $lang_data['bucket_id'] = $bucket_id;
            $lang_data['artist_id'] = $artist_id;
            $lang_data['language_id'] = $data['language_id'];
            $lang_data['title'] = $data['name'];
            $lang_data['caption'] = $data['caption'];
            $lang_data['status'] = 'active'; // Bucket Lanuage status will be always active
            $lang_data['slug'] = $slug;

            $en_lang_id = '';
            $languages_data = \App\Models\Language::active()->where('name', 'English')->first();
            if(null !== $languages_data) {
                $en_lang_id = $languages_data->_id;
            }

            $bucket_lang_record_exists = \App\Models\Bucketlang::active()->where('bucket_id', $bucket_id)->get();

            if(!$bucket_lang_record_exists->isEmpty()) {
                foreach($bucket_lang_record_exists as $bucket_rec) {

                    if($bucket_rec->language_id == $en_lang_id && $data['language_id'] == $en_lang_id) {
                        $lang_data['is_default_language'] = true;
                    } else {
                        $lang_data['is_default_language'] = false;
                    }
                }
            } else {

                if($data['language_id'] == $en_lang_id) {
                   $lang_data['is_default_language'] = true;
                }
            }

            $bucketlangset = \App\Models\Bucketlang::active()->where('bucket_id', $bucket_id)->where('language_id', $data['language_id'])->first();
            if($bucketlangset) {
                $id = $bucketlangset->_id;
                $bucketlang         =   $this->repLangObj->update($lang_data, $id);
            } else {
                $bucketlang         =   $this->repLangObj->store($lang_data);
            }

            $results['bucket']  =   $bucket;
            $results['bucketlang'] = $bucketlang;

            $languages          =   $this->artistservice->getArtistCode2LanguageArray($artist_id);

            array_set($data, 'languages', $languages);

            $artist_id      =   (!empty($bucket) && isset($bucket['artist_id'])) ? $bucket['artist_id'] : '';
            $purge_result   =   $this->awsElasticCacheRedis->purgeBucketListCache($data);
            $purge_result   =   $this->awsElasticCacheRedis->purgeArtistConfigCache(['artist_id' => $artist_id]);

            if (env('APP_ENV', 'stg') == 'production') {
                try {
                    $invalidate_result = $this->awscloudfrontService->invalidateBuckets();
                } catch (Exception $e) {
                    $error_messages = [
                        'error' => true, 'type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()
                    ];
                    Log::info('AwsCloudfront - Invalidation  : invalidateBuckets Fail ', $error_messages);
                }
            }

        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function getBucketLanguage($language_id, $bucket_id) {

        $bucketlang = \App\Models\Bucketlang::active()->where('language_id', $language_id)->where('bucket_id', $bucket_id)->first();

        $results = [
            'id' => "",
            'name' => "",
            'caption' => ''
        ];

        if($bucketlang) {
            $results['id'] = $bucketlang->_id;
            $results['name'] = $bucketlang->title;
            $results['caption'] = $bucketlang->caption;
        }

        return $results;

    }


    public function destroy($id)
    {
        $results = $this->repObj->destroy($id);
        return $results;
    }

    public function artistBucketList($artist_id)
    {
        $error_messages = $results = [];
        $results = $this->repObj->artistBucketList($artist_id);

        return ['error_messages' => $error_messages, 'results' => $results];
    }



    /**
     * Return Customer Like content ids for an artist
     *
     * @param   string  $customer_id
     * @param   string  $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-20
     */
    public function storeArtistBucket($data, $artist_id) {
        $results        = [];
        $error_messages = [];

        $artist_default_lang = '';
        if(!isset($data['language_id'])) {
            // Find artist default language id
            $artist_config = $this->artistservice->getArtistConfig($artist_id);
            if($artist_config) {
                if( isset($artist_config['results']) && isset($artist_config['results']['artistconfig']) && isset($artist_config['results']['artistconfig']['artist_languages']) ) {
                    foreach ($artist_config['results']['artistconfig']['artist_languages'] as $key => $artist_language) {
                        if(isset($artist_language['is_default']) && ($artist_language['is_default'] == true) ) {
                            $artist_default_lang = isset($artist_language['_id']) ? $artist_language['_id'] : '';
                        }
                    }
                }
            }
            $data['language_id'] = $artist_default_lang;
        }

        $bucket =  $this->repObj->store($data);

        $bucket_id = $bucket->_id;
        $artist_id = $bucket->artist_id;

        $slug = $this->validateSlugData($data['name'], $artist_id);

        $lang_data['bucket_id']     = $bucket_id;
        $lang_data['artist_id']     = $artist_id;
        $lang_data['language_id']   = $data['language_id'];
        $lang_data['title']         = $data['name'];
        $lang_data['caption']       = $data['caption'];
        $lang_data['status']        = $data['status'];
        $lang_data['is_default_language'] = true;
        $lang_data['slug']          = $slug;

        $bucketlang                 = $this->repLangObj->store($lang_data);

        $results['bucket']          = $bucket;
        $results['bucketlang']      = $bucketlang;

        return ['error_messages' => $error_messages, 'results' => $results];
    }

}
