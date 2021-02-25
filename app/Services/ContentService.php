<?php

namespace App\Services;

use Carbon\Carbon;
use Input;
use Config;
use League\Flysystem\Exception;
use Session;
use Request;
use Log;
use Storage;
use Cache;

use Facebook;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookAuthenticationException;

use App\Repositories\Contracts\ContentInterface;
use App\Services\Gcp;
use App\Services\Aws;
use App\Services\Notifications\PushNotification;
use App\Services\SocialShare\Fb;
use App\Services\SocialShare\Twitter;
use App\Services\SocialShare\Instagram;
use App\Services\Jwtauth;
use App\Services\CachingService;
use Predis\Client as PredisClient;
use App\Services\RedisDb;
use App\Services\Image\Kraken;

use App\Models\Contentlang;

use \App\Services\AwsCloudfront;
use App\Services\Cache\AwsElasticCacheRedis;

use App\Services\LanguageService;
use App\Services\ArtistService;

use App\Repositories\Contracts\PurchaseInterface;
use Aws\S3\S3Client;

use App\Transformer\ContentTransformer;


class ContentService
{
    protected $repObj;
    protected $jwtauth;
    protected $gcp;
    protected $fb;
    protected $tw;
    protected $aws;
    protected $twitter;
    protected $instagram;
    protected $pushnotification;
    protected $caching;
    protected $redisdb;
    protected $kraken;
    protected $awscloudfrontService;
    protected $awsElasticCacheRedis;
    protected $languageService;
    protected $artistservice;
    protected $transformer;


    public function __construct(
        ContentInterface $repObj,
        Jwtauth $jwtauth,
        Gcp $gcp,
        Aws $aws,
        PushNotification $pushnotification,
        Fb $fb,
        Twitter $twitter,
        Instagram $instagram,
        CachingService $caching,
        RedisDb $redisdb,
        Kraken $kraken,
        PurchaseInterface $purchaseRepObj,
        AwsCloudfront $awscloudfrontService,
        AwsElasticCacheRedis $awsElasticCacheRedis,
        LanguageService $languageService,
        ArtistService $artistservice,
        ContentTransformer $transformer
    )
    {
        $this->repObj = $repObj;
        $this->jwtauth = $jwtauth;
        $this->gcp = $gcp;
        $this->aws = $aws;
        $this->pushnotification = $pushnotification;
        $this->fb = $fb;
        $this->twitter = $twitter;
        $this->instagram = $instagram;
        $this->caching = $caching;
        $this->redisdb = $redisdb;
        $this->kraken = $kraken;
        $this->awscloudfrontService = $awscloudfrontService;
        $this->purchaseRepObj = $purchaseRepObj;
        $this->awsElasticCacheRedis = $awsElasticCacheRedis;
        $this->languageService = $languageService;
        $this->artistservice = $artistservice;
        $this->transformer = $transformer;
    }


    public function listing($bucket_id, $level, $request)
    {
        $error_messages = $results = [];

        if (empty($error_messages)) {
            $results = $this->repObj->listing($bucket_id, $level, $request);
        }

        return ['error_messages' => $error_messages, 'results' => $results];

    }

    public function photoListing($id, $request)
    {
        $error_messages = $results = [];

        if (empty($error_messages)) {
            $results = $this->repObj->photoListing($id, $request);
        }

        return ['error_messages' => $error_messages, 'results' => $results];

    }

    public function videoListing($id)
    {
        $error_messages = $results = [];

        if (empty($error_messages)) {
            $results['contents'] = $this->repObj->videoListing($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];

    }

    public function audioListing($id)
    {
        $error_messages = $results = [];

        if (empty($error_messages)) {
            $results['contents'] = $this->repObj->audioListing($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];

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

    public function getArtistContentListing($artist_id)
    {
        $error_messages = $results = [];
        $results = $this->repObj->getArtistContentListing($artist_id);

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function find($id)
    {
        $results = $this->repObj->find($id);
        return $results;
    }

    public function getRecentPostforArtist($request)
    {
        $error_messages = $results = [];
        $results = $this->repObj->getRecentPostforArtist($request);
        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function show($id, $language = null)
    {
        $error_messages = $results = [];


        /*
         // OLD

        $cachetag_name = $id . "_contentdetails";
        $env_cachetag = env_cache_tag_key($cachetag_name);              //ENV_contentdetails
        $cachetag_key = $id;                                            //PAGENO_VISIBILITY_PARENTID
        $cache_time = Config::get('cache.cache_time');

        $contents = Cache::tags($env_cachetag)->has($cachetag_key);
        if (!$contents) {
            $responses = $this->repObj->find($id); //taking time while loading each n everytime
            $items = ($responses) ? $responses : [];
            $items = apply_cloudfront_url($items);
            Cache::tags($env_cachetag)->put($cachetag_key, $items, $cache_time);
        }

        $results['cache'] = ['tags' => $env_cachetag, 'key' => $cachetag_key];
        $results['content'] = Cache::tags($env_cachetag)->get($cachetag_key);

        */


        $cacheParams = [];
        $hash_name      =   env_cache(Config::get('cache.hash_keys.content_detail').$id);
        $hash_field     =   $id;
        $cache_miss     =   false;

        $cacheParams['hash_name']   =  $hash_name;
        $cacheParams['hash_field']  =  (string)$hash_field;

        /*
        // Old Code without parent_content
        $content = $responses = $this->awsElasticCacheRedis->getHashData($cacheParams);
        if (empty($content)) {
            $responses = $this->repObj->find($id);
            $items = ($responses) ? apply_cloudfront_url($responses) : [];
            $cacheParams['hash_field_value'] = $items;
            $saveToCache = $this->awsElasticCacheRedis->saveHashData($cacheParams);
            $cache_miss = true;
            $content  = $this->awsElasticCacheRedis->getHashData($cacheParams);
        }
        */

        $results  = $this->awsElasticCacheRedis->getHashData($cacheParams);
        if (empty($results)) {
            $responses                          =   $this->repObj->show($id, $language);
            $items                              =   ($responses) ? apply_cloudfront_url($responses) : [];
            $cacheParams['hash_field_value']    =   $items;
            $saveToCache                        =   $this->awsElasticCacheRedis->saveHashData($cacheParams);
            $cache_miss                         =   true;
            $results                            =   $this->awsElasticCacheRedis->getHashData($cacheParams);
        }

        $results['cache']    = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];


        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function lists($request)
    {
        $requestData                    =   $request->all();
        $error_messages                 =   [];
        $results                        =   [];
        $artist_id                      =   (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? trim($requestData['artist_id']) : '';
        $bucket_id                      =   (isset($requestData['bucket_id']) && $requestData['bucket_id'] != '') ? trim($requestData['bucket_id']) : '';
        $bucket_slug                    =   (isset($requestData['bucket_slug']) && $requestData['bucket_slug'] != '') ? trim($requestData['bucket_slug']) : '';
        $visiblity                      =   (isset($requestData['visiblity']) && $requestData['visiblity'] != '') ? trim($requestData['visiblity']) : 'customer';
        $page                           =   (isset($requestData['page']) && $requestData['page'] != '') ? trim($requestData['page']) : '1';
        $parent_id                      =   (isset($requestData['parent_id']) && $requestData['parent_id'] != '') ? trim($requestData['parent_id']) : '';
        $parent_slug                    =   (isset($requestData['parent_slug']) && $requestData['parent_slug'] != '') ? trim($requestData['parent_slug']) : '';
        $platform                       =   (isset($requestData['platform']) && $requestData['platform'] != '') ? trim($requestData['platform']) : 'android';
        $platform_version               =   (!empty($requestData['v'])) ? strtolower(trim($requestData['v'])) : '';
        $test_app_platorm_version       =   (!empty(Config::get('app.artist_test_build_version.'.$artist_id.'.'.$platform))) ? strtolower(trim(Config::get('app.artist_test_build_version.'.$artist_id.'.'.$platform))) : "";

        if ($platform == '') {
            $platform = (request()->header('platform')) ? trim(request()->header('platform')) : "";
        }

        $default_language = "";
        $artist_default_lang = $this->artistservice->getConfigLanguages($artist_id);
        foreach ($artist_default_lang as $key => $default_lang) {
            if($default_lang['is_default'] == true) {
                $default_language = $default_lang['code_2'];
            }
        }

        $language_code = (isset($requestData['lang']) && $requestData['lang'] != '') ? trim(strtolower($requestData['lang'])) : $default_language;

        $language_id = '';
        $language_data = \App\Models\Language::active()->where('code_2', $language_code)->first();
        if(!empty($language_data)) {
            $language_id = $language_data->_id;
        }

        // Incase Bucket ID is not provide
        // But Bucket Slug is provide
        // Then find Bucket ID base on Bucket Slug
        if(!$bucket_id && $bucket_slug) {
            $bucket = $this->getContentBucketIdBySlug($bucket_slug, $artist_id, $language_id);
            if (!empty($bucket) && !empty($bucket['results']['bucket_id'])) {
                $bucket_id = $bucket['results']['bucket_id'];
                $requestData['bucket_id'] = $bucket_id;
            }
        }
        if(!$bucket_id) {
            $error_messages[] = 'Bucket detail not found';
            return ['error_messages' => $error_messages, 'results' => $results];
        }

        // Incase Content Parent ID is not provided
        // But Content Parent Slug is provided
        // Then find Content Parent ID base on provided Content Parent Slug
        if(!$parent_id && $parent_slug) {
            $content_parent = $this->getContentParentIdBySlug($parent_slug, $bucket_id);
            if (!empty($content_parent) && !empty($content_parent['results']['parent_id'])) {
                $parent_id = $content_parent['results']['parent_id'];
                $requestData['parent_id'] = $parent_id;
            }
        }

        $cacheParams    = [];
        $hash_name      = env_cache(Config::get('cache.hash_keys.content_lists') . $bucket_id . ':' . $platform . ':' . $visiblity . ':' . $parent_id . ':' .$language_code);
        $hash_field     = $page;
        $cache_miss     = false;

        $cacheParams['hash_name']       =   $hash_name;
        $cacheParams['hash_field']      =   (string)$hash_field;
        $cacheParams['expire_time']     =   Config::get('cache.15_min') * 60;
        $is_test_enable                 =   'false';

        // For Is Test Reivew manipulations
        if($test_app_platorm_version && ($platform_version == $test_app_platorm_version)) {
            $is_test_enable                   =   "true";
            $requestData['is_test_enable']    =   $is_test_enable;
            $responses                        =   $this->repObj->lists($requestData);
            $items                            =   ($responses) ? apply_cloudfront_url($responses) : [];
            $results                          =   $items;
            $cache_miss                       =   true;

        } else {

            $results  = $this->awsElasticCacheRedis->getHashData($cacheParams);
            if (empty($results)) {
                $responses                          =   $this->repObj->lists($requestData);
                $items                              =   ($responses) ? apply_cloudfront_url($responses) : [];
                $cacheParams['hash_field_value']    =   $items;
                $saveToCache                        =   $this->awsElasticCacheRedis->saveHashData($cacheParams);
                $cache_miss                         =   true;
                $results                            =   $this->awsElasticCacheRedis->getHashData($cacheParams);
            }
        }

        $results['cache']                       =   ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];
        $results['test_app_platorm_version']    =   $test_app_platorm_version;
        $results['platform_version']            =   $platform_version;
        $results['is_test_enable']              =   $is_test_enable;

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    function formatSizeUnits($bytes)
    {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }

    public function store($request)
    {
        $data = array_except($request->all(), ['on_facebook', 'on_twitter', 'on_instagram', '_token']);

        $data['platforms'] = ['android', 'ios', 'web'];
        $error_messages = $results = [];

        $slug = (isset($data['name'])) ? str_slug($data['name']) : '';
        $send_notification = (isset($data['send_notification']) && $data['send_notification'] != '') ? (string)$data['send_notification'] : "false";
        $artist_id = $data['artist_id'];

        $bucket_id = (isset($data['bucket_id']) && $data['bucket_id'] != '') ? trim($data['bucket_id']) : '';

        $bucket = \App\Models\Bucket::where('_id', '=', $bucket_id)->first();
        $bucket_code = (isset($bucket) & isset($bucket['code']) && $bucket['code'] != '') ? trim($bucket['code']) : "";
        array_set($data, 'bucket_code', $bucket_code);

        array_set($data, 'slug', $slug);

        if (isset($request['on_facebook']) && $request['on_facebook'] == 1) {
            array_set($data, 'share_on_facebook', 'scheduled');
        }

        if (isset($request['on_twitter']) && $request['on_twitter'] == 1) {
            array_set($data, 'share_on_twitter', 'scheduled');
        }

        if (isset($request['on_instagram']) && $request['on_instagram'] == 1) {
            array_set($data, 'share_on_instagram', 'scheduled');
        }

        // ini_set('memory_limit','1000M');

        if ($request->file('video')) {

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
            $s3 = Storage::createS3Driver(Config::get('product.' . env('PRODUCT') . '.s3.rawvideos'));
            if (env('APP_ENV', 'stg') != 'local') {
                $response = $s3->put($object_upload_path, file_get_contents($object_source_path), 'public');
            }


            $vod_job_data = ['status' => 'submitted', 'object_name' => $imageName, 'object_path' => $object_upload_path, 'object_extension' => $obj_extension, 'bucket' => 'armsrawvideos'];
            array_set($data, 'vod_job_data', $vod_job_data);
            array_set($data, 'video_status', 'uploaded');

            @unlink($fullpath);
        }

        if ($request->file('audio')) {

            //upload to local drive
            $upload = $request->file('audio');
            $folder_path = 'uploads/contents/audio/';
            $obj_path = public_path($folder_path);
            $obj_extension = $upload->getClientOriginalExtension();
            $imageName = time() . '_' . str_slug($upload->getRealPath()) . '.' . $obj_extension;
            $fullpath = $obj_path . $imageName;
            $upload->move($obj_path, $imageName);
            chmod($fullpath, 0777);

            //upload to aws
            $object_source_path = $fullpath;
            $object_upload_path = 'artistkyc/'.$imageName;
            $s3 = Storage::createS3Driver(Config::get('product.' . env('PRODUCT') . '.s3.rawaudios'));
            if (env('APP_ENV', 'stg') != 'local') {
                $response = $s3->put($object_upload_path, file_get_contents($object_source_path), 'public');
            }

            $aod_job_data = ['status' => 'submitted', 'object_name' => $imageName, 'object_path' => $object_upload_path, 'object_extension' => $obj_extension, 'bucket' => 'armsrawaudios'];
            array_set($data, 'aod_job_data', $aod_job_data);
            array_set($data, 'audio_status', 'uploaded');

            @unlink($fullpath);

        }

        if ($request->hasFile('photo')) {

//------------------------------------Kraken Image Compression--------------------------------------------

            $parmas = ['file' => $request->file('photo'), 'type' => 'contents'];
            $kraked_img = $this->kraken->uploadKrakenImageToGCP($parmas);
            $photo = $kraked_img;
            array_set($data, 'photo', $photo);

//------------------------------------Kraken Image Compression--------------------------------------------

        }

        if (isset($data['content_types'])) {
            if ($data['content_types'] == 'photos') { // Photos Content Type
                if (isset($data['video'])) {
                    unset($data['video']);
                    unset($data['vod_job_data']);
                    unset($data['video_status']);
                }
                if (isset($data['audio'])) {
                    unset($data['audio']);
                    unset($data['aod_job_data']);
                    unset($data['audio_status']);
                    unset($data['duration']);
                }


            } elseif ($data['content_types'] == 'videos') { // Videos Content Type
                if (isset($data['audio'])) {
                    unset($data['audio']);
                    unset($data['aod_job_data']);
                    unset($data['audio_status']);
                    unset($data['duration']);
                }


            } elseif ($data['content_types'] == 'audios') { // Audios Content Type
                if (isset($data['video'])) {
                    unset($data['video']);
                    unset($data['vod_job_data']);
                    unset($data['video_status']);
                }


            } else {
                if (isset($data['video'])) { // Polls Content Type
                    unset($data['video']);
                    unset($data['vod_job_data']);
                    unset($data['video_status']);
                }
                if (isset($data['audio'])) {
                    unset($data['audio']);
                    unset($data['aod_job_data']);
                    unset($data['audio_status']);
                    unset($data['duration']);
                }
            }
        }

        if (empty($error_messages)) {

            $content = $this->repObj->store($data);

            foreach ($data['platforms'] as $key => $platform) {
                $cachetag_name = $platform . '_' . $bucket_id . "_contents";
                $env_cachetag = env_cache_tag_key($cachetag_name);              //  ENV_PLATFORM_BUCKETID_contents

                $this->caching->flushTag($env_cachetag);
            }

            $results['content'] = $content;
            $content_id = $content->_id;

////==========================================Transcode Video=============================================================

//            //Push to Queue Job For transcode video
//            if ($content && isset($content['video_status']) && $content['video_status'] == 'uploaded') {
//                $payload = ['content_id' => $content_id, 'send_notification' => $send_notification];
//                $payload = array_merge($payload, $content['vod_job_data']);
//                $jobData = [
//                    'label' => 'CreateHLSTranscodeJobForVideo',
//                    'type' => 'transcode_video',
//                    'payload' => $payload,
//                    'status' => "scheduled",
//                    'delay' => 0,
//                    'retries' => 0,
//                ];
//                $recodset = new \App\Models\Job($jobData);
//                $recodset->save();
//                $send_notification = 'false'; //incase of video
//            }

////==========================================Transcode Video=============================================================

//==========================================Mediaconvert Video==========================================================
            //Push to Queue Job For transcode media convert
            if ($content && isset($content['video_status']) && $content['video_status'] == 'uploaded') {

                $payload = [
                    'content_id' => $content_id,
                    'send_notification' => $send_notification,
                ];

                $payload = array_merge($payload, $content['vod_job_data']);

                $jobData = [
                    'label' => 'CreateHLSTranscodeJobForMediaconvert',
                    'type' => 'transcode_mediaconvert',
                    'payload' => $payload,
                    'status' => "scheduled",
                    'delay' => 0,
                    'retries' => 0,
                ];

                $recodset = new \App\Models\Job($jobData);
                $recodset->save();
                $send_notification = 'false'; //incase of video
            }
//==========================================Mediaconvert Video==========================================================

//==========================================Transcode Audio=============================================================
            //Push to Queue Job For transcode audio
            if ($content && isset($content['audio_status']) && $content['audio_status'] == 'uploaded') {
                $payload = ['content_id' => $content_id, 'send_notification' => $send_notification];
                $payload = array_merge($payload, $content['aod_job_data']);
                $jobData = [
                    'label' => 'CreateHLSTranscodeJobForAudio',
                    'type' => 'transcode_audio',
                    'payload' => $payload,
                    'status' => "scheduled",
                    'delay' => 0,
                    'retries' => 0,
                ];
                $recodset = new \App\Models\Job($jobData);
                $recodset->save();
                $send_notification = 'false'; //incase of audio
            }
//==========================================Transcode Audio=============================================================

            //Send Notification ONLY OF PHOTOS
            if ($send_notification == 'true') {
                $test = (env('APP_ENV', 'stg') == 'production') ? "false" : "true";
                $artist = \App\Models\Cmsuser::with('artistconfig')->where('_id', '=', $artist_id)->first();

                if ($artist) {
                    $test_topic_id = (isset($artist['artistconfig']['fmc_default_topic_id_test']) && $artist['artistconfig']['fmc_default_topic_id_test'] != '') ? trim($artist['artistconfig']['fmc_default_topic_id_test']) : "";
                    $production_topic_id = (isset($artist['artistconfig']['fmc_default_topic_id']) && $artist['artistconfig']['fmc_default_topic_id'] != '') ? trim($artist['artistconfig']['fmc_default_topic_id']) : "";
                    $artistname = $artist->first_name . ' ' . $artist->last_name;
                    $topic_id = ($test == 'true') ? $test_topic_id : $production_topic_id;
                    $deeplink = $bucket_code;

                    $type = "photo";
                    if (isset($data['player_type']) && isset($data['player_type'])) {
                        if (isset($data['embed_code']) && $data['embed_code'] != "" || isset($data['url']) && $data['url'] != "") {
                            $type = "video";
                        }
                    }

                    $title = "The " . ucwords($artistname) . " Offical App";
                    $body = ucwords($artistname) . " has posted a " . $type;

                    $notificationParams = [
                        'artist_id' => $artist_id, 'topic_id' => $topic_id, 'deeplink' => $deeplink, 'content_id' => $content_id, 'title' => $title, 'body' => $body,
                    ];
                    $sendNotification = $this->pushnotification->sendNotificationToTopic($notificationParams);
                }
            }// $send_notification

            if (isset($request['on_facebook']) && $request['on_facebook'] == 1) {

                $payload = [
                    'content_id' => $content_id,
                    'artist_id' => $artist_id
                ];

                $jobData = [
                    'label' => 'ShareContentOnFacebook',
                    'type' => 'social_share',
                    'payload' => $payload,
                    'status' => "scheduled",
                    'delay' => 0,
                    'retries' => 0
                ];
                $recodset = new \App\Models\Job($jobData);
                $recodset->save();

            }

            if (isset($request['on_twitter']) && $request['on_twitter'] == 1) {

                $payload = [
                    'content_id' => $content_id,
                    'artist_id' => $artist_id
                ];

                $jobData = [
                    'label' => 'ShareContentOnTwitter',
                    'type' => 'social_share',
                    'payload' => $payload,
                    'status' => "scheduled",
                    'delay' => 0,
                    'retries' => 0
                ];
                $recodset = new \App\Models\Job($jobData);
                $recodset->save();
            }

            if (isset($request['on_instagram']) && $request['on_instagram'] == 1) {

                $payload = [
                    'content_id' => $content_id,
                    'artist_id' => $artist_id
                ];

                $jobData = [
                    'label' => 'ShareContentOnInstagram',
                    'type' => 'social_share',
                    'payload' => $payload,
                    'status' => "scheduled",
                    'delay' => 0,
                    'retries' => 0
                ];

                $recodset = new \App\Models\Job($jobData);
                $recodset->save();

            }
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function postOnPage($facebook, $destination_url, $payload, $fb_page_access_token)
    {
        $error_messages = [];
        try {
            $response = $facebook->post(
                $destination_url,
                $payload,
                $fb_page_access_token
            );

        } catch (Facebook\Exceptions\FacebookAuthenticationException $e) {
            $error_messages[] = $e->getMessage();

        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            $error_message[] = $e->getMessage();

        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            $error_message[] = $e->getMessage();

        } catch (Exception $e) {

            $error_messages[] = $e->getMessage();
        }

        return ['error_messages' => $error_messages, 'results' => $response];
    }


    public function update($request)
    {
        $data = array_except($request->all(), ['on_facebook', 'on_twitter', 'on_instagram', '_method', '_token']);


        \Log::info('Update Content LOGGER - request_payload  ', $data);

        $error_messages = $results = [];
        $content_id = $data['content_id'];
        $type = (isset($data['type']) && $data['type'] != '') ? trim($data['type']) : 'photo';

        $commercial_type = (isset($data['commercial_type']) && $data['commercial_type'] != '') ? trim($data['commercial_type']) : 'free';
        $coins = (isset($data['coins']) && $data['coins'] != '') ? trim($data['coins']) : 0;
        $contentObj = \App\Models\Content::where('_id', '=', $content_id)->first();

        if (!$contentObj) {
            $error_messages[] = 'Content does not exist';
        }
        $artist_id = $data['artist_id'];
        $bucket_id = $contentObj['bucket_id'];
        $bucket = \App\Models\Bucket::where('_id', '=', $bucket_id)->first();
        $bucket_code = (isset($bucket) & isset($bucket['code']) && $bucket['code'] != '') ? trim($bucket['code']) : "";
        array_set($data, 'bucket_code', $bucket_code);

        $slug = (isset($data['source'])) ? str_slug($data['name']) : '';
        array_set($data, 'slug', $slug);

        $published_at = (isset($data['published_at']) && $data['published_at'] != '') ? hyphen_date($data['published_at']) : '';

        if (!empty($published_at)) {
            $published_at = new \MongoDB\BSON\UTCDateTime(strtotime($published_at) * 1000);
            $data['published_at'] = $published_at;
        }
//        else {
//            $data['published_at'] = Carbon::now();
//        }

        if ($type == 'poll') {
            $expired_at = (isset($data['expired_at']) && $data['expired_at'] != '') ? hyphen_date($data['expired_at']) : '';
            $expired_at = $expired_at . ' 23:59:00';

            if (!empty($expired_at)) {
                $expired_at = new \MongoDB\BSON\UTCDateTime(strtotime($expired_at) * 1000);
                $data['expired_at'] = $expired_at;
            } else {
                $data['expired_at'] = Carbon::now();
            }
        }

//        if (isset($request['on_facebook']) && $request['on_facebook'] == 1) {
//            array_set($data, 'share_on_facebook', 'scheduled');
//        }
//        if (isset($request['on_twitter']) && $request['on_twitter'] == 1) {
//            array_set($data, 'share_on_twitter', 'scheduled');
//        }
//        if (isset($request['on_instagram']) && $request['on_instagram'] == 1) {
//            array_set($data, 'share_on_instagram', 'scheduled');
//        }
//        if ($contentObj && isset($contentObj['parent_id']) && $contentObj['parent_id'] != '') {
//            $parentContentObj = \App\Models\Content::where('_id', '=', $contentObj['parent_id'])->first();
//            $parent_id = trim($parentContentObj['_id']);
//            if ($contentObj && isset($parentContentObj['commercial_type']) && $parentContentObj['commercial_type'] != '') {
//                $commercial_type = $parentContentObj['commercial_type'];
//            }
//        }
        array_set($data, 'commercial_type', $commercial_type);
        array_set($data, 'coins', $coins);

        if ($request->file('video')) {
            /* OLD CODE
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
            $s3 = Storage::createS3Driver(Config::get('product.' . env('PRODUCT') . '.s3.rawvideos'));
            $response = $s3->put($object_upload_path, file_get_contents($object_source_path), 'public');

            $vod_job_data = ['status' => 'submitted', 'object_name' => $imageName, 'object_path' => $object_upload_path, 'object_extension' => $obj_extension, 'bucket' => 'armsrawvideos'];
            array_set($data, 'vod_job_data', $vod_job_data);
            array_set($data, 'video_status', 'uploaded');

            @unlink($fullpath);
            */

            $video_file = $data['video'];
            $video_file->video_lang_default = isset($val['video_lang_default']) ? $val['video_lang_default'] : false;
            $video_file->video_lang         = isset($val['video_lang']) ? $val['video_lang'] : 'eng';
            $video_file->video_lang_label   = isset($val['video_lang_label']) ? $val['video_lang_label'] : 'ENGLISH';
            $vod_job_data = $this->uploadContentVideoFile($video_file);

            if($vod_job_data) {
                $data['vod_job_data'] = [];
                if(isset($contentObj['vod_job_data'])) {
                    $data['vod_job_data'] = $contentObj['vod_job_data'];

                    $data['vod_job_data'] = $this->repObj->findAndUpdateVodJobDataCollection($vod_job_data, $data['vod_job_data']);
                }

                if(isset($contentObj['video'])) {
                    $data['video'] = $this->repObj->findAndUpdateVideoObject($vod_job_data, $contentObj['video']);
                }
            }
        }

        if ($request->file('audio')) {

            //upload to local drive
            $upload = $request->file('audio');
            $folder_path = 'uploads/contents/audio/';
            $obj_path = public_path($folder_path);
            $obj_extension = $upload->getClientOriginalExtension();
            $imageName = time() . '_' . str_slug($upload->getRealPath()) . '.' . $obj_extension;
            $fullpath = $obj_path . $imageName;
            $upload->move($obj_path, $imageName);
            chmod($fullpath, 0777);

            //upload to aws
            $object_source_path = $fullpath;
            $object_upload_path = $imageName;
            $s3 = Storage::createS3Driver(Config::get('product.' . env('PRODUCT') . '.s3.rawaudios'));
            if (env('APP_ENV', 'stg') != 'local') {
                $response = $s3->put($object_upload_path, file_get_contents($object_source_path), 'public');
            }

            $aod_job_data = ['status' => 'submitted', 'object_name' => $imageName, 'object_path' => $object_upload_path, 'object_extension' => $obj_extension, 'bucket' => 'armsrawaudios'];
            array_set($data, 'aod_job_data', $aod_job_data);
            array_set($data, 'audio_status', 'uploaded');


            @unlink($fullpath);
        }

        if ($request->hasFile('photo')) {

//------------------------------------Kraken Image Compression--------------------------------------------
            $photo_obj      =   [];
            $add_watermark  =   "true";
            $parmas         =   ['file' => $request->file('photo'), 'type' => 'contents', 'add_watermark' => $add_watermark, 'artist_id' => $artist_id];
            $photo          =   $this->kraken->uploadToAws($parmas);
            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                $photo_obj  = $photo['results'];
            }
            $photo          =   $photo_obj;
            array_set($data, 'photo', $photo);
//------------------------------------Kraken Image Compression--------------------------------------------

        }

        if (isset($data['content_types'])) {

            if ($data['content_types'] == 'photos') {

                if (isset($data['video'])) {
                    unset($data['video']);
                    unset($data['vod_job_data']);
                    unset($data['video_status']);
                }

                if (isset($data['audio'])) {
                    unset($data['audio']);
                    unset($data['aod_job_data']);
                    unset($data['audio_status']);
                    unset($data['duration']);
                }
            } elseif ($data['content_types'] == 'videos') {
                if (isset($data['audio'])) {
                    unset($data['audio']);
                    unset($data['aod_job_data']);
                    unset($data['audio_status']);
                    unset($data['duration']);
                }
            } else {
                if (isset($data['video'])) {
                    unset($data['video']);
                    unset($data['vod_job_data']);
                    unset($data['video_status']);
                }
            }
        }



//        Log::info('Update Data Request Payload  :  ', $data);

        if (empty($error_messages)) {

             $content = $this->repObj->update($data, $content_id);

            $languages          =   $this->artistservice->getArtistCode2LanguageArray($artist_id);

            $parent_id = (!empty($content['parent_id']) && $content['parent_id'] != '') ? trim($content['parent_id']) : "";
            $purge_result = $this->awsElasticCacheRedis->purgeContentListCache(['content_id' => $content_id, 'bucket_id' => $bucket_id, 'parent_id' => $parent_id, 'languages' => $languages]);
            $purge_result = $this->awsElasticCacheRedis->purgeContentDetailCache(['content_id' => $content_id, 'bucket_id' => $bucket_id, 'parent_id' => $parent_id]);


            $results['content'] = $content;
            $content_id = $content->_id;

//==========================================Transcode Video==========================================================
            //Push to Queue Job For transcode media convert
            if ($content && isset($content['video_status']) && $content['video_status'] == 'uploaded') {

                $payload = ['content_id' => $content_id];
                $payload = array_merge($payload, $content['vod_job_data']);

                $jobData = [
                    'label' => 'CreateHLSTranscodeJobForVideo',
                    'type' => 'transcode_video',
                    'payload' => $payload,
                    'status' => "scheduled",
                    'delay' => 0,
                    'retries' => 0,
                ];

                $recodset = new \App\Models\Job($jobData);
                $recodset->save();
            }
//==========================================Transcode Video==========================================================

//==========================================Transcode Audio=============================================================
            //Push to Queue Job For transcode audio
            if ($content && isset($content['audio_status']) && $content['audio_status'] == 'uploaded') {
                $payload = ['content_id' => $content_id];
                $payload = array_merge($payload, $content['aod_job_data']);
                $jobData = [
                    'label' => 'CreateHLSTranscodeJobForAudio',
                    'type' => 'transcode_audio',
                    'payload' => $payload,
                    'status' => "scheduled",
                    'delay' => 0,
                    'retries' => 0,
                ];
                $recodset = new \App\Models\Job($jobData);
                $recodset->save();
            }
//==========================================Transcode Audio=============================================================

//            if (isset($request['on_facebook']) && $request['on_facebook'] == 1) {
//                //$facebookResponse= $this->fb->postFeed($content_id);
//                $payload = ['content_id' => $content_id];
//
//                $jobData = [
//                    'label' => 'ShareContentOnFacebook',
//                    'type' => 'social_share',
//                    'payload' => $payload,
//                    'status' => "scheduled",
//                    'delay' => 0,
//                    'retries' => 0,
//                ];
 //                $recodset = new \App\Models\Job($jobData);
//                $recodset->save();
//
//            }
//
//            if (isset($request['on_twitter']) && $request['on_twitter'] == 1) {
//                $payload = ['content_id' => $content_id];
//
//                $jobData = [
//                    'label' => 'ShareContentOnTwitter',
//                    'type' => 'social_share',
//                    'payload' => $payload,
//                    'status' => "scheduled",
//                    'delay' => 0,
//                    'retries' => 0,
//                ];
 //                $recodset = new \App\Models\Job($jobData);
//                $recodset->save();
//
//                // $twitterResponse= $this->twitter->postFeed($content_id);
//            }
//            if (isset($request['on_instagram']) && $request['on_instagram'] == 1) {
//                $payload = ['content_id' => $content_id];
//
//                $jobData = [
//                    'label' => 'ShareContentOnInstagram',
//                    'type' => 'social_share',
//                    'payload' => $payload,
//                    'status' => "scheduled",
//                    'delay' => 0,
//                    'retries' => 0,
//                ];
 //                $recodset = new \App\Models\Job($jobData);
//                $recodset->save();
//
//                // $twitterResponse= $this->twitter->postFeed($content_id);
//            }
            if (env('APP_ENV', 'stg') == 'production') {
                try {
                    $invalidate_result = $this->awscloudfrontService->invalidateContents();
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

        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function destroy($id)
    {
        $results = $this->repObj->destroy($id);
        return $results;
    }

    public function photoStore($request)
    {

        $error_messages = [];
        $results = [];
        $type = 'photo';
        $commercial_type = 'free';
        $data = $request->all();

        $artist_id = trim($data['artist_id']);
        $bucket_id = trim($data['bucket_id']);
        $parent_id = (isset($data['parent_id'])) ? trim($data['parent_id']) : '';
        $contentObj = \App\Models\Content::where('_id', $parent_id)->first()->toArray();
        $artist = \App\Models\Cmsuser::with('artistconfig')->where('_id', '=', $artist_id)->first();
        $bucket = \App\Models\Bucket::where('_id', '=', $bucket_id)->first();

        if ($contentObj && isset($contentObj['parent_id']) && $contentObj['parent_id'] != '') {
            $parentContentObj = \App\Models\Content::where('_id', '=', $contentObj['parent_id'])->first();
            $parent_id = trim($parentContentObj['_id']);
            if ($contentObj && isset($parentContentObj['commercial_type']) && $parentContentObj['commercial_type'] != '') {
                $commercial_type = $parentContentObj['commercial_type'];
            }
        }

        $level = (isset($data['level'])) ? intval($data['level']) : 1;
        $coins = (isset($data['coins'])) ? intval($data['coins']) : 0;
        $name = (isset($data['name'])) ? $data['name'] : '';
        $caption = (isset($data['caption'])) ? $data['caption'] : '';
        $is_album = (isset($data['is_album'])) ? $data['is_album'] : 'false';
        $slug = (isset($data['name'])) ? str_slug($data['name']) : '';

        // Manages photos start *********************************************
        if ($request->hasFile('photos')) {

            $all_uploads = $request->file('photos');

            // Make sure it really is an array
            if (!is_array($all_uploads)) {
                $all_uploads = array($all_uploads);
            }

            foreach ($all_uploads as $upload) {
                // Ignore array member if it's not an UploadedFile object, just to be extra safe
                if (!is_a($upload, 'Symfony\Component\HttpFoundation\File\UploadedFile')) {
                    continue;
                }

                if ($request['from_web'] == 1) { // Handling Request Coming from Web only for Image Validation
                    $bytes = $upload->getSize();
                    if ($bytes > Config::get('app.file_size')) { // File size > 350 KB
                        $error_messages[] = 'The Photo ' . $upload->getClientOriginalName() . ' may not be greater than 900 KB';
                    }
                }

                //upload to local drive
//                $folder_path = 'uploads/contents/c/';
//                $img_path = public_path($folder_path);
//                $imageName = time() . '_' . str_slug($upload->getRealPath()) . '.' . $upload->getClientOriginalExtension();
//                $fullpath = $img_path . $imageName;
//                $upload->move($img_path, $imageName);
//                chmod($fullpath, 0777);
//
////                //upload to gcp
//                $artist_id = $data['artist_id'];
//                $bucket_id = $data['bucket_id'];
//                $object_source_path = $fullpath;
//                $object_upload_path = $artist_id . '/buckets/' . $bucket_id . '/cover/' . $imageName;
//                $params = ['object_source_path' => $object_source_path, 'object_upload_path' => $object_upload_path];
//                $uploadToGcp = $this->gcp->localFileUpload($params);
//                $cover_url = Config::get('gcp.base_url') . Config::get('gcp.default_bucket_path') . $object_upload_path;
//                $photo = ['cover' => $cover_url, 'thumb' => ''];
//                @unlink($fullpath);

//------------------------------------Kraken Image Compression--------------------------------------------

                $parmas = ['file' => $upload, 'type' => 'contents'];
//              $parmas = ['url' => 'https://storage.googleapis.com/arms-razrmedia/contents/c/php1qTnAH.jpg'];
                $kraked_img = $this->kraken->uploadKrakenImageToGCP($parmas);
                $photo = $kraked_img;

//------------------------------------Kraken Image Compression--------------------------------------------


                $photoData = [
                    'name' => $name,
                    'caption' => $caption,
                    'slug' => $slug,
                    'type' => $type,
                    'artist_id' => $artist_id,
                    'bucket_id' => $bucket_id,
                    'level' => $level,
                    'photo' => $photo,
                    'is_album' => $is_album,
                    'commercial_type' => $commercial_type,
                    'coins' => $coins,
                    'platforms' => ['android', 'ios', 'web'] //Add Patform
                ];

                if ($parent_id != '') {
                    $photoData['parent_id'] = $parent_id;
                }


                if (empty($error_messages)) {
                    $photo = $this->repObj->store($photoData);
                }
            }

            $platforms = ['android', 'ios', 'web'];
            foreach ($platforms as $key => $platform) {
                $cachetag_name = $platform . '_' . $bucket_id . "_contents";
                $env_cachetag = env_cache_tag_key($cachetag_name);              //  ENV_PLATFORM_BUCKETID_contents

                $this->caching->flushTag($env_cachetag);
            }

        }
        //Manages photos end *********************************************


        //Send Notification
        $send_notification = (isset($data['send_notification']) && $data['send_notification'] != '') ? (string)$data['send_notification'] : "false";
        if ($send_notification == 'true') {
            $test = (env('APP_ENV', 'stg') == 'production') ? "false" : "true";
            if ($artist) {
                $latest_bucket_content = \App\Models\Content::where('bucket_id', '=', $bucket_id)->where('artist_id', '=', $artist_id)->orderBy('_id', 'desc')->first();
                $content_id = ($latest_bucket_content && isset($latest_bucket_content['_id']) && $latest_bucket_content['_id'] != '') ? $latest_bucket_content['_id'] : "";
                $test_topic_id = (isset($artist['artistconfig']['fmc_default_topic_id_test']) && $artist['artistconfig']['fmc_default_topic_id_test'] != '') ? trim($artist['artistconfig']['fmc_default_topic_id_test']) : "";
                $production_topic_id = (isset($artist['artistconfig']['fmc_default_topic_id']) && $artist['artistconfig']['fmc_default_topic_id'] != '') ? trim($artist['artistconfig']['fmc_default_topic_id']) : "";
                $artistname = $artist->first_name . ' ' . $artist->last_name;
                $topic_id = ($test == 'true') ? $test_topic_id : $production_topic_id;
                $deeplink = (isset($bucket) & isset($bucket['code']) && $bucket['code'] != '') ? trim($bucket['code']) : "";
                $type = "photo";
                $title = "The " . ucwords($artistname) . " Offical App";
                $body = ucwords($artistname) . " has posted a " . $type;
                $notificationParams = [
                    'artist_id' => $artist_id, 'topic_id' => $topic_id, 'deeplink' => $deeplink, 'title' => $title, 'body' => $body, 'content_id' => $content_id
                ];
                $sendNotification = $this->pushnotification->sendNotificationToTopic($notificationParams);
            }
        }// $send_notification


        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function photoStorev2($request)
    {

        $error_messages = [];
        $results = [];
        $type = 'photo';
        $commercial_type = 'free';

        $data = $request->all();

        $artist_id = trim($data['artist_id']);
        $bucket_id = trim($data['bucket_id']);
        $parent_id = (isset($data['parent_id'])) ? trim($data['parent_id']) : '';

        $contentObj = \App\Models\Content::where('_id', $parent_id)->first()->toArray();

        $artist = \App\Models\Cmsuser::with('artistconfig')->where('_id', '=', $artist_id)->first();

        $bucket = \App\Models\Bucket::where('_id', '=', $bucket_id)->first();

        if ($contentObj && isset($contentObj['parent_id']) && $contentObj['parent_id'] != '') {
            $parentContentObj = \App\Models\Content::where('_id', '=', $contentObj['parent_id'])->first();
            $parent_id = trim($parentContentObj['_id']);
            if ($contentObj && isset($parentContentObj['commercial_type']) && $parentContentObj['commercial_type'] != '') {
                $commercial_type = $parentContentObj['commercial_type'];
            }
        }

        $level = (isset($data['level'])) ? intval($data['level']) : 1;
        $is_album = (isset($data['is_album'])) ? $data['is_album'] : 'false';


        // Manages photos start *********************************************

        foreach ($request['medias'] as $key => $val) {

            if (!empty($val['photo'])) {

                $coins = (isset($val['coins'])) ? intval($val['coins']) : 0;
                $name = (isset($val['name'])) ? $val['name'] : '';
                $caption = (isset($val['caption'])) ? $val['caption'] : '';
                $slug = (isset($val['name'])) ? str_slug($val['name']) : '';

//------------------------------------Kraken Image Compression--------------------------------------------

                $parmas = ['file' => $val['photo'], 'type' => 'contents'];
                $kraked_img = $this->kraken->uploadKrakenImageToGCP($parmas);
                $photo = $kraked_img;

//------------------------------------Kraken Image Compression--------------------------------------------

                $photoData = [
                    'name' => $name,
                    'caption' => $caption,
                    'slug' => $slug,
                    'type' => $type,
                    'artist_id' => $artist_id,
                    'bucket_id' => $bucket_id,
                    'level' => $level,
                    'photo' => $photo,
                    'is_album' => $is_album,
                    'commercial_type' => $commercial_type,
                    'coins' => $coins,
                    'platforms' => ['android', 'ios', 'web'] //Add Patform
                ];

                if ($parent_id != '') {
                    $photoData['parent_id'] = $parent_id;
                }


                if (empty($error_messages)) {
                    $photo = $this->repObj->store($photoData);
                }
            }
        }

        $platforms = ['android', 'ios', 'web'];
        foreach ($platforms as $key => $platform) {
            $cachetag_name = $platform . '_' . $bucket_id . "_contents";
            $env_cachetag = env_cache_tag_key($cachetag_name);              //  ENV_PLATFORM_BUCKETID_contents

            $this->caching->flushTag($env_cachetag);
        }


        //Manages photos end *********************************************


        //Send Notification
        $send_notification = (isset($data['send_notification']) && $data['send_notification'] != '') ? (string)$data['send_notification'] : "false";
        if ($send_notification == 'true') {
            $test = (env('APP_ENV', 'stg') == 'production') ? "false" : "true";
            if ($artist) {
                $latest_bucket_content = \App\Models\Content::where('bucket_id', '=', $bucket_id)->where('artist_id', '=', $artist_id)->orderBy('_id', 'desc')->first();
                $content_id = ($latest_bucket_content && isset($latest_bucket_content['_id']) && $latest_bucket_content['_id'] != '') ? $latest_bucket_content['_id'] : "";
                $test_topic_id = (isset($artist['artistconfig']['fmc_default_topic_id_test']) && $artist['artistconfig']['fmc_default_topic_id_test'] != '') ? trim($artist['artistconfig']['fmc_default_topic_id_test']) : "";
                $production_topic_id = (isset($artist['artistconfig']['fmc_default_topic_id']) && $artist['artistconfig']['fmc_default_topic_id'] != '') ? trim($artist['artistconfig']['fmc_default_topic_id']) : "";
                $artistname = $artist->first_name . ' ' . $artist->last_name;
                $topic_id = ($test == 'true') ? $test_topic_id : $production_topic_id;
                $deeplink = (isset($bucket) & isset($bucket['code']) && $bucket['code'] != '') ? trim($bucket['code']) : "";
                $type = "photo";
                $title = "The " . ucwords($artistname) . " Offical App";
                $body = ucwords($artistname) . " has posted a " . $type;
                $notificationParams = [
                    'artist_id' => $artist_id, 'topic_id' => $topic_id, 'deeplink' => $deeplink, 'title' => $title, 'body' => $body, 'content_id' => $content_id
                ];
                $sendNotification = $this->pushnotification->sendNotificationToTopic($notificationParams);
            }
        }// $send_notification


        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function videoStore($request)
    {
        $data = $request->all();
        $error_messages = [];
        $results = [];
        $commercial_type = 'free';
        $send_notification = (isset($data['send_notification']) && $data['send_notification'] != '') ? (string)$data['send_notification'] : "false";
        $parent_id = (isset($data['parent_id'])) ? trim($data['parent_id']) : '';
        $slug = (isset($data['source'])) ? str_slug($data['name']) : '';
        $contentObj = \App\Models\Content::where('_id', $parent_id)->first()->toArray();

        if ($contentObj && isset($contentObj['parent_id']) && $contentObj['parent_id'] != '') {
            $parentContentObj = \App\Models\Content::where('_id', '=', $contentObj['parent_id'])->first();
            $parent_id = trim($parentContentObj['_id']);
            if ($contentObj && isset($parentContentObj['commercial_type']) && $parentContentObj['commercial_type'] != '') {
                $commercial_type = $parentContentObj['commercial_type'];
            }
        }

        array_set($data, 'slug', $slug);
        array_set($data, 'commercial_type', $commercial_type);

        $platforms = ['android', 'ios', 'web'];
        array_set($data, 'platforms', $platforms); //Added Platforms option

        // Manages video start *********************************************
        if ($request->file('video')) {

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
            $s3 = Storage::createS3Driver(Config::get('product.' . env('PRODUCT') . '.s3.rawvideos'));
            if (env('APP_ENV', 'stg') != 'local') {
                $response = $s3->put($object_upload_path, file_get_contents($object_source_path), 'public');
            }

            $vod_job_data = ['status' => 'submitted', 'object_name' => $imageName, 'object_path' => $object_upload_path, 'object_extension' => $obj_extension, 'bucket' => 'armsrawvideos'];
            array_set($data, 'vod_job_data', $vod_job_data);
            array_set($data, 'video_status', 'uploaded');

            @unlink($fullpath);
        }


        if ($request->hasFile('photo')) {


//------------------------------------Kraken Image Compression--------------------------------------------

            $parmas = ['file' => $request->file('photo'), 'type' => 'contents'];
//              $parmas = ['url' => 'https://storage.googleapis.com/arms-razrmedia/contents/c/php1qTnAH.jpg'];
            $kraked_img = $this->kraken->uploadKrakenImageToGCP($parmas);
            $photo = $kraked_img;
            array_set($data, 'photo', $photo);

//------------------------------------Kraken Image Compression--------------------------------------------

        }

        if (empty($error_messages)) {
            $content = $this->repObj->store($data);


            $page = (isset($data['page']) && $data['page'] != '') ? trim($data['page']) : '1';

            $platforms = ['android', 'ios', 'web'];
            foreach ($platforms as $key => $platform) {
                $cachetag_name = $platform . '_' . $data['bucket_id'] . "_contents";
                $env_cachetag = env_cache_tag_key($cachetag_name);              //  ENV_PLATFORM_BUCKETID_contents

                $this->caching->flushTag($env_cachetag);
            }

            $results['content'] = $content;
            $content_id = $content->_id;

            //Push to Queue Job For transcode video
            if ($content && isset($content['video_status']) && $content['video_status'] == 'uploaded') {
                $payload = ['content_id' => $content_id, 'send_notification' => $send_notification];
                $payload = array_merge($payload, $content['vod_job_data']);
                $jobData = [
                    'label' => 'CreateHLSTranscodeJobForVideo',
                    'type' => 'transcode_video',
                    'payload' => $payload,
                    'status' => "scheduled",
                    'delay' => 0,
                    'retries' => 0,
                ];
                $recodset = new \App\Models\Job($jobData);
                $recodset->save();

                $send_notification = 'false'; //incase of video
            }


        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function audioStore($request)
    {
        $data = $request->all();
        $error_messages = [];
        $results = [];
        $commercial_type = 'free';
        $send_notification = (isset($data['send_notification']) && $data['send_notification'] != '') ? (string)$data['send_notification'] : "false";
        $parent_id = (isset($data['parent_id'])) ? trim($data['parent_id']) : '';
        $slug = (isset($data['source'])) ? str_slug($data['name']) : '';
        $contentObj = \App\Models\Content::where('_id', $parent_id)->first()->toArray();

        if ($contentObj && isset($contentObj['parent_id']) && $contentObj['parent_id'] != '') {
            $parentContentObj = \App\Models\Content::where('_id', '=', $contentObj['parent_id'])->first();
            $parent_id = trim($parentContentObj['_id']);
            if ($contentObj && isset($parentContentObj['commercial_type']) && $parentContentObj['commercial_type'] != '') {
                $commercial_type = $parentContentObj['commercial_type'];
            }
        }

        array_set($data, 'slug', $slug);
        array_set($data, 'commercial_type', $commercial_type);

        $platforms = ['android', 'ios', 'web'];
        array_set($data, 'platforms', $platforms); //Added Platforms option

        // Manages audio start *********************************************
        if ($request->file('audio')) {

            //upload to local drive
            $upload = $request->file('audio');
            $folder_path = 'uploads/contents/audio/';
            $obj_path = public_path($folder_path);
            $obj_extension = $upload->getClientOriginalExtension();
            $imageName = time() . '_' . str_slug($upload->getRealPath()) . '.' . $obj_extension;
            $fullpath = $obj_path . $imageName;
            $upload->move($obj_path, $imageName);
            chmod($fullpath, 0777);

            //upload to aws
            $object_source_path = $fullpath;
            $object_upload_path = $imageName;
            $s3 = Storage::createS3Driver(Config::get('product.' . env('PRODUCT') . '.s3.rawaudios'));
            if (env('APP_ENV', 'stg') != 'local') {
                $response = $s3->put($object_upload_path, file_get_contents($object_source_path), 'public');
            }
            $aod_job_data = ['status' => 'submitted', 'object_name' => $imageName, 'object_path' => $object_upload_path, 'object_extension' => $obj_extension, 'bucket' => 'armsrawaudios'];
            array_set($data, 'aod_job_data', $aod_job_data);
            array_set($data, 'audio_status', 'uploaded');


            @unlink($fullpath);
        }

        if ($request->hasFile('photo')) {

//------------------------------------Kraken Image Compression--------------------------------------------

            $parmas = ['file' => $request->file('photo'), 'type' => 'contents'];
//              $parmas = ['url' => 'https://storage.googleapis.com/arms-razrmedia/contents/c/php1qTnAH.jpg'];
            $kraked_img = $this->kraken->uploadKrakenImageToGCP($parmas);
            $photo = $kraked_img;
            array_set($data, 'photo', $photo);

//------------------------------------Kraken Image Compression--------------------------------------------

        }

        if (empty($error_messages)) {
            $content = $this->repObj->store($data);

            $page = (isset($data['page']) && $data['page'] != '') ? trim($data['page']) : '1';

            $platforms = ['android', 'ios', 'web'];
            foreach ($platforms as $key => $platform) {
                $cachetag_name = $platform . '_' . $data['bucket_id'] . "_contents";
                $env_cachetag = env_cache_tag_key($cachetag_name);              //  ENV_PLATFORM_BUCKETID_contents

                $this->caching->flushTag($env_cachetag);
            }

//            $cachetag_name = $data['bucket_id'] . "_contents";
//            $env_cachetag = env_cache_tag_key($cachetag_name);              //  ENV_BUCKETID_contents
//
//            $this->caching->flushTag($env_cachetag);

            $results['content'] = $content;
            $content_id = $content->_id;

            //Push to Queue Job For transcode audio
            if ($content && isset($content['audio_status']) && $content['audio_status'] == 'uploaded') {
                $payload = ['content_id' => $content_id, 'send_notification' => $send_notification];
                $payload = array_merge($payload, $content['aod_job_data']);
                $jobData = [
                    'label' => 'CreateHLSTranscodeJobForAudio',
                    'type' => 'transcode_audio',
                    'payload' => $payload,
                    'status' => "scheduled",
                    'delay' => 0,
                    'retries' => 0,
                ];
                $recodset = new \App\Models\Job($jobData);
                $recodset->save();
                $send_notification = 'false'; //incase of audio
            }

        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function videoUpdate($request, $id)
    {
        $data = $request->all();
        $error_messages = [];
        $results = [];
        $slug = (isset($data['name'])) ? str_slug($data['name']) : '';
        $parent_id = (isset($data['parent_id'])) ? trim($data['parent_id']) : '';
        $contentObj = \App\Models\Content::where('_id', $parent_id)->first()->toArray();

        if ($contentObj && isset($contentObj['parent_id']) && $contentObj['parent_id'] != '') {
            $parentContentObj = \App\Models\Content::where('_id', '=', $contentObj['parent_id'])->first();
            $parent_id = trim($parentContentObj['_id']);
            if ($contentObj && isset($parentContentObj['commercial_type']) && $parentContentObj['commercial_type'] != '') {
                $commercial_type = $parentContentObj['commercial_type'];
            }
        }

        array_set($data, 'slug', $slug);
        array_set($data, 'commercial_type', $commercial_type);

        if ($request->hasFile('photo')) {

            //upload to local drive
            $upload = $request->file('photo');

            $folder_path = 'uploads/contents/c/';
            $img_path = public_path($folder_path);
            $imageName = time() . '_' . str_slug($upload->getRealPath()) . '.' . $upload->getClientOriginalExtension();
            $fullpath = $img_path . $imageName;
            $upload->move($img_path, $imageName);
            chmod($fullpath, 0777);

            //upload to gcp
            $artist_id = $data['artist_id'];
            $bucket_id = $data['bucket_id'];
            $object_source_path = $fullpath;
            $object_upload_path = $artist_id . '/buckets/' . $bucket_id . '/cover/' . $imageName;
            $params = ['object_source_path' => $object_source_path, 'object_upload_path' => $object_upload_path];
            $uploadToGcp = $this->gcp->localFileUpload($params);
            $cover_url = Config::get('gcp.base_url') . Config::get('gcp.default_bucket_path') . $object_upload_path;
            $photo = ['cover' => $cover_url, 'thumb' => ''];

            array_set($data, 'photo', $photo);

            @unlink($fullpath);

        }

        if (empty($error_messages)) {
            $results['content'] = $this->repObj->update($data, $id);

            $platforms = ['android', 'ios', 'web'];
            foreach ($platforms as $key => $platform) {
                $cachetag_name = $platform . '_' . $data['bucket_id'] . "_contents";
                $env_cachetag = env_cache_tag_key($cachetag_name);              //  ENV_PLATFORM_BUCKETID_contents

                $this->caching->flushTag($env_cachetag);
            }

            $cachetag_name = $id . "_contentdetails";
            $env_cachetag = env_cache_tag_key($cachetag_name);    //ENV_contentid_contentdetails
            $this->caching->flushTag($env_cachetag);


//            $cachetag_name = $data['bucket_id'] . "_contents";
//            $env_cachetag = env_cache_tag_key($cachetag_name);              //  ENV_BUCKETID_contents

//            $this->caching->flushTag($env_cachetag);
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function getLikes($request)
    {
        $requestData = $request->all();
        $error_messages = $results = [];

        $content_id = isset($requestData['content_id']) ? $requestData['content_id'] : '';
        $page = isset($request['page']) ? $request['page'] : '';

        $cachetag_name = $content_id . '_' . "likes";
        $env_cachetag = env_cache_tag_key($cachetag_name);              //  ENV_contentid_likes
        $cachetag_key = $page . '_' . $content_id;                          //  PAGENO_contentid
        $cache_time = Config::get('cache.cache_time');

        $contents = Cache::tags($env_cachetag)->has($cachetag_key);

        if (!$contents) {
            $responses = $this->repObj->getLikes($requestData);
            $items = ($responses) ? $responses : [];
            $items = apply_cloudfront_url($items);
            Cache::tags($env_cachetag)->put($cachetag_key, $items, $cache_time);
        }

        $results = Cache::tags($env_cachetag)->get($cachetag_key);
        $results['cache'] = ['tags' => $env_cachetag, 'key' => $cachetag_key];

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function saveLike($request)
    {
        $error_messages = $results = [];
        $requestData = $request->all();
        $artist_id = (request()->header('artistid')) ? trim(request()->header('artistid')) : "";
        $platform = (request()->header('platform')) ? trim(request()->header('platform')) : "";
        $requestData['artist_id'] = $artist_id;
        $requestData['platform'] = $platform;


        if (empty($error_messages)) {
            $results['like'] = $this->repObj->saveLike($requestData);

//            $data = \App\Models\Content::where('_id', $requestData['content_id'])->first(['bucket_id']);
//            $platforms = ['android', 'ios', 'web'];
//            foreach ($platforms as $key => $platform) {
//                $cachetag_name = $platform . '_' . $data['bucket_id'] . "_contents";
//                $env_cachetag = env_cache_tag_key($cachetag_name);              //  ENV_PLATFORM_BUCKETID_contents
//                $this->caching->flushTag($env_cachetag);
//            }

            $cachetag_name = $requestData['content_id'] . "_contentdetails";
            $env_cachetag = env_cache_tag_key($cachetag_name);    //ENV_contentid_contentdetails
            $cachetag_key = $requestData['content_id'];        //contentid
            $this->caching->flushTagKey($env_cachetag, $cachetag_key);

            $customer = $this->jwtauth->customerFromToken();
            $customer_id = $customer['_id'];

            $metaids_key = Config::get('cache.keys.customermetaids') . $customer_id;
            $env_metaids_key = env_cache_key($metaids_key); // Redis KEYS for Metaids
            $redisClient = $this->redisdb->PredisConnection();
            $redisClient->hdel($env_metaids_key, ['like_content_ids']);

        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function getComments($request)
    {
        $requestData = $request->all();
        $error_messages = $results = [];

        if (empty($error_messages)) {
            $results['comment'] = $this->repObj->getCommentsOld($requestData);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function getCommentsLists($request)
    {
        $content_id = isset($request['content_id']) ? $request['content_id'] : '';
        $page = isset($request['page']) ? $request['page'] : '1';

        $requestData = $request->all();
        $error_messages = $results = [];

        /*
        $cachetag_name = $content_id . '_' . "comments";
        $env_cachetag = env_cache_tag_key($cachetag_name);              //  ENV_contentid_comments
        $cachetag_key = $page . '_' . $content_id;                          //  PAGENO_contentid
        $cache_time = Config::get('cache.cache_time');

        $contents = Cache::tags($env_cachetag)->has($cachetag_key);
        if (!$contents) {
            $responses = $this->repObj->getComments($requestData);
            $items = ($responses) ? $responses : [];
            $items = apply_cloudfront_url($items);
            Cache::tags($env_cachetag)->put($cachetag_key, $items, $cache_time);
        }
        $results = Cache::tags($env_cachetag)->get($cachetag_key);
        $results['cache'] = ['tags' => $env_cachetag, 'key' => $cachetag_key];
        */


        $cacheParams = [];
        $hash_name      =   env_cache(Config::get('cache.hash_keys.content_comments_lists').$content_id);
        $hash_field     =   $page;
        $cache_miss     =   false;

        $cacheParams['hash_name']   =  $hash_name;
        $cacheParams['hash_field']  =  (string)$hash_field;


        $results = $responses = $this->awsElasticCacheRedis->getHashData($cacheParams);
        if (empty($results)) {
            $responses = $this->repObj->getComments($request);
            $items = ($responses) ? apply_cloudfront_url($responses) : [];
            $cacheParams['hash_field_value'] = $items;
            $saveToCache = $this->awsElasticCacheRedis->saveHashData($cacheParams);
            $cache_miss = true;
            $results  = $this->awsElasticCacheRedis->getHashData($cacheParams);
        }

        $results['cache']    = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];


        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function getCommentReplies($request)
    {
        $comment_id = isset($request['comment_id']) ? $request['comment_id'] : '';
        $page = isset($request['page']) ? $request['page'] : '1';

        $requestData = $request->all();
        $error_messages = $results = [];

        $cacheParams = [];
        $hash_name      =   env_cache(Config::get('cache.hash_keys.content_commentreplies_lists').$comment_id);
        $hash_field     =   $page;
        $cache_miss     =   false;

        $cacheParams['hash_name']   =  $hash_name;
        $cacheParams['hash_field']  =  (string)$hash_field;


        $results = $responses = $this->awsElasticCacheRedis->getHashData($cacheParams);
        if (empty($results)) {
            $responses = $this->repObj->getCommentReplies($request);
            $items = ($responses) ? apply_cloudfront_url($responses) : [];
            $cacheParams['hash_field_value'] = $items;
            $saveToCache = $this->awsElasticCacheRedis->saveHashData($cacheParams);
            $cache_miss = true;
            $results  = $this->awsElasticCacheRedis->getHashData($cacheParams);
        }

        $results['cache']    = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];


        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function saveComment($request)
    {
        $requestData = $request->all();
        $error_messages = $results = [];

        if (empty($error_messages)) {
            $results['comment'] = $this->repObj->saveComment($requestData);


            $cachetag_name = $request['content_id'] . "_contentdetails";
            $env_cachetag_contentdetail = env_cache_tag_key($cachetag_name);//ENV_contentid_contentdetails
//            $this->caching->flushTag($env_cachetag_contentdetail);
            $cachetag_key = $requestData['content_id'];        //contentid
            $this->caching->flushTagKey($env_cachetag_contentdetail, $cachetag_key);

            $cachetag_name = $request['content_id'] . "_comments";
            $env_cachetag_comments = env_cache_tag_key($cachetag_name);     //ENV_contentid_comments
            $this->caching->flushTag($env_cachetag_comments);
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function replyOnComment($request)
    {
        $requestData = $request->all();
        $error_messages = $results = [];

        if (empty($error_messages)) {
            $results['comment'] = $this->repObj->replyOnComment($requestData);

            $cachetag_name = $request['content_id'] . "_comments";
            $env_cachetag_comments = env_cache_tag_key($cachetag_name);     //ENV_contentid_comments
            $this->caching->flushTag($env_cachetag_comments);

            $cachetag_name = $request['parent_id'] . "_commentreplies";
            $env_cachetag = env_cache_tag_key($cachetag_name);    //ENV_commentreplies
//            $cachetag_key = $results['comment']['entity_id'];     //content_id

            $this->caching->flushTag($env_cachetag);


            // Send Notification to customer <Celeb Name>  has replied to your comment.


        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function pinToTop($request)
    {

        $requestData = $request->all();
        $error_messages = $results = [];

        if (empty($error_messages)) {
            $results['content'] = $this->repObj->pinToTop($requestData);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function purchaseContent($request)
    {
        $error_messages = [];
        $results = [];
        $requestData = $request->all();

        $content_id = $requestData['content_id'];
        $artist_id = $requestData['artist_id'];

        $platform = $requestData['platform'];

        $coins = 0;

        $customer_id = $this->jwtauth->customerIdFromToken();
        $contentObj = \App\Models\Content::where('_id', $content_id)->first();
        $customer = \App\Models\Customer::where('_id', $customer_id)->first();
        if($contentObj) {
            if(isset($contentObj->coins)) {
                $coins  = $contentObj->coins;
            }
            else {
                $error_messages[] = 'Content does not have coins set';
            }
        }
        else {
            $error_messages[] = 'Content does not exists';
        }

        if (empty($customer)) {
            $error_messages[] = 'Customer does not exists';
        }

        if (empty($contentObj)) {
            $error_messages[] = 'Content does not exists';
        }

        if ($coins < 1) {
            $error_messages[] = 'Coins cannot be zero';
        }

        if (!isset($customer['coins']) || $customer['coins'] < $coins) {
            $error_messages[] = 'Not Enough Coins, Add More';
        }

        if (empty($error_messages)) {
            $results['purchase'] = $this->repObj->purchaseContent($requestData);

            ////===Cahce Flush===
            $purge_result = $this->awsElasticCacheRedis->purgeCustomerSpendingsListsCache(['customer_id' => $customer_id]);

            $purge_meta_ids = $this->awsElasticCacheRedis->purgeCustomerMetaIdsCache(['customer_id' => $customer_id]);
        }


        return ['error_messages' => $error_messages, 'results' => $results];
    }





    public function getHistoryPurchaseContents($request)
    {

        $error_messages = $results = [];
        $customer_id = $this->jwtauth->customerIdFromToken();
        $request['customer_id'] = $customer_id;

        if (empty($error_messages)) {
            $results = $this->repObj->getHistoryPurchaseContents($request);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function fetchResults($request)
    {
        $error_messages = $results = [];
        $requestData = $request->all();
        $results = $this->repObj->fetchResults($requestData);

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function submitPollResult($request)
    {
        $data = $request->all();
        $error_messages = $results = [];

        if (empty($error_messages)) {
            $results['pollResults'] = $this->repObj->submitPollResult($data);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function fetchList($request)
    {
        $requestData = $request->all();

        $error_messages = $results = [];
        if (empty($error_messages)) {
            $response = $this->repObj->fetchList($requestData);
            $results['contents'] = apply_cloudfront_url($response);
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function updateStatus($request)
    {
        if (!empty($request->all()['content_id'])) {
            $content = \App\Models\Content::findOrFail($request->all()['content_id']);
            $data = [];

            if ($request->all()['status'] == 'active') {
                array_set($data, 'status', 'inactive');
            } else {
                array_set($data, 'status', 'active');
                array_set($data, 'published_at', new \MongoDB\BSON\UTCDateTime(strtotime(date('Y-m-d H:i:s')) * 1000));
            }

            $content->update($data);

            try {
                if ($content && isset($content->parent_id) && $content->parent_id != '') {
                    $this->repObj->updateChildrenCount($content->parent_id);
                }
            } catch (Exception $e) {
                $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                Log::info('UpdateChildrenCount : Error ', $message);
            }

            $artist_id = $content['artist_id'];
            $bucket_id = (!empty($content['bucket_id']) && $content['bucket_id'] != '') ? trim($content['bucket_id']) : "";
            $parent_id = (!empty($content['parent_id']) && $content['parent_id'] != '') ? trim($content['parent_id']) : "";
            $content_id = (!empty($content['content_id']) && $content['content_id'] != '') ? trim($content['content_id']) : "";

            $languages          =   $this->artistservice->getArtistCode2LanguageArray($artist_id);

            $purge_result   = $this->awsElasticCacheRedis->purgeContentListCache(['content_id' => $content_id, 'bucket_id' => $bucket_id, 'parent_id' => $parent_id, 'languages' => $languages]);
            $purge_result   = $this->awsElasticCacheRedis->purgeContentDetailCache(['content_id' => $content_id, 'bucket_id' => $bucket_id, 'parent_id' => $parent_id]);



        }
        return !empty($content) ? $content : '';
    }

    public function uploadContent($request)
    {
        $error_messages = $results = [];

        $data = $request->all();

        $status = "active";
        $commercial_type = "free";
        $coins = 0;

        $type = (isset($data['type'])) ? $data['type'] : 'photo';
        $artist_id = (isset($data['artist_id'])) ? $data['artist_id'] : ''; //handel error in service

        $bucket_id = (isset($data['bucket_id'])) ? $data['bucket_id'] : '';
        $bucket = \App\Models\Bucket::where('_id', '=', $bucket_id)->first();
        $bucket_code = (isset($bucket) & isset($bucket['code']) && $bucket['code'] != '') ? trim($bucket['code']) : ""; // for sending notification

        $parent_id = (isset($data['parent_id'])) ? $data['parent_id'] : '';

        $send_notification = (isset($data['send_notification']) && $data['send_notification'] != '') ? (string)$data['send_notification'] : "false";

        $is_album = (isset($data['is_album'])) ? $data['is_album'] : 'false';


        $cover_with_image = [];
        $cover_with_out_image = [];
        $get_contents_with_cover = '';

        foreach ($data['medias'] as $key => $val) {

            $coins = (!empty($val['coins'])) ? intval($val['coins']) : $coins;
            $name = (!empty($val['name'])) ? $val['name'] : '';
            $caption = (!empty($val['caption'])) ? $val['caption'] : '';
            $slug = (!empty($val['name'])) ? str_slug($val['name']) : '';
            $commercial_type = (!empty($val['commercial_type'])) ? $val['commercial_type'] : $commercial_type;
            $player_type = (!empty($val['player_type'])) ? $val['player_type'] : '';
            $embed_code = (!empty($val['embed_code'])) ? $val['embed_code'] : '';
            $duration = (!empty($val['duration'])) ? $val['duration'] : '';

            $vod_job_data = [];

            $setOfContentData = [
                'name' => $name,
                'caption' => $caption,
                'slug' => $slug,
                'type' => $type,
                'artist_id' => $artist_id,
                'bucket_id' => $bucket_id,
                'level' => 1,
                'is_album' => $is_album,
                'commercial_type' => $commercial_type,
                'coins' => $coins,
                'status' => $status,
                'platforms' => ['android', 'ios', 'web'] //Add Patform
            ];

            if (!empty($player_type)) {
                array_set($setOfContentData, 'player_type', $player_type);
            }
            if (!empty($duration)) {
                array_set($setOfContentData, 'duration', $duration);
                // Convert Duration into milliseconds and save in duration_ms attribute
                $duration_ms = self::convertDurationInMilliseconds($duration);
                array_set($setOfContentData, 'duration_ms', $duration_ms);

            }

            if (!empty($val['video'])) {
                /* OLD CODE
                //upload to local drive
                $upload = $val['video'];
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
                $s3 = Storage::createS3Driver(Config::get('product.' . env('PRODUCT') . '.s3.rawvideos'));
                if (env('APP_ENV', 'stg') != 'local') {
                    $response = $s3->put($object_upload_path, file_get_contents($object_source_path), 'public');
                }

                $vod_job_data = ['status' => 'submitted', 'object_name' => $imageName, 'object_path' => $object_upload_path, 'object_extension' => $obj_extension, 'bucket' => 'armsrawvideos'];

                array_set($setOfContentData, 'vod_job_data', $vod_job_data);
                array_set($setOfContentData, 'video_status', 'uploaded');
                array_set($setOfContentData, 'video', $val['video']);

                if (!empty($embed_code)) {
                    array_set($setOfContentData, 'embed_code', $embed_code);
                }

                @unlink($fullpath);
                */

                $video_file = $val['video'];
                $video_file->video_lang_default = isset($val['video_lang_default']) ? $val['video_lang_default'] : false;
                $video_file->video_lang         = isset($val['video_lang']) ? $val['video_lang'] : 'eng';
                $video_file->video_lang_label   = isset($val['video_lang_label']) ? $val['video_lang_label'] : 'ENGLISH';
                $vod_job_data = $this->uploadContentVideoFile($video_file);

                if($vod_job_data) {
                    array_set($setOfContentData, 'vod_job_data', [$vod_job_data]);
                    array_set($setOfContentData, 'video_status', 'uploaded');

                    if(isset($vod_job_data['video_url_key']) && $vod_job_data['video_url_key']) {
                        $video_url_key = $vod_job_data['video_url_key'];
                        $video_url_raw = isset($vod_job_data['video_url_raw']) ? $vod_job_data['video_url_raw'] : '';
                        if($video_url_key == 'eng') {
                            array_set($setOfContentData['video'], 'url', $video_url_raw);
                        }

                        if($video_url_key && $video_url_raw) {
                            array_set($setOfContentData['video'], $video_url_key, $video_url_raw);
                        }
                    }
                }
            }

            if (!empty($val['audio'])) {

                //upload to local drive
                $upload = $val['audio'];
                $folder_path = 'uploads/contents/audio/';
                $obj_path = public_path($folder_path);
                $obj_extension = $upload->getClientOriginalExtension();
                $imageName = time() . '_' . str_slug($upload->getRealPath()) . '.' . $obj_extension;
                $fullpath = $obj_path . $imageName;
                $upload->move($obj_path, $imageName);
                chmod($fullpath, 0777);

                //upload to aws
                $object_source_path = $fullpath;
                $object_upload_path = $imageName;
                $s3 = Storage::createS3Driver(Config::get('product.' . env('PRODUCT') . '.s3.rawaudios'));
                if (env('APP_ENV', 'stg') != 'local') {
                    $response = $s3->put($object_upload_path, file_get_contents($object_source_path), 'public');
                }
                $aod_job_data = ['status' => 'submitted', 'object_name' => $imageName, 'object_path' => $object_upload_path, 'object_extension' => $obj_extension, 'bucket' => 'armsrawaudios'];
                array_set($setOfContentData, 'aod_job_data', $aod_job_data);
                array_set($setOfContentData, 'audio_status', 'uploaded');
                array_set($setOfContentData, 'audio', $val['audio']);

                @unlink($fullpath);

            }

            if (!empty($val['photo'])) {

//------------------------------------Kraken Image Compression--------------------------------------------

                $parmas = ['file' => $val['photo'], 'type' => 'contents'];
                $kraked_img = $this->kraken->uploadKrakenImageToGCP($parmas);
                $photo = $kraked_img;

//                $photo = Array
//                (
//                    'thumb' => 'https://storage.googleapis.com/arms-razrmedia/contents/ct/phpMpU5XU.jpg',
//                    'thumb_width' => 420,
//                    'thumb_height' => 294,
//                    'cover' => 'https://storage.googleapis.com/arms-razrmedia/contents/c/phpMpU5XU.jpg',
//                    'cover_width' => 1280,
//                    'cover_height' => 896,
//                    'medium' => 'https://storage.googleapis.com/arms-razrmedia/contents/cm/phpMpU5XU.jpg',
//                    'medium_width' => 800,
//                    'medium_height' => 560
//                );

                array_set($setOfContentData, 'photo', $photo);
//------------------------------------Kraken Image Compression--------------------------------------------

            }

            $notification_arr = Array(
                'send_notification' => $send_notification,
                'artist_id' => $artist_id,
                'bucket_code' => $bucket_code,
                'player_type' => $player_type,
                'embed_code' => $embed_code,
                'url' => ''
            );

            if ($is_album == 'false' && empty($parent_id)) { //Level-1
                $store = $this->repObj->store($setOfContentData);

                $content_id = $store->_id;

//                //Push to Queue Job For transcode video
//                if ($store && isset($store['video_status']) && $store['video_status'] == 'uploaded') {
//                    $payload = ['content_id' => $content_id, 'send_notification' => $send_notification];
//                    $payload = array_merge($payload, $store['vod_job_data']);
//                    $jobData = [
//                        'label' => 'CreateHLSTranscodeJobForVideo',
//                        'type' => 'transcode_video',
//                        'payload' => $payload,
//                        'status' => "scheduled",
//                        'delay' => 0,
//                        'retries' => 0,
//                    ];
//                    $recodset = new \App\Models\Job($jobData);
//                    $recodset->save();
//                    $send_notification = 'false'; //incase of video
//                }

//==========================================Mediaconvert Video==========================================================
                //Push to Queue Job For transcode media convert
                if ($store && isset($store['video_status']) && $store['video_status'] == 'uploaded') {
                    /* OLD CODE
                    $payload = [
                        'content_id' => $content_id,
                        'send_notification' => $send_notification,
                    ];

                    $payload = array_merge($payload, $store['vod_job_data']);

                    $jobData = [
                        'label' => 'CreateHLSTranscodeJobForMediaconvert',
                        'type' => 'transcode_mediaconvert',
                        'payload' => $payload,
                        'status' => "scheduled",
                        'delay' => 0,
                        'retries' => 0,
                    ];

                    $recodset = new \App\Models\Job($jobData);
                    $recodset->save();
                    $send_notification = 'false'; //incase of video
                    */

                    $this->createJobForTranscoding($content_id, $vod_job_data);
                }
//==========================================Mediaconvert Video==========================================================

                //Push to Queue Job For transcode audio
                if ($store && isset($store['audio_status']) && $store['audio_status'] == 'uploaded') {
                    $payload = ['content_id' => $content_id, 'send_notification' => $send_notification];
                    $payload = array_merge($payload, $store['aod_job_data']);
                    $jobData = [
                        'label' => 'CreateHLSTranscodeJobForAudio',
                        'type' => 'transcode_audio',
                        'payload' => $payload,
                        'status' => "scheduled",
                        'delay' => 0,
                        'retries' => 0,
                    ];
                    $recodset = new \App\Models\Job($jobData);
                    $recodset->save();
                    $send_notification = 'false'; //incase of audio
                }


                $notification_arr['content_id'] = $content_id;
                $this->sendNotifications($notification_arr);
            }

            if ($is_album == 'true' || !empty($parent_id)) { //level-2

                if ((array_key_exists('cover', $val) && !is_null($val['cover']) && $val['cover'] != 'false') && !empty($val['cover'])) {
                    $cover_with_image = $setOfContentData;
                    $get_contents_with_cover = "true";
                } else {

                    if ($setOfContentData['commercial_type'] == 'partial_paid') {
                        $setOfContentData['commercial_type'] = 'free';
                        $setOfContentData['coins'] = 0;
                    }

                    array_push($cover_with_out_image, $setOfContentData);
                }
            }
        }

        if ($is_album == 'true' && empty($parent_id)) {

            if ($get_contents_with_cover == "true") {

                $cover_with_image['status'] = 'inactive';

                $store = $this->repObj->store($cover_with_image);

                $parent_id = $store->_id;

//                $commercial_type = $store->commercial_type;

            } else {

                $cover_with_out_image[0]['status'] = 'inactive';

                $store = $this->repObj->store($cover_with_out_image[0]);

                $parent_id = $store->_id;

//                $commercial_type = $store->commercial_type;

                unset($cover_with_out_image[0]);
//                $cover_with_out_image[0]['status'] = $status;

            }
        }


        if (!empty($parent_id)) {

            $cover_with_out_image = array_values($cover_with_out_image);

            $length_of_arr = count($cover_with_out_image);

            foreach ($cover_with_out_image as $withoutKey => $withoutVal) {

                $withoutVal['parent_id'] = $parent_id;
//                $withoutVal['commercial_type'] = $commercial_type;
                $withoutVal['level'] = 2;
                $withoutVal['is_album'] = 'false';

                $store = $this->repObj->store($withoutVal);

                $content_id = $store->_id;

                //Push to Queue Job For transcode video
                if ($store && isset($store['video_status']) && $store['video_status'] == 'uploaded') {
                    $payload = ['content_id' => $content_id, 'send_notification' => $send_notification];
                    $payload = array_merge($payload, $store['vod_job_data']);
                    $jobData = [
                        'label' => 'CreateHLSTranscodeJobForVideo',
                        'type' => 'transcode_video',
                        'payload' => $payload,
                        'status' => "scheduled",
                        'delay' => 0,
                        'retries' => 0,
                    ];
                    $recodset = new \App\Models\Job($jobData);
                    $recodset->save();
                    $send_notification = 'false'; //incase of video
                }

                //Push to Queue Job For transcode audio
                if ($store && isset($store['audio_status']) && $store['audio_status'] == 'uploaded') {
                    $payload = ['content_id' => $content_id, 'send_notification' => $send_notification];
                    $payload = array_merge($payload, $store['aod_job_data']);
                    $jobData = [
                        'label' => 'CreateHLSTranscodeJobForAudio',
                        'type' => 'transcode_audio',
                        'payload' => $payload,
                        'status' => "scheduled",
                        'delay' => 0,
                        'retries' => 0,
                    ];
                    $recodset = new \App\Models\Job($jobData);
                    $recodset->save();
                    $send_notification = 'false'; //incase of audio
                }


                if ($withoutKey == $length_of_arr - 1) { // condition for last loop index
                    $content = \App\Models\Content::where('_id', $parent_id)->first();
                    $updateddata['status'] = 'active';
                    $content->update($updateddata);
                }

            }

            $notification_arr = Array(
                'send_notification' => $send_notification,
                'artist_id' => $artist_id,
                'bucket_code' => $bucket_code,
                'player_type' => '',
                'embed_code' => '',
                'url' => '',
                'content_id' => $parent_id,
            );

            $this->sendNotifications($notification_arr);
        }

//--------------------------------------------Purge Cache Key--------------------------------------------------------
        $platforms = ['android', 'ios', 'web'];
        foreach ($platforms as $cachekey => $platform) {
            $cachetag_name = $platform . '_' . $bucket_id . "_contents";
            $env_cachetag = env_cache_tag_key($cachetag_name);              //  ENV_PLATFORM_BUCKETID_contents

            $this->caching->flushTag($env_cachetag);
        }
//--------------------------------------------Purge Cache Key--------------------------------------------------------

        $results['parent_id'] = !empty($content) ? $content->_id : null;

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function sendNotifications($notification_arr)
    {

        $send_notification = !empty($notification_arr['send_notification']) ? $notification_arr['send_notification'] : '';
        $artist_id = !empty($notification_arr['artist_id']) ? $notification_arr['artist_id'] : '';
        $bucket_code = !empty($notification_arr['bucket_code']) ? $notification_arr['bucket_code'] : '';
        $player_type = !empty($notification_arr['player_type']) ? $notification_arr['player_type'] : '';
        $embed_code = !empty($notification_arr['embed_code']) ? $notification_arr['embed_code'] : '';
        $url = !empty($notification_arr['url']) ? $notification_arr['url'] : '';
        $content_id = !empty($notification_arr['content_id']) ? $notification_arr['content_id'] : '';

        //Send Notification ONLY OF PHOTOS
        if ($send_notification == 'true') {
            $test = (env('APP_ENV', 'stg') == 'production') ? "false" : "true";
            $artist = \App\Models\Cmsuser::with('artistconfig')->where('_id', '=', $artist_id)->first();

            if ($artist) {
                $test_topic_id = (isset($artist['artistconfig']['fmc_default_topic_id_test']) && $artist['artistconfig']['fmc_default_topic_id_test'] != '') ? trim($artist['artistconfig']['fmc_default_topic_id_test']) : "";
                $production_topic_id = (isset($artist['artistconfig']['fmc_default_topic_id']) && $artist['artistconfig']['fmc_default_topic_id'] != '') ? trim($artist['artistconfig']['fmc_default_topic_id']) : "";
                $artistname = $artist->first_name . ' ' . $artist->last_name;
                $topic_id = ($test == 'true') ? $test_topic_id : $production_topic_id;
                $deeplink = $bucket_code;

                $type = "photo";
                if (isset($player_type) && isset($player_type)) {
                    if (isset($embed_code) && $embed_code != "" || isset($url) && $url != "") {
                        $type = "video";
                    }
                }

                $title = "The " . ucwords($artistname) . " Offical App";
                $body = ucwords($artistname) . " has posted a " . $type;

                $notificationParams = [
                    'artist_id' => $artist_id, 'topic_id' => $topic_id, 'deeplink' => $deeplink, 'content_id' => $content_id, 'title' => $title, 'body' => $body,
                ];
                $sendNotification = $this->pushnotification->sendNotificationToTopic($notificationParams);
            }
        }// $send_notification

    }

    public function pollStats($request)
    {
        $requestData = $request->all();
        $content_id = $requestData['content_id'];

        $pollContent = \App\Models\Pollresult::with('polloption')->where('content_id', $content_id)->get();
        $total_votes = count($pollContent);

        foreach ($pollContent as $key => $val) {
            $option_id = $val['option_id'];
            $pollresults[$key]['votes'] = \App\Models\Pollresult::where('option_id', $option_id)->count();
            $pollresults[$key]['optin_id'] = !empty($val['polloption']['_id']) ? $val['polloption']['_id'] : '';
            $pollresults[$key]['name'] = !empty($val['polloption']['name']) ? $val['polloption']['name'] : $val['polloption']['photo'];
            $pollresults[$key]['votes_in_percentage'] = ($pollresults[$key]['votes'] / $total_votes) * 100;
        }
        $pollresults = !empty($pollresults) ? $pollresults : [];
        $results['results'] = array_unique($pollresults, SORT_REGULAR);
        $results['total_votes'] = !empty($total_votes) ? $total_votes : '';

        return $results;
    }


    public function processContent($request)
    {
        $error_messages             =   [];
        $results                    =   [];
        $requestData                =   array_except($request->all(), ['_method', '_token']);
        $artist_id                  =   !empty($requestData['artist_id']) ? $requestData['artist_id'] : '';
        $is_album                   =   !empty($requestData['is_album']) ? $requestData['is_album'] : 'false';
        $send_notification          =   (!empty($requestData['send_notification'])) ? (string)$requestData['send_notification'] : "false";
        $parent_id                  =   !empty($requestData['parent_id']) ? $requestData['parent_id'] : '';
        $customer_id                =   !empty($requestData['customer_id']) ? $requestData['customer_id'] : '';
        $age_restriction            =   !empty($requestData['age_restriction']) ? intval($requestData['age_restriction']) : '';
        $default_platforms          =    ['android', 'ios', 'web'];
        $level                      =   1;
        $new_album                  =   'false';
        $new_album_id               =   '';

        $album_cover                =   [];
        $album_contents             =   [];
        $found_albumn_cover         =   'false';

        $platforms                  =   (!empty($requestData['platforms']) && is_array($requestData['platforms']) && count($requestData['platforms']) > 0)  ? array_values($requestData['platforms']) : $default_platforms;



        \Log::info('processContentt LOGGER - request_payload  ', $requestData);

        //Overriding variable logic
        if ($is_album == 'true') {
            $new_album = 'true';
            $is_album = 'true';
        }

        if ($parent_id != '') {
            $new_album = 'false';
            $is_album = 'true';
        }


        $bucket_id = (isset($requestData['bucket_id'])) ? $requestData['bucket_id'] : '';
        $bucket = \App\Models\Bucket::where('_id', '=', $bucket_id)->first();
        $bucket_code = (!empty($bucket) & !empty($bucket['code'])) ? trim($bucket['code']) : ""; // for sending notification

        $default_notification_params = ['send_notification' => $send_notification, 'bucket_code' => $bucket_code, 'artist_id' => $artist_id];


        if (!empty($requestData['medias']) && isset($requestData['medias']) && count($requestData['medias']) > 0) {

            $total_media_cnt = count($requestData['medias']);
//
//            Log::info('total_media_cnt : '. $total_media_cnt);
//            Log::info('################################################');
//
//            Log::info('Uploaded Media : ', $requestData['medias']);
//            Log::info('################################################');

            foreach ($requestData['medias'] as $key => $val) {

                $has_photo = 'false';
                $content = $val;
                $process_valid_content      =   'false';


                $coins                      =   !empty($val['coins']) ? intval($val['coins']) : 0;
                $ordering                   =   !empty($val['ordering']) ? intval($val['ordering']) : 0;
                $commercial_type            =   !empty($val['commercial_type']) ? $val['commercial_type'] : "free";
                $pin_to_top                 =   !empty($val['pin_to_top']) ? $val['pin_to_top'] : false;
                $source                     =   !empty($val['source']) ? $val['source'] : 'custom';
                $status                     =   !empty($val['status']) ? $val['status'] : "active";
                $eighteen_plus_age_content  =   !empty($val['18_plus_age_content']) ? $val['18_plus_age_content'] : "true";
                $is_commentbox_enable       =   !empty($val['is_commentbox_enable']) ? $val['is_commentbox_enable'] : "true";
                $is_test_enable             =   !empty($val['is_test_enable']) ? $val['is_test_enable'] : "false";


                $webview_url    = !empty($val['webview_url']) ? $val['webview_url'] : '';
                $webview_label  = !empty($val['webview_label']) ? $val['webview_label'] : '';


                if (!empty($webview_url)) {
                    $content['webview_url'] = $webview_url;
                }

                if (!empty($webview_label)) {
                    $content['webview_label'] = $webview_label;
                }

                if (!empty($customer_id) && $customer_id != 'xxxxx' && $customer_id != '') {
                    $content['customer_id'] = $customer_id;
                }


                if (!empty($age_restriction) && $age_restriction != '') {
                    $content['age_restriction'] = $age_restriction;
                }


                //Manage For Photo
                if (!empty($val['photo']) || !empty($val['s3_photo'])) {
                    $content['type'] = 'photo';
                    $process_valid_content = 'true';
                    $has_photo = 'true';
                }

                //Manage For Internal Video
                if (!empty($val['video']) || !empty($val['s3_video']) || !empty($val['player_type'])) {
                    $content['type'] = 'video';
                    $process_valid_content = 'true';
                }

                //Manage For Internal Audio
                if (!empty($val['audio']) || !empty($val['s3_audio'])) {
                    $content['type'] = 'audio';
                    $process_valid_content = 'true';
                }


                //Manage For Text
                if ($process_valid_content == 'false') {
                    $content['type'] = 'photo';
                    $process_valid_content = 'true';
                }


                if ($process_valid_content == 'true') {

                    // Default values
                    $content['artist_id'] = $requestData['artist_id'];
                    $content['bucket_id'] = $bucket_id;
                    $content['level'] = $level;
                    $content['commercial_type'] = $commercial_type;
                    $content['coins'] = $coins;
                    $content['ordering'] = $ordering;
                    $content['pin_to_top'] = $pin_to_top;
                    $content['source'] = $source;
                    $content['18_plus_age_content'] = $eighteen_plus_age_content;
                    $content['is_commentbox_enable'] = $is_commentbox_enable;
                    $content['is_test_enable'] = $is_test_enable;
                    $content['commercial_type'] = $commercial_type;
                    $content['stats'] = Config::get('app.stats');
                    $content['likes'] = ['internal' => 0];
                    $content['comments'] = ['internal' => 0];
                    $content['flags'] = Config::get('app.content.flags');
                    $content['platforms'] =          $platforms;
                    $content['bucket_code'] = $bucket_code;
                    $content['send_notification'] = $send_notification;
                    $content['published_at'] = new \MongoDB\BSON\UTCDateTime(strtotime(date('Y-m-d H:i:s')) * 1000);
                    $content['status'] = 'active';
                    $content['is_album'] = 'false';

                    $content['has_photo'] = $has_photo;


                    if ($total_media_cnt == 1 && $new_album == 'true') {

                        $album_cover = $content;
                        $album_cover['commercial_type'] = !isset($val['commercial_type']) ? trim($val['commercial_type']) : $commercial_type;
                        $album_cover['coins'] = !isset($val['coins']) ? intval($val['coins']) : $coins;
                        $found_albumn_cover = 'true';

                    } else {

                        if ((array_key_exists('cover', $val) && !is_null($val['cover']) && $val['cover'] != 'false') && !empty($val['cover'])) {

                            $album_cover = $content;
                            $album_cover['coins'] = !isset($val['coins']) ? intval($val['coins']) : $coins;
                            $album_cover['commercial_type'] = !isset($val['commercial_type']) ? trim($val['commercial_type']) : $commercial_type;
                            $found_albumn_cover = 'true';

                        } else {
                            array_push($album_contents, $content);

                        }
                    }


                }//$process_valid_content
            }



 //            Log::info('found_albumn_cover ', $found_albumn_cover);
            //Log::info('album_contents ', $album_contents);


            if ($new_album == 'true') {

                // Cover Imange Not found then force fully assgin first object as album cover
                if (empty($album_cover) && $found_albumn_cover == 'false') {
                    $album_cover = $album_contents[0];
                    $album_contents = array_shift($album_contents);
                }
                $album_cover['status'] = 'inactive';
                $album_cover['is_album'] = 'true';


                //Insert Album Cover First
                $store_album_cover = $this->processPhoto($album_cover);
                if (!empty($store_album_cover) && !empty($store_album_cover['results']['content_id'])) {
                    $new_album_id = $store_album_cover['results']['content_id'];
                    $parent_id = $new_album_id;
                }

            }

//            var_dump($total_media_cnt);



//            Log::info('################################################');
//            Log::info('Uploaded Album Cover : ', $album_cover);
//            Log::info('################################################');
//            Log::info('Uploaded Album Content : ', $album_contents);
//            Log::info('################################################');


            foreach ($album_contents as $key => $val) {

                if ($parent_id != '') {

                    if (!empty($val['commercial_type']) && $val['commercial_type'] == 'partial_paid') {
                        $val['commercial_type'] = 'free';
                    }

                    $val['level'] = 2;
                    $val['parent_id'] = $parent_id;
                }

                if (!empty($val) && !empty($val['type']) && $val['type'] == 'photo') {

                    $contentData = $this->processPhoto($val);

                } elseif (!empty($val) && !empty($val['type']) && $val['type'] == 'video') {
                    $contentData = $this->processVideo($val);

                } elseif (!empty($val) && !empty($val['type']) && $val['type'] == 'audio') {

                    $contentData = $this->processAudio($val);

                }

                // Send Notification for level 1 content
                if ($parent_id == '') {
                    $notification_params = $default_notification_params;
                    if (!empty($contentData) && !empty($contentData['results']['content_id'])) {
                        $content_id = $contentData['results']['content_id'];
                        $notification_params['content_id'] = $parent_id;
                    }
                    $this->processContentNotification($notification_params);
                }

            }


            if ($new_album == 'true' && $new_album_id != '') {

                $content = \App\Models\Content::where('_id', $new_album_id)->first();
                $updateddata['status'] = 'active';
                $content->update($updateddata);

            }

            // Send Notification on create new album or content added to existing album
            if ($parent_id != '') {
                $notification_params = $default_notification_params;
                $notification_params['content_id'] = $parent_id;
                $this->processContentNotification($notification_params);
            }


        }//


        //--------------------------------------------Purge Cache Key--------------------------------------------------------

        $parent_id = (!empty($parent_id) && $parent_id != '') ? trim($parent_id) : "";
        $purge_result = $this->awsElasticCacheRedis->purgeContentListCache(['bucket_id' => $bucket_id, 'parent_id' => $parent_id]);

        //--------------------------------------------Purge Cache Key--------------------------------------------------------


        //--------------------------------------------Purge CF Cache ---------------------------
        if (env('APP_ENV', 'stg') == 'production') {
            try {
                $invalidate_result = $this->awscloudfrontService->invalidateContents();
            } catch (Exception $e) {
                $error_messages = [
                    'error' => true, 'type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()
                ];
                Log::info('AwsCloudfront - Invalidation  : Fail ', $error_messages);
            }
        }


        $results['parent_id'] = !empty($parent_id) ? $parent_id : null;

//        Log::info('processContent - request_payload  : Fail '. var_dump($requestData));

        return ['error_messages' => $error_messages, 'results' => $results];

    }


    private function processPhoto($mediaObject)
    {

        $error_messages = [];
        $results = [];
        $photo_obj = [];
        $add_watermark = true;

        $photo_file = !empty($mediaObject['photo']) ? $mediaObject['photo'] : '';
        if (!empty($photo_file)) {
            $parmas     =   ['file' => $photo_file, 'type' => 'contents', 'add_watermark' => $add_watermark, 'artist_id' => $mediaObject['artist_id']];
            $photo      =   $this->kraken->uploadToAws($parmas);
            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                $photo_obj  = $photo['results'];
            }
        }


        $base_raw_photo_url = Config::get('product.' . env('PRODUCT') . '.s3.base_urls.photo');
        $photo_url = !empty($mediaObject['s3_photo']) ? $mediaObject['s3_photo'] : '';

        if (!empty($photo_url)) {
            $photo_url = $base_raw_photo_url . $photo_url;
            $kraken_param = [
                'url' => $photo_url, 'type' => 'contents', 'add_watermark' => $add_watermark, 'artist_id' => $mediaObject['artist_id']
            ];

            $photo_url = !empty($photo_url) ? $photo_url : '';
            $photo_obj = [
                'thumb' => $photo_url, 'thumb_width' => null, 'thumb_height' => null,
                'cover' => $photo_url, 'cover_width' => null, 'cover_height' => null,
                'medium' => $photo_url, 'medium_width' => null, 'medium_height' => null
            ];
        }

        $photo_obj = !empty($photo_obj) ? $photo_obj : Config::get('kraken.contents_photo');

        $mediaObject['photo'] = $photo_obj;
        $excludes = ['_method', '_token', 'send_notification'];
        $storeData = array_except($mediaObject, $excludes);

        $contentObject = $this->repObj->storeContent($storeData);


        //===========================================Jobs Store=================================================================
        //Push to Queue Job For image kraken process
        if ($contentObject && isset($contentObject['_id']) && $contentObject['_id'] != '' && !empty($photo_url)) {

            $content_id = $contentObject['_id'];
            $payload = ['content_id' => $content_id, 'kraken_param' => $kraken_param];
            $jobData = ['label' => 'CreateKrakenImageJobForImage', 'type' => 'image_process', 'payload' => $payload, 'status' => "scheduled", 'delay' => 0, 'retries' => 0];
            $recodset = new \App\Models\Job($jobData);
            $recodset->save();
        }

        //===========================================Jobs Store=================================================================


        $results['content']     =   !empty($contentObject) ? $contentObject : null;
        $results['content_id']  =   (!empty($contentObject) && isset($contentObject['_id'])) ? $contentObject['_id'] : null;

        return ['error_messages' => $error_messages, 'results' => $results];


    }


    private function processVideo($mediaObject)
    {

        $error_messages = [];
        $results = [];
        $storeData = $mediaObject;
        $photo_obj = [];
        $add_watermark = true;
        $base_raw_video_url = Config::get('product.' . env('PRODUCT') . '.s3.base_urls.raw_video');


        $photo_file = !empty($mediaObject['photo']) ? $mediaObject['photo'] : '';
        if (!empty($photo_file)) {
            $parmas     =   ['file' => $photo_file, 'type' => 'contents', 'add_watermark' => $add_watermark, 'artist_id' => $mediaObject['artist_id']];
            $photo      =   $this->kraken->uploadToAws($parmas);
            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                $photo_obj  = $photo['results'];
            }
        }

        $base_raw_photo_url = Config::get('product.' . env('PRODUCT') . '.s3.base_urls.photo');
        $photo_url = !empty($mediaObject['s3_photo']) ? $mediaObject['s3_photo'] : '';

        if (!empty($photo_url)) {
            $photo_url = $base_raw_photo_url . $photo_url;
            $kraken_param = [
                'url' => $photo_url, 'type' => 'contents', 'add_watermark' => $add_watermark, 'artist_id' => $mediaObject['artist_id']
            ];

            $photo_url = !empty($photo_url) ? $photo_url : '';
            $photo_obj = [
                'thumb' => $photo_url, 'thumb_width' => null, 'thumb_height' => null,
                'cover' => $photo_url, 'cover_width' => null, 'cover_height' => null,
                'medium' => $photo_url, 'medium_width' => null, 'medium_height' => null
            ];
        }

        $photo_obj = !empty($photo_obj) ? $photo_obj : Config::get('kraken.contents_photo');


        $vod_job_data = [];
        $video_url = '';
        $video_file = !empty($mediaObject['video']) ? $mediaObject['video'] : '';
        $video_s3_file = !empty($mediaObject['s3_video']) ? $mediaObject['s3_video'] : '';
        $orientation = (!empty($mediaObject['orientation'])) ? strtolower(trim($mediaObject['orientation'])) : '';
        $player_type = (!empty($mediaObject['player_type'])) ? strtolower(trim($mediaObject['player_type'])) : '';
        $embed_code = (!empty($mediaObject['embed_code'])) ? $mediaObject['embed_code'] : '';
        $duration = (!empty($mediaObject['duration'])) ? strtolower(trim($mediaObject['duration'])) : '';
        $partial_play_duration = (!empty($mediaObject['partial_play_duration'])) ? strtolower(trim($mediaObject['partial_play_duration'])) : '';
        $url = (!empty($mediaObject['url'])) ? strtolower(trim($mediaObject['url'])) : '';

        if (!empty($video_file)) {
            /* OLD CODE
            //upload to local drive
            $upload = $mediaObject['video'];
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
            $s3 = Storage::createS3Driver(Config::get('product.' . env('PRODUCT') . '.s3.rawvideos'));
            $response = $s3->put($object_upload_path, file_get_contents($object_source_path), 'public');

            $object_name = $imageName;
            $vod_job_data = [
                'status' => 'submitted', 'object_name' => $object_name, 'object_path' => $object_upload_path, 'object_extension' => $obj_extension, 'bucket' => 'armsrawvideos'
            ];

            $video_url = $base_raw_video_url . $vod_job_data['object_name'];
            @unlink($fullpath);
            */

            $video_file->video_lang_default = isset($mediaObject['video_lang_default']) ? $mediaObject['video_lang_default'] : false;
            $video_file->video_lang         = isset($mediaObject['video_lang']) ? $mediaObject['video_lang'] : 'eng';
            $video_file->video_lang_label   = isset($mediaObject['video_lang_label']) ? $mediaObject['video_lang_label'] : 'ENGLISH';
            $vod_job_data = $this->uploadContentVideoFile($video_file);

            if($vod_job_data) {
                $storeData['video'] = [];
                array_set($setOfContentData, 'vod_job_data', [$vod_job_data]);
                array_set($setOfContentData, 'video_status', 'uploaded');

                if(isset($vod_job_data['video_url_key']) && $vod_job_data['video_url_key']) {
                    $video_url_key = $vod_job_data['video_url_key'];
                    $video_url_raw = isset($vod_job_data['video_url_raw']) ? $vod_job_data['video_url_raw'] : '';
                    if($video_url_key == 'eng') {
                        array_set($storeData['video'], 'url', $video_url_raw);
                    }

                    if($video_url_key && $video_url_raw) {
                        array_set($storeData['video'], $video_url_key, $video_url_raw);
                    }
                }
            }
        }

        if (!empty($video_s3_file)) {
            $video_url = $base_raw_video_url . $video_s3_file;
            $object_name = $video_s3_file;
            $vod_job_data = [
                'orientation' => $orientation, 'status' => 'submitted', 'object_name' => $object_name, 'object_path' => $video_s3_file, 'object_extension' => pathinfo($video_s3_file, PATHINFO_EXTENSION), 'bucket' => 'armsrawvideos'
            ];
        }

        if ($player_type == 'internal') {
            array_set($storeData, 'status', 'inactive');
            array_set($storeData, 'vod_job_data', [$vod_job_data]);
            array_set($storeData, 'video_status', 'uploaded');
            $url = (!empty($vod_job_data) && !empty($vod_job_data['object_name'])) ? $video_url : '';
        }

        if (!empty($player_type)) {
            array_set($storeData['video'], 'player_type', $player_type);
        }

        if (!empty($embed_code)) {
            array_set($storeData['video'], 'embed_code', $embed_code);
        }

        if (!empty($url)) {
            array_set($storeData['video'], 'url', $url);
        }

        $videoObj = array_merge($storeData['video'], $photo_obj);
        $storeData['video'] = $videoObj;

        $excludes = ['_method', '_token', 'send_notification'];
        $storeData = array_except($storeData, $excludes);

        if (!empty($duration)) {
            array_set($storeData, 'duration', $duration);
            // Convert Duration into milliseconds and save in duration_ms attribute
            $duration_ms = self::convertDurationInMilliseconds($duration);
            array_set($storeData, 'duration_ms', $duration_ms);
        }

        if (!empty($partial_play_duration)) {
            array_set($storeData, 'partial_play_duration', $partial_play_duration);
        }


        $contentObject = $this->repObj->storeContent($storeData);


        if ($contentObject && isset($contentObject['_id']) && $contentObject['_id'] != '') {
            $content_id = $contentObject['_id'];

            //==========================================Transcode Video==========================================================
            //Push to Queue Job For transcode Video
            if ($contentObject && $vod_job_data &&  isset($vod_job_data['status']) && $vod_job_data['status'] == 'uploaded') {

                /* OLD CODE
                $payload    =   ['content_id' => $content_id, 'send_notification' => '', 'object_name' => $object_name,];
                $payload    =   array_merge($payload, $contentObject['vod_job_data']);
                $jobData    =   ['label' => 'CreateHLSTranscodeJobForVideo', 'type' => 'transcode_video', 'payload' => $payload, 'status' => "scheduled", 'delay' => 0, 'retries' => 0,];

                $recodset   =   new \App\Models\Job($jobData);
                $recodset->save();
                */
                $this->createJobForTranscoding($content_id, $vod_job_data);
            }
            //==========================================Transcode Video==========================================================


            if (!empty($photo_url)) {
                $payload = ['content_id' => $content_id, 'kraken_param' => $kraken_param];
                $jobData = ['label' => 'CreateKrakenImageJobForImage', 'type' => 'image_process', 'payload' => $payload, 'status' => "scheduled", 'delay' => 0, 'retries' => 0];
                $recodset = new \App\Models\Job($jobData);
                $recodset->save();
            }

        }


        $results['content']     =   !empty($contentObject) ? $contentObject : null;
        $results['content_id']  =   (!empty($contentObject) && isset($contentObject['_id'])) ? $contentObject['_id'] : null;

        return ['error_messages' => $error_messages, 'results' => $results];

    }


    private function processAudio($mediaObject)
    {

        $error_messages = [];
        $results = [];
        $storeData = $mediaObject;
        $photo_obj = [];
        $add_watermark = true;
        $base_raw_audio_url = Config::get('product.' . env('PRODUCT') . '.s3.base_urls.audio');


        $photo_file = !empty($mediaObject['photo']) ? $mediaObject['photo'] : '';
        if (!empty($photo_file)) {
            $parmas     =   ['file' => $photo_file, 'type' => 'contents', 'add_watermark' => $add_watermark, 'artist_id' => $mediaObject['artist_id']];
            $photo      =   $this->kraken->uploadToAws($parmas);
            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                $photo_obj  = $photo['results'];
            }
        }

        $base_raw_photo_url = Config::get('product.' . env('PRODUCT') . '.s3.base_urls.photo');
        $photo_url = !empty($mediaObject['s3_photo']) ? $mediaObject['s3_photo'] : '';

        if (!empty($photo_url)) {
            $photo_url = $base_raw_photo_url . $photo_url;
            $kraken_param = [
                'url' => $photo_url, 'type' => 'contents', 'add_watermark' => $add_watermark, 'artist_id' => $mediaObject['artist_id']
            ];

            $photo_url = !empty($photo_url) ? $photo_url : '';
            $photo_obj = [
                'thumb' => $photo_url, 'thumb_width' => null, 'thumb_height' => null,
                'cover' => $photo_url, 'cover_width' => null, 'cover_height' => null,
                'medium' => $photo_url, 'medium_width' => null, 'medium_height' => null
            ];
        }

        $photo_obj = !empty($photo_obj) ? $photo_obj : Config::get('kraken.contents_photo');



        $aod_job_data = [];
        $audio_url = '';
        $audio_file = !empty($mediaObject['audio']) ? $mediaObject['audio'] : '';
        $audio_s3_file = !empty($mediaObject['s3_audio']) ? $mediaObject['s3_audio'] : '';
        $duration = (!empty($mediaObject['duration'])) ? strtolower(trim($mediaObject['duration'])) : '';
        $url = (!empty($mediaObject['url'])) ? $mediaObject['url'] : '';

        if (!empty($audio_file)) {
            //upload to local drive
            $upload = $mediaObject['audio'];
            $folder_path = 'uploads/contents/audio/';
            $obj_path = public_path($folder_path);
            $obj_extension = $upload->getClientOriginalExtension();
            $imageName = time() . '_' . str_slug($upload->getRealPath()) . '.' . $obj_extension;
            $fullpath = $obj_path . $imageName;
            $upload->move($obj_path, $imageName);
            chmod($fullpath, 0777);

            //upload to aws
            $object_source_path = $fullpath;
            $object_upload_path = $imageName;
            $s3 = Storage::createS3Driver(Config::get('product.' . env('PRODUCT') . '.s3.rawaudios'));
            if (env('APP_ENV', 'stg') != 'local') {
                $response = $s3->put($object_upload_path, file_get_contents($object_source_path), 'public');
            }
            $aod_job_data = [
                'status' => 'submitted', 'object_name' => $imageName, 'object_path' => $object_upload_path, 'object_extension' => $obj_extension, 'bucket' => 'armsrawaudios'
            ];

            $audio_url = $base_raw_audio_url . $aod_job_data['object_name'];
            @unlink($fullpath);
        }

        if (!empty($audio_s3_file)) {
            $audio_url = $base_raw_audio_url . $audio_s3_file;
            $aod_job_data = [
                'status' => 'submitted', 'object_name' => $audio_s3_file, 'object_path' => $audio_s3_file, 'object_extension' => pathinfo($audio_s3_file, PATHINFO_EXTENSION), 'bucket' => 'armsrawaudios'
            ];
        }


        array_set($storeData, 'status', 'inactive');
        array_set($storeData, 'aod_job_data', $aod_job_data);
        array_set($storeData, 'audio_status', 'uploaded');
        $url = (!empty($aod_job_data) && !empty($aod_job_data['object_name'])) ? $audio_url : '';

        if (!empty($duration)) {
            array_set($storeData, 'duration', $duration);
            // Convert Duration into milliseconds and save in duration_ms attribute
            $duration_ms = self::convertDurationInMilliseconds($duration);
            array_set($storeData, 'duration_ms', $duration_ms);
        }

        if (!empty($url)) {
            array_set($storeData['audio'], 'url', $url);
        }

        $audioObj = array_merge($storeData['audio'], $photo_obj);
        $storeData['audio'] = $audioObj;

        $excludes = ['_method', '_token', 'send_notification'];
        $storeData = array_except($storeData, $excludes);

        $contentObject = $this->repObj->storeContent($storeData);


        if ($contentObject && isset($contentObject['_id']) && $contentObject['_id'] != '') {
            $content_id = $contentObject['_id'];

            //==========================================Mediaconvert Video==========================================================
            //Push to Queue Job For transcode media convert
            if ($contentObject && isset($contentObject['audio_status']) && $contentObject['audio_status'] == 'uploaded') {

                $payload = [
                    'content_id' => $content_id, 'send_notification' => '',
                ];

                $payload = array_merge($payload, $contentObject['aod_job_data']);

                $jobData = [
                    'label' => 'CreateHLSTranscodeJobForAudio', 'type' => 'transcode_audio', 'payload' => $payload, 'status' => "scheduled", 'delay' => 0, 'retries' => 0,
                ];

                $recodset = new \App\Models\Job($jobData);
                $recodset->save();
            }
            //==========================================Mediaconvert Video==========================================================


            if (!empty($photo_url)) {
                $payload = ['content_id' => $content_id, 'kraken_param' => $kraken_param];
                $jobData = ['label' => 'CreateKrakenImageJobForImage', 'type' => 'image_process', 'payload' => $payload, 'status' => "scheduled", 'delay' => 0, 'retries' => 0];
                $recodset = new \App\Models\Job($jobData);
                $recodset->save();
            }

        }


        $results['content'] = !empty($contentObject) ? $contentObject : null;
        $results['content_id'] = (!empty($contentObject) && isset($contentObject['_id'])) ? $contentObject['_id'] : null;

        return ['error_messages' => $error_messages, 'results' => $results];

    }

    private function processContentNotification($params = array())
    {


        $send_notification = !empty($params['send_notification']) ? $params['send_notification'] : '';
        $artist_id = !empty($params['artist_id']) ? $params['artist_id'] : '';
        $bucket_code = !empty($params['bucket_code']) ? $params['bucket_code'] : '';
        $player_type = !empty($params['player_type']) ? $params['player_type'] : '';
        $embed_code = !empty($params['embed_code']) ? $params['embed_code'] : '';
        $url = !empty($params['url']) ? $params['url'] : '';
        $content_id = !empty($params['content_id']) ? $params['content_id'] : '';


        //Send Notification ONLY OF PHOTOS
        if ($send_notification == 'true' && $artist_id != '') {
            $test = (env('APP_ENV', 'stg') == 'production') ? "false" : "true";
            $artist = \App\Models\Cmsuser::with('artistconfig')->where('_id', '=', $artist_id)->first();

            if ($artist) {
                $test_topic_id = (isset($artist['artistconfig']['fmc_default_topic_id_test']) && $artist['artistconfig']['fmc_default_topic_id_test'] != '') ? trim($artist['artistconfig']['fmc_default_topic_id_test']) : "";
                $production_topic_id = (isset($artist['artistconfig']['fmc_default_topic_id']) && $artist['artistconfig']['fmc_default_topic_id'] != '') ? trim($artist['artistconfig']['fmc_default_topic_id']) : "";
                $artistname = $artist->first_name . ' ' . $artist->last_name;
                $topic_id = ($test == 'true') ? $test_topic_id : $production_topic_id;
                $deeplink = $bucket_code;

                $type = "photo";
                if (isset($player_type) && isset($player_type)) {
                    if (isset($embed_code) && $embed_code != "" || isset($url) && $url != "") {
                        $type = "video";
                    }
                }

                $title = "The " . ucwords($artistname) . " Offical App";
                $body = ucwords($artistname) . " has posted a " . $type;

                $notificationParams = [
                    'artist_id' => $artist_id, 'topic_id' => $topic_id, 'deeplink' => $deeplink, 'content_id' => $content_id, 'title' => $title, 'body' => $body,
                ];
                $sendNotification = $this->pushnotification->sendNotificationToTopic($notificationParams);
            }
        }// $send_notification


    }


    /**
     * Store Content In Database when request is from CMS
     *
     *
     * @param   request
     *
     * @return  Boolean
     *
     * @author
     * @since
     */
    public function storeContent($request)
    {
        $content = null;
        $data                       = array_except($request->all(), ['_method', '_token']);
        $type                       = $data['type'] ? $data['type'] : 'photo';
        $is_album                   = !empty($data['is_album']) ? $data['is_album'] : 'false';
        $send_notification          = (!empty($data['send_notification'])) ? (string) $data['send_notification'] : "false";
        $pin_to_top                 = !empty($data['pin_to_top']) ? $data['pin_to_top'] : false;
        $source                     = !empty($data['source']) ? $data['source'] : 'custom';
        $status                     = !empty($data['status']) ? $data['status'] : "active";
        $is_test_enable             = !empty($data['is_test_enable']) ? $data['is_test_enable'] : "false";
        $eighteen_plus_age_content  = !empty($data['18_plus_age_content']) ? $data['18_plus_age_content'] : "true";
        $is_commentbox_enable       = !empty($data['is_commentbox_enable']) ? $data['is_commentbox_enable'] : "true";
        $parent_id                  = !empty($data['parent_id']) ? $data['parent_id'] : '';
        $genres                     = !empty($data['genres']) ? $data['genres'] : [];
        $level                      = !empty($data['level']) ? intval(trim($data['level']))  : 1;
	 $is_producer                  = !empty($data['is_producer']) ? $data['is_producer'] : 'false';
        $producer_id                  = !empty($data['producer_id']) ? $data['producer_id'] : '';
        if (!empty($parent_id)) {
            array_set($data, 'parent_id', $parent_id);
        }
        array_set($data, 'type', $type);
        array_set($data, 'is_album', $is_album);

        $bucket_id = (isset($data['bucket_id'])) ? $data['bucket_id'] : '';
        $bucket = \App\Models\Bucket::where('_id', '=', $bucket_id)->first();
        $bucket_code = (!empty($bucket) & !empty($bucket['code'])) ? trim($bucket['code']) : "";

        // Meta Data
        $meta_data_default = array(
            'title' => '',
            'description' => '',
            'keywords' => '',
        );
        $meta_data = isset($data['meta']) ? $data['meta'] : $meta_data_default;
        $age_restriction = isset($data['age_restriction']) ? $data['age_restriction'] : 18;

        // Generate unique id for content
        $unique_id = $this->generateUniqueId();

        $default_arr = Array(
            'ordering'              => 0,
            'pin_to_top'            => $pin_to_top,
            'status'                => $status,
            'source'                => $source,
            'is_test_enable'        => $is_test_enable,
            '18_plus_age_content'   => $eighteen_plus_age_content,
            'is_commentbox_enable'  => $is_commentbox_enable,
            'stats'                 => Config::get('app.stats'),
            'likes'                 => ['internal' => 0],
            'comments'              => ['internal' => 0],
            'flags'                 => Config::get('app.content.flags'),
            'bucket_code'           => $bucket_code,
            'send_notification'     => $send_notification,
            'published_at'          => new \MongoDB\BSON\UTCDateTime(strtotime(date('Y-m-d H:i:s')) * 1000),
            'meta'                  => $meta_data,
            'age_restriction'       => $age_restriction,
            'unique_id'             => $unique_id,
            'genres'                => $genres,
	    'level'                 => $level,
	    'is_producer'           => $is_producer,
            'producer_id'           =>$producer_id
        );

         if ($type == 'photo') {
            $content = $this->contentPhotoStore($data, $default_arr);
        }
        elseif ($type == 'video') {
            // Set Video Addition Parameters
            $default_arr = $this->updateVideoData($data, $default_arr);

            $content = $this->contentVideoStore($data, $default_arr);
        }
        elseif ($type == 'audio') {

            $content = $this->contentAudioStore($data, $default_arr);
        }
        elseif ($type == 'poll') {

            $content = $this->contentPollStore($data, $default_arr);
        }

        if($content) {
            if(isset($content['results']) ) {
                $content['results']['content_type'] = $type;
            }
        }

        $artist_id  = $bucket['artist_id'];

        $languages  = $this->artistservice->getArtistCode2LanguageArray($artist_id);


        // ---------------------    Purge Cache Key        ---------------------
        $parent_id      = (!empty($content['parent_id']) && $content['parent_id'] != '') ? trim($content['parent_id']) : "";
        $content_id     = (!empty($content['_id']) && $content['_id'] != '') ? trim($content['_id']) : "";
        $purge_cache_params = [
            'content_id'=> $content_id,
            'bucket_id' => $bucket_id,
            'parent_id' => $parent_id,
            'languages' => $languages
        ];
        $purge_result   = $this->awsElasticCacheRedis->purgeContentListCache($purge_cache_params);
        // ---------------------    Purge Cache Key        ---------------------

        if (env('APP_ENV', 'stg') == 'production') {
            try {
                $invalidate_result = $this->awscloudfrontService->invalidateContents();
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

        return $content;
    }


    /**
     * Store Photo Content In Database when request is from CMS
     *
     *
     * @param   array   $requestData
     * @param   array   $default_arr
     *
     * @return  array
     *
     * @author
     * @since
     */
    public function contentPhotoStore($requestData, $default_arr)
    {
        $error_messages = [];
        $results        = [];
        $is_album       = isset($requestData['is_album']) ? $requestData['is_album'] : 'false';
        $status         = $default_arr['status'];
        $parent_id      = !empty($requestData['parent_id']) ? $requestData['parent_id'] : '';
        $customer_id    = $this->jwtauth->customerIdFromToken();

        $add_watermark          = true;
        $album_with_content     = [];
        $album_with_out_content = [];
        $is_cover               = '';
        $kraken_param           = [];

        foreach ($requestData['medias'] as $key => $val) {

            $name               = !empty($val['name']) ? $val['name'] : '';
            $slug               = !empty($val['name']) ? str_slug($val['name']) : '';
            $caption            = !empty($val['caption']) ? $val['caption'] : '';
            $language_id        = !empty($val['language_id']) ? $val['language_id'] : '';
            $coins              = !empty($val['coins']) ? intval($val['coins']) : 0;
            $commercial_type    = !empty($val['commercial_type']) ? $val['commercial_type'] : 'free';
            $webview_url        = !empty($val['webview_url']) ? $val['webview_url'] : '';
            $webview_label      = !empty($val['webview_label']) ? $val['webview_label'] : '';

            // Landscape Photo
            $photo_obj      =   [];
            $photo_file     =   !empty($val['photo']) ? $val['photo'] : '';
            if (!empty($photo_file)) {
                $parmas     =   ['file' => $photo_file, 'type' => 'contents'];
                $photo      =   $this->kraken->uploadToAws($parmas);
                if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                    $photo_obj  = $photo['results'];
                }
            }

            // Portrait Photo
            $photo_portrait_obj = [];
            $photo_portrait_file= !empty($val['photo_portrait']) ? $val['photo_portrait'] : '';
            if($photo_portrait_file) {
                $parmas         = ['file' => $photo_portrait_file, 'type' => 'portraitcontents'];
                $photo_portrait = $this->kraken->uploadToAws($parmas);
                if(!empty($photo_portrait) && !empty($photo_portrait['success']) && $photo_portrait['success'] === true && !empty($photo_portrait['results'])) {
                    $photo_portrait_obj  = $photo_portrait['results'];
                }
            }

            $storeData = [
                'type'              => $requestData['type'],
                'bucket_id'         => $requestData['bucket_id'],
                'artist_id'         => $requestData['artist_id'],
                'is_album'          => $requestData['is_album'],
                'level'             => 1,
                'status'            => $status,
                'platforms'         => isset($requestData['platforms']) ? $requestData['platforms'] : ['android', 'ios', 'web'],
                'name'              => $name,
                'slug'              => $slug,
                'caption'           => $caption,
                'language_id'       => $language_id,
                'coins'             => $coins,
                'commercial_type'   => $commercial_type,
                'photo'             => $photo_obj,
                'photo_portrait'    => $photo_portrait_obj,
            ];

            if (!empty($parent_id)) {
                array_set($storeData, 'parent_id', $parent_id);
            }

            if (!empty($webview_url)) {
                array_set($storeData, 'webview_url', $webview_url);
            }

            if (!empty($webview_label)) {
                array_set($storeData, 'webview_label', $webview_label);
            }

            if (!empty($customer_id) && $customer_id != 'xxxxx' && $customer_id != '') {
                array_set($storeData, 'customer_id', $customer_id);
            }

            $storeData = array_merge($storeData, $default_arr);

            $notification_arr = [
                'send_notification' => $default_arr['send_notification'],
                'bucket_code'       => $default_arr['bucket_code'],
                'artist_id'         => $requestData['artist_id'],
            ];

            $store = $this->repObj->storeContent($storeData);
        }

        $results['parent_id'] = !empty($parent_id) ? $parent_id : null;

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function contentPollStore($requestData, $default_arr)
    {
        $error_messages = $results = [];

        $is_album = $requestData['is_album'];
        $coins = 0;
        $commercial_type = 'free';
        $status = 'active';
        $parent_id = !empty($requestData['parent_id']) ? $requestData['parent_id'] : '';
        $customer_id = $this->jwtauth->customerIdFromToken();

        $album_with_content = [];
        $album_with_out_content = [];
        $is_cover = '';
        foreach ($requestData['medias'] as $key => $val) {

            $name = !empty($val['name']) ? $val['name'] : '';
            $slug = !empty($val['name']) ? str_slug($val['name']) : '';
            $caption = !empty($val['caption']) ? $val['caption'] : '';
            $coins = !empty($val['coins']) ? $val['coins'] : $coins;
            $commercial_type = !empty($val['commercial_type']) ? $val['commercial_type'] : $commercial_type;
            $webview_url = !empty($val['webview_url']) ? $val['webview_url'] : '';
            $webview_label = !empty($val['webview_label']) ? $val['webview_label'] : '';

//==================================================Kraken Object=======================================================
            $photo_obj = [];

            $photo_file = !empty($val['photo']) ? $val['photo'] : '';
            if (!empty($photo_file)) {
                $parmas     =   ['file' => $photo_file, 'type' => 'contents'];
                $photo      =   $this->kraken->uploadToAws($parmas);
                if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                    $photo_obj  = $photo['results'];
                }
            }

            $base_raw_photo_url = Config::get('product.' . env('PRODUCT') . '.s3.base_urls.photo');
            $photo_url = !empty($val['s3_photo']) ? $val['s3_photo'] : '';
            if (!empty($photo_url)) {
                $photo_url = $base_raw_photo_url . $photo_url;
                $parmas = ['url' => $photo_url, 'type' => 'contents', 'add_watermark' => true, 'artist_id' => $requestData['artist_id']];
                $kraked_img = $this->kraken->uploadKrakenImageToGCP($parmas);
                $photo_obj = $kraked_img;
            }

            $photo_obj = !empty($photo_obj) ? $photo_obj : Config::get('kraken.contents_photo');
//==================================================Kraken Object=======================================================

            $storeData = Array(
                'type' => $requestData['type'],
                'bucket_id' => $requestData['bucket_id'],
                'artist_id' => $requestData['artist_id'],
                'is_album' => $requestData['is_album'],
                'level' => 1,
                'status' => $status,
                'platforms' => ['android', 'ios', 'web'], //Add Patform
                'name' => $name,
                'slug' => $slug,
                'caption' => $caption,
                'coins' => $coins,
                'commercial_type' => $commercial_type,
                'photo' => $photo_obj,
                'pollstats' => null,
                'total_votes' => 0
            );

            if (!empty($webview_url)) {
                array_set($storeData, 'webview_url', $webview_url);
            }
            if (!empty($webview_label)) {
                array_set($storeData, 'webview_label', $webview_label);
            }
            if (!empty($customer_id) && $customer_id != 'xxxxx') {
                array_set($storeData, 'customer_id', $customer_id);
            }


            $expired_at = (!empty($val['expired_at'])) ? hyphen_date($val['expired_at']) : '';
            $expired_at = $expired_at . ' 23:59:00';
            $expired = Carbon::now();

            if (!empty($expired_at)) {
                $expired_at = new \MongoDB\BSON\UTCDateTime(strtotime($expired_at) * 1000);
                $expired = $expired_at;
            }

            $storeData = array_set($storeData, 'expired_at', $expired);

            $storeData = array_merge($storeData, $default_arr);

            $notification_arr = Array(
                'send_notification' => $default_arr['send_notification'],
                'bucket_code' => $default_arr['bucket_code'],
                'artist_id' => $requestData['artist_id'],
            );

            if ($is_album == 'false' && empty($parent_id)) { //Level-1
                $store = $this->repObj->storeContent($storeData);

                $content_id = $store->_id;

//==========================================Send Notification==========================================================
                $notification_arr['content_id'] = $content_id;
                $this->sendNotifications($notification_arr);
//==========================================Send Notification==========================================================
            }

            if ($is_album == 'true' || !empty($parent_id)) { //level-2

                if ((array_key_exists('cover', $val) && !is_null($val['cover']) && $val['cover'] != 'false') && !empty($val['cover'])) {
                    $album_with_content = $storeData;
                    $is_cover = "true";
                } else {

                    if ($storeData['commercial_type'] == 'partial_paid') {
                        $storeData['commercial_type'] = 'free';
                        $storeData['coins'] = 0;
                    }

                    array_push($album_with_out_content, $storeData);
                }
            }
        }

        if ($is_album == 'true' && empty($parent_id)) {

            if ($is_cover == "true") {

                $album_with_content['status'] = 'inactive';

                $store = $this->repObj->storeContent($album_with_content);

                $parent_id = $store->_id;

            } else {
                $album_with_out_content[0]['status'] = 'inactive';

                $store = $this->repObj->storeContent($album_with_out_content[0]);

                $parent_id = $store->_id;

                unset($album_with_out_content[0]);
//                $album_with_out_content[0]['status'] = $status;
            }
        }


        if (!empty($parent_id)) {

            $album_with_out_content = array_values($album_with_out_content);

//            $length_of_arr = count($album_with_out_content);

            foreach ($album_with_out_content as $contentKey => $contentVal) {

                $contentVal['parent_id'] = $parent_id;
                $contentVal['level'] = 2;
                $contentVal['is_album'] = 'false';

                $store = $this->repObj->storeContent($contentVal);

                $content_id = $store->_id;

//==========================================Send Notification==========================================================
                $notification_arr['content_id'] = $content_id;
                $this->sendNotifications($notification_arr);
//==========================================Send Notification==========================================================

            }

            $content = \App\Models\Content::where('_id', $parent_id)->first();
            $updateddata['status'] = 'active';
            $content->update($updateddata);

        }

        $results['parent_id'] = !empty($content) ? $content->_id : null;

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function contentVideoStore($requestData, $default_arr)
    {
        $error_messages = [];
        $results        = [];
        $is_album       = $requestData['is_album'];
        $status         = $default_arr['status'];
        $parent_id      = !empty($requestData['parent_id']) ? $requestData['parent_id'] : '';
        $level          = !empty($requestData['level']) ? intval($requestData['level']) : 1;
        $customer_id    = $this->jwtauth->customerIdFromToken();
        $base_raw_video_url = Config::get('product.' . env('PRODUCT') . '.s3.base_urls.raw_video');

        $add_watermark = true;
        $album_with_content = [];
        $album_with_out_content = [];
        $is_cover = '';
        $language_id = '';
        $kraken_param = [];
        $subtitles  = [];
        $content_folder_name = '';
        if(isset($default_arr['unique_id'])) {
            $content_folder_name = $default_arr['unique_id'];
        }
        else {
            $content_folder_name        = $this->generateUniqueId();
            $default_arr['unique_id']   = $content_folder_name;
        }

        foreach ($requestData['medias'] as $key => $val) {
            $video_file_name = '';
            $payload_vod_job_data = [];
            $name                   = !empty($val['name']) ? $val['name'] : '';
            $slug                   = !empty($val['name']) ? str_slug($val['name']) : '';
            $caption                = !empty($val['caption']) ? $val['caption'] : '';
            $coins                  = !empty($val['coins']) ? $val['coins'] : 0;
            $commercial_type        = !empty($val['commercial_type']) ? $val['commercial_type'] : 'free';
            $player_type            = (!empty($val['player_type'])) ? $val['player_type'] : '';
            $embed_code             = (!empty($val['embed_code'])) ? $val['embed_code'] : '';
            $url                    = (!empty($val['url'])) ? $val['url'] : '';
            $duration               = !empty($val['duration']) ? $val['duration'] : '';
            $partial_play_duration  = !empty($val['partial_play_duration']) ? $val['partial_play_duration'] : '';
            $video_lang             = !empty($val['video_lang']) ? $val['video_lang'] : 'eng';
            $language_id            = isset($val['language_id']) ? $val['language_id'] : '';

            if(!$language_id) {
                $language_eng = $this->languageService->getActiveBy('eng');
                if($language_eng) {
                    $language_id = $language_eng['_id'];
                }
            }

            $vod_job_data   = [];
            $video_url      = '';
            $video_file     = !empty($val['video']) ? $val['video'] : '';
            if (!empty($video_file)) {
                $video_file->video_lang_default = isset($val['video_lang_default']) ? $val['video_lang_default'] : false;
                $video_file->video_lang         = isset($val['video_lang']) ? $val['video_lang'] : 'eng';
                $video_file->video_lang_label   = isset($val['video_lang_label']) ? $val['video_lang_label'] : 'ENGLISH';

                $vod_job_data = $this->uploadContentVideoFile($video_file);

                $storeData['video'] = isset($storeData['video']) ? $storeData['video'] : [];
                $storeData['video'] = $this->repObj->findAndUpdateVideoObject($vod_job_data, $storeData['video']);
                if(isset($storeData['video']) && isset($storeData['video']['url'])) {
                    $url = $storeData['video']['url'];
                }
            }

            $video_name = !empty($val['s3_video']) ? $val['s3_video'] : '';

            if (!empty($video_name)) {
                $video_url = $base_raw_video_url . $video_name;

                $vod_job_data = [
                    'status' => 'submitted',
                    'object_name' => $video_name,
                    'object_path' => $video_name,
                    'object_extension' => pathinfo($video_name, PATHINFO_EXTENSION),
                    'bucket' => 'armsrawvideos'
                ];
            }

            // Landscape Photo
            $photo_obj = [];

            $photo_file = !empty($val['photo']) ? $val['photo'] : '';
            if (!empty($photo_file)) {
                $parmas     =   ['file' => $photo_file, 'type' => 'contents'];
                $photo      =   $this->kraken->uploadToAws($parmas);
                if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                    $photo_obj  = $photo['results'];
                }
            }

            $photo_url = !empty($val['s3_photo']) ? $val['s3_photo'] : '';
            if (!empty($photo_url)) {
                $base_raw_photo_url = Config::get('product.' . env('PRODUCT') . '.s3.base_urls.photo');
                $photo_url          = $base_raw_photo_url . $photo_url;
                $kraken_param = [
                    'url' => $photo_url, 'type' => 'contents', 'add_watermark' => $add_watermark, 'artist_id' => $requestData['artist_id']
                ];

                $photo_url = !empty($photo_url) ? $photo_url : '';
                $photo_obj = [
                    'thumb' => $photo_url, 'thumb_width' => null, 'thumb_height' => null,
                    'cover' => $photo_url, 'cover_width' => null, 'cover_height' => null,
                    'medium' => $photo_url, 'medium_width' => null, 'medium_height' => null
                ];
            }

            $photo_obj = !empty($photo_obj) ? $photo_obj : Config::get('kraken.contents_photo');


            // Portrait Photo
            $photo_portrait_obj = [];
            $photo_portrait_file= !empty($val['photo_portrait']) ? $val['photo_portrait'] : '';
            if($photo_portrait_file) {
                $parmas         = ['file' => $photo_portrait_file, 'type' => 'portraitcontents'];
                $photo_portrait = $this->kraken->uploadToAws($parmas);
                if(!empty($photo_portrait) && !empty($photo_portrait['success']) && $photo_portrait['success'] === true && !empty($photo_portrait['results'])) {
                    $photo_portrait_obj  = $photo_portrait['results'];
                }
            }

            // Add Subtitles
            if(isset($requestData['subtitles']) && $requestData['subtitles']) {
                foreach ($requestData['subtitles'] as $key => $subtitle) {
                    if(isset($subtitle['file'])) {
                        $subtitle_data = $this->storeContentSubtitleFile($subtitle, $content_folder_name);
                        if($subtitle_data) {
                            $subtitles[] = $subtitle_data;
                        }
                    }
                }
            } // END SUBTILES

            $storeData = Array(
                'type'              => $requestData['type'],
                'bucket_id'         => $requestData['bucket_id'],
                'artist_id'         => $requestData['artist_id'],
                'is_album'          => $requestData['is_album'],
                'level'             => $level,
                'status'            => $status,
                'platforms'         => ['android', 'ios', 'web'], //Add Patform
                'name'              => $name,
                'slug'              => $slug,
                'caption'           => $caption,
                'coins'             => $coins,
                'commercial_type'   => $commercial_type,
                'vod_job_data'      => [$vod_job_data],
                'video_status'      => 'uploaded',
                'subtitles'         => $subtitles,
                'language_id'       => $language_id,
            );

            if (!empty($parent_id)) {
                array_set($storeData, 'parent_id', $parent_id);
            }

            if (!empty($photo_portrait_obj)) {
                array_set($storeData, 'photo_portrait', $photo_portrait_obj);
            }

            if (!empty($duration)) {
                array_set($storeData, 'duration', $duration);
                // Convert Duration into milliseconds and save in duration_ms attribute
                $duration_ms = self::convertDurationInMilliseconds($duration);
                array_set($storeData, 'duration_ms', $duration_ms);
            }

            if (!empty($partial_play_duration)) {
                array_set($storeData, 'partial_play_duration', $partial_play_duration);
            }

            if ($player_type == 'internal') {
                array_set($storeData, 'status', 'inactive');
            }

            if (!empty($player_type)) {
                array_set($storeData['video'], 'player_type', $player_type);
            }

            if (!empty($embed_code)) {
                array_set($storeData['video'], 'embed_code', $embed_code);
            }

            if (!empty($url)) {
                array_set($storeData['video'], 'url', $url);
            }

            if (!empty($webview_url)) {
                array_set($storeData, 'webview_url', $webview_url);
            }

            if (!empty($webview_label)) {
                array_set($storeData, 'webview_label', $webview_label);
            }

            if (!empty($customer_id) && $customer_id != 'xxxxx') {
                array_set($storeData, 'customer_id', $customer_id);
            }

            $storeData['video'] = array_merge($storeData['video'], $photo_obj);

            $storeData = array_merge($storeData, $default_arr);

            $notification_arr = Array(
                'send_notification' => $default_arr['send_notification'],
                'bucket_code' => $default_arr['bucket_code'],
                'artist_id' => $requestData['artist_id'],
            );

            if($storeData) {
                $store = $this->repObj->storeContent($storeData);
                $content_id = $store->_id;
                if($store->vod_job_data) {
                   $payload_vod_job_data = $store->vod_job_data[0];
                }

                //Push to Queue Job For transcode media convert
                if ($store && isset($store['video_status']) && $store['video_status'] == 'uploaded') {

                    $payload = [
                        'content_id'        => $content_id,
                        'send_notification' => $default_arr['send_notification'],
                        'object_name'       => $video_name,
                    ];

                    $payload = array_merge($payload, $payload_vod_job_data);

                    $jobData = [
                        'label'     => 'CreateHLSTranscodeJobForVideo',
                        'type'      => 'transcode_video',
                        'payload'   => $payload,
                        'status'    => "scheduled",
                        'delay'     => 0,
                        'retries'   => 0,
                    ];

                    $recodset = new \App\Models\Job($jobData);
                    $recodset->save();
                }
            }
        }

        $results['parent_id'] = !empty($content) ? $content->_id : null;

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function contentAudioStore($requestData, $default_arr)
    {
        $error_messages = $results = [];
        $base_raw_audio_url = Config::get('product.' . env('PRODUCT') . '.s3.base_urls.audio');
        $is_album = $requestData['is_album'];
        $coins = 0;
        $commercial_type = 'free';
        $status = 'active';
        $parent_id = !empty($requestData['parent_id']) ? $requestData['parent_id'] : '';
        $customer_id = $this->jwtauth->customerIdFromToken();

        $album_with_content = [];
        $album_with_out_content = [];
        $is_cover = '';

        foreach ($requestData['medias'] as $key => $val) {

            $name = !empty($val['name']) ? $val['name'] : '';
            $slug = !empty($val['name']) ? str_slug($val['name']) : '';
            $caption = !empty($val['caption']) ? $val['caption'] : '';
            $coins = !empty($val['coins']) ? $val['coins'] : $coins;
            $commercial_type = !empty($val['commercial_type']) ? $val['commercial_type'] : $commercial_type;
            $duration = !empty($val['duration']) ? $val['duration'] : '';
            $partial_play_duration = !empty($val['partial_play_duration']) ? $val['partial_play_duration'] : '';

            $aod_job_data = [];
            $audio_url = '';
            $audio_file = !empty($val['audio']) ? $val['audio'] : '';

            if (!empty($audio_file)) {
                //upload to local drive
                $upload = $val['audio'];
                $folder_path = 'uploads/contents/audio/';
                $obj_path = public_path($folder_path);
                $obj_extension = $upload->getClientOriginalExtension();
                $imageName = time() . '_' . str_slug($upload->getRealPath()) . '.' . $obj_extension;
                $fullpath = $obj_path . $imageName;
                $upload->move($obj_path, $imageName);
                chmod($fullpath, 0777);

                //upload to aws
                $object_source_path = $fullpath;
                $object_upload_path = $imageName;
                $s3 = Storage::createS3Driver(Config::get('product.' . env('PRODUCT') . '.s3.rawaudios'));
                if (env('APP_ENV', 'stg') != 'local') {
                    $response = $s3->put($object_upload_path, file_get_contents($object_source_path), 'public');
                }
                $aod_job_data = [
                    'status' => 'submitted',
                    'object_name' => $imageName,
                    'object_path' => $object_upload_path,
                    'object_extension' => $obj_extension,
                    'bucket' => 'armsrawaudios'
                ];

                $audio_url = $base_raw_audio_url . $aod_job_data['object_name'];
                @unlink($fullpath);
            }

            $audio_name = !empty($val['s3_audio']) ? $val['s3_audio'] : '';

            if (!empty($audio_name)) {
                $audio_url = $base_raw_audio_url . $audio_name;

                $aod_job_data = [
                    'status' => 'submitted',
                    'object_name' => $audio_name,
                    'object_path' => $audio_name,
                    'object_extension' => pathinfo($audio_name, PATHINFO_EXTENSION),
                    'bucket' => 'armsrawaudios'
                ];
            }

//==================================================Kraken Object=======================================================
            $photo_obj = [];

            $photo_file = !empty($val['photo']) ? $val['photo'] : '';
            if (!empty($photo_file)) {
                $parmas     =   ['file' => $photo_file, 'type' => 'contents'];
                $photo      =   $this->kraken->uploadToAws($parmas);
                if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                    $photo_obj  = $photo['results'];
                }
            }

            $base_raw_photo_url = Config::get('product.' . env('PRODUCT') . '.s3.base_urls.photo');
            $photo_url = !empty($val['s3_photo']) ? $val['s3_photo'] : '';
            if (!empty($photo_url)) {
                $photo_url = $base_raw_photo_url . $photo_url;
                $parmas = ['url' => $photo_url, 'type' => 'contents'];
                $kraked_img = $this->kraken->uploadKrakenImageToGCP($parmas);
                $photo_obj = $kraked_img;
            }

            $photo_obj = !empty($photo_obj) ? $photo_obj : Config::get('kraken.contents_photo');
//==================================================Kraken Object=======================================================

            $storeData = Array(
                'type' => $requestData['type'],
                'bucket_id' => $requestData['bucket_id'],
                'artist_id' => $requestData['artist_id'],
                'is_album' => $requestData['is_album'],
                'level' => 1,
                'status' => $status,
                'platforms' => ['android', 'ios', 'web'], //Add Patform
                'name' => $name,
                'slug' => $slug,
                'caption' => $caption,
                'coins' => $coins,
                'commercial_type' => $commercial_type,
                'aod_job_data' => $aod_job_data,
                'audio_status' => 'uploaded',
            );


            if (!empty($duration)) {
                array_set($storeData, 'duration', $duration);
                // Convert Duration into milliseconds and save in duration_ms attribute
                $duration_ms = self::convertDurationInMilliseconds($duration);
                array_set($storeData, 'duration_ms', $duration_ms);
            }

            if (!empty($partial_play_duration)) {
                array_set($storeData, 'partial_play_duration', $partial_play_duration);
            }

            if (!empty($webview_url)) {
                array_set($storeData, 'webview_url', $webview_url);
            }

            if (!empty($webview_label)) {
                array_set($storeData, 'webview_label', $webview_label);
            }

            if (!empty($customer_id) && $customer_id != 'xxxxx') {
                array_set($storeData, 'customer_id', $customer_id);
            }

            $url = (!empty($aod_job_data) && !empty($aod_job_data['object_name'])) ? $audio_url : '';
            array_set($storeData['audio'], 'url', $url);

            $storeData['audio'] = array_merge($storeData['audio'], $photo_obj);

            $storeData = array_merge($storeData, $default_arr);

            $notification_arr = Array(
                'send_notification' => $default_arr['send_notification'],
                'bucket_code' => $default_arr['bucket_code'],
                'artist_id' => $requestData['artist_id'],
            );

            if ($is_album == 'false' && empty($parent_id)) { //Level-1
                $store = $this->repObj->storeContent($storeData);

                $content_id = $store->_id;

//==========================================Transcode Audio=============================================================
                //Push to Queue Job For transcode audio
                if ($store && isset($store['audio_status']) && $store['audio_status'] == 'uploaded') {
                    $payload = ['content_id' => $content_id, 'send_notification' => $default_arr['send_notification']];
                    $payload = array_merge($payload, $store['aod_job_data']);
                    $jobData = [
                        'label' => 'CreateHLSTranscodeJobForAudio',
                        'type' => 'transcode_audio',
                        'payload' => $payload,
                        'status' => "scheduled",
                        'delay' => 0,
                        'retries' => 0,
                    ];
                    $recodset = new \App\Models\Job($jobData);
                    $recodset->save();
                    $send_notification = 'false'; //incase of audio
                }
//==========================================Transcode Audio=============================================================

//==========================================Send Notification==========================================================
                $notification_arr['content_id'] = $content_id;
                $this->sendNotifications($notification_arr);
//==========================================Send Notification==========================================================
            }

            if ($is_album == 'true' || !empty($parent_id)) { //level-2

                if ((array_key_exists('cover', $val) && !is_null($val['cover']) && $val['cover'] != 'false') && !empty($val['cover'])) {
                    $album_with_content = $storeData;
                    $is_cover = "true";
                } else {

                    if ($storeData['commercial_type'] == 'partial_paid') {
                        $storeData['commercial_type'] = 'free';
                        $storeData['coins'] = 0;
                    }

                    array_push($album_with_out_content, $storeData);
                }
            }
        }

        if ($is_album == 'true' && empty($parent_id)) {

            if ($is_cover == "true") {
                $album_with_content['status'] = 'inactive';

                $store = $this->repObj->storeContent($album_with_content);

                $parent_id = $store->_id;

            } else {
                $album_with_out_content[0]['status'] = 'inactive';

                $store = $this->repObj->storeContent($album_with_out_content[0]);

                $parent_id = $store->_id;

                unset($album_with_out_content[0]);
//                $album_with_out_content[0]['status'] = $status;
            }
        }

        if (!empty($parent_id)) {

            $album_with_out_content = array_values($album_with_out_content);

//            $length_of_arr = count($album_with_out_content);

            foreach ($album_with_out_content as $contentKey => $contentVal) {

                $contentVal['parent_id'] = $parent_id;
                $contentVal['level'] = 2;
                $contentVal['is_album'] = 'false';

                $store = $this->repObj->storeContent($contentVal);

                $content_id = $store->_id;

//==========================================Transcode Audio=============================================================
                //Push to Queue Job For transcode audio
                if ($store && isset($store['audio_status']) && $store['audio_status'] == 'uploaded') {
                    $payload = ['content_id' => $content_id, 'send_notification' => $default_arr['send_notification']];
                    $payload = array_merge($payload, $store['aod_job_data']);
                    $jobData = [
                        'label' => 'CreateHLSTranscodeJobForAudio',
                        'type' => 'transcode_audio',
                        'payload' => $payload,
                        'status' => "scheduled",
                        'delay' => 0,
                        'retries' => 0,
                    ];
                    $recodset = new \App\Models\Job($jobData);
                    $recodset->save();
                    $send_notification = 'false'; //incase of audio
                }
//==========================================Transcode Audio=============================================================

//==========================================Send Notification==========================================================
                $notification_arr['content_id'] = $content_id;
                $this->sendNotifications($notification_arr);
//==========================================Send Notification==========================================================

            }

            $content = \App\Models\Content::where('_id', $parent_id)->first();
            $updateddata['status'] = 'active';
            $content->update($updateddata);

        }
        $results['parent_id'] = !empty($content) ? $content->_id : null;

        return ['error_messages' => $error_messages, 'results' => $results];
    }



    /**
     * Update Content In Database when request is from CMS
     *
     *
     * @param   request
     *
     * @return  Boolean
     *
     * @author
     * @since
     */
    public function contentUpdate($request)
    {
//	    echo "<pre>";
//	    print_r($request->all());
//	    echo "<pre>";
//	    exit;
        $data = array_except($request->all(), ['_method', '_token']);
        $error_messages = $results = [];

        $type                   = !empty($data['type']) ? $data['type'] : 'photo';
        $player_type            = !empty($data['player_type']) ? $data['player_type'] : '';
        $url                    = !empty($data['url']) ? $data['url'] : '';
        $embed_code             = !empty($data['embed_code']) ? $data['embed_code'] : '';
        $duration               = !empty($data['duration']) ? $data['duration'] : '';
        $partial_play_duration  = !empty($data['partial_play_duration']) ? $data['partial_play_duration'] : '';
	 $is_producer                  = !empty($data['is_producer']) ? $data['is_producer'] : 'false';
        $producer_id                  = !empty($data['producer_id']) ? $data['producer_id'] : '';
        $content_id = $data['content_id'];
        $contentObj = \App\Models\Content::where('_id', '=', $content_id)->first();

        if (!$contentObj) {
            $error_messages[] = 'Content does not exist';
        }

        $bucket_id = $contentObj['bucket_id'];
        $artist_id = $contentObj['artist_id'];

        $slug = (isset($data['name'])) ? str_slug($data['name']) : '';
        array_set($data, 'slug', $slug);

        // Meta Data
        $meta_data_default = array(
            'title' => '',
            'description' => '',
            'keywords' => '',
        );
        $meta_data = isset($data['meta']) ? $data['meta'] : $meta_data_default;

        $age_restriction = isset($data['age_restriction']) ? $data['age_restriction'] : 18;

        $default_arr = Array(
            'ordering'              => 0,
            'pin_to_top'            => !empty($data['pin_to_top']) ? $data['pin_to_top'] : false,
            'source'                => !empty($data['source']) ? $data['source'] : 'custom',
            'is_test_enable'        => !empty($data['is_test_enable']) ? $data['is_test_enable'] : "false",
            '18_plus_age_content'   => !empty($data['18_plus_age_content']) ? $data['18_plus_age_content'] : "true",
            'is_commentbox_enable'  => !empty($data['is_commentbox_enable']) ? $data['is_commentbox_enable'] : "true",
            'is_album'              => !empty($data['is_album']) ? $data['is_album'] : "false",
            'commercial_type'       => !empty($data['commercial_type']) ? trim($data['commercial_type']) : 'free',
            'coins'                 => !empty($data['coins']) ? intval($data['coins']) : 0,
            'platforms'             => !empty($data['platforms']) ? $data['platforms'] : [],
            'slug'                  => !empty($data['name']) ? str_slug($data['name']) : '',
            'name'                  => !empty($data['name']) ? $data['name'] : '',
            'caption'               => !empty($data['caption']) ? $data['caption'] : '',
            'language_id'           => !empty($data['language_id']) ? $data['language_id'] : '',
            'level'                 => !empty($data['level']) ? $data['level'] : '',
            'status'                => !empty($data['status']) ? $data['status'] : 'active',
            'webview_url'           => !empty($data['webview_url']) ? $data['webview_url'] : '',
            'webview_label'         => !empty($data['webview_label']) ? $data['webview_label'] : '',
            'meta'                  => $meta_data,
	    'age_restriction'       => $age_restriction,
	      'producer_id'           =>$producer_id,
            'is_producer'           =>$is_producer
        );

        $content_folder_name = '';
        if(isset($contentObj['unique_id'])) {
            $content_folder_name = $contentObj['unique_id'];
        }
        else {
            $content_folder_name        = $this->generateUniqueId();
            $default_arr['unique_id']   = $content_folder_name;
        }

        // Genres
        if (!isset($data['genres'])) {
            $default_arr['genres'] = [];
        }
        else {
            array_set($default_arr, 'genres', $data['genres']);
        }

        $published_at = !empty($data['published_at']) ? hyphen_date($data['published_at']) : '';

        if (!empty($published_at)) {
            $published_at = new \MongoDB\BSON\UTCDateTime(strtotime($published_at) * 1000);
        } else {
            $published_at = Carbon::now();
        }
        if (!empty($published_at)) {
            array_set($default_arr, 'published_at', $published_at);
        }

        if ($type == 'photo') {

            // Landscape Photo
            $photo_obj = [];

            $photo_file = !empty($data['photo']) ? $data['photo'] : '';
             if (!empty($photo_file)) {
                $parmas     =   ['file' => $photo_file, 'type' => 'contents'];
                $photo      =   $this->kraken->uploadToAws($parmas);
                if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                    $photo_obj  = $photo['results'];
                    array_set($default_arr, 'photo', $photo_obj);
                }
            }

            // Portrait Photo
            $photo_portrait_obj = [];

            $photo_portrait_file= !empty($data['photo_portrait']) ? $data['photo_portrait'] : '';
            if($photo_portrait_file) {
                $parmas         = ['file' => $photo_portrait_file, 'type' => 'portraitcontents'];
                $photo_portrait = $this->kraken->uploadToAws($parmas);
                if(!empty($photo_portrait) && !empty($photo_portrait['success']) && $photo_portrait['success'] === true && !empty($photo_portrait['results'])) {
                    $photo_portrait_obj  = $photo_portrait['results'];
                    array_set($default_arr, 'photo_portrait', $photo_portrait_obj);
                }
            }
        }
        elseif ($type == 'video') {

             // Set Video Parameters
            $default_arr = $this->updateVideoData($data, $default_arr);

            $base_raw_video_url = Config::get('product.' . env('PRODUCT') . '.s3.base_urls.raw_video');

            // Portrait Photo
            $photo_obj = [];

            $photo_file = !empty($data['photo']) ? $data['photo'] : '';
             if (!empty($photo_file)) {

                 $photo_file = !empty($data['photo']) ? $data['photo'] : '';
                $parmas     =   ['file' => $photo_file, 'type' => 'contents'];
                $photo      =   $this->kraken->uploadToAws($parmas);
                 if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                    $photo_obj  = $photo['results'];
                }

                $default_arr['video'] = [];
                $default_arr['video'] = array_merge($default_arr['video'], $contentObj['video']);
                $default_arr['video'] = array_merge($default_arr['video'], $photo_obj);
            }

            // Portrait Photo
            $photo_portrait_obj = [];

            $photo_portrait_file= !empty($data['photo_portrait']) ? $data['photo_portrait'] : '';
            if($photo_portrait_file) {
                $parmas         = ['file' => $photo_portrait_file, 'type' => 'portraitcontents'];
		$photo_portrait = $this->kraken->uploadToAws($parmas);

	//	echo "<pre>";
	//	print_r($photo_portrait);
	//	echo "<pre>";
	//	exit;
                if(!empty($photo_portrait) && !empty($photo_portrait['success']) && $photo_portrait['success'] === true && !empty($photo_portrait['results'])) {
                    $photo_portrait_obj  = $photo_portrait['results'];
                    array_set($default_arr, 'photo_portrait', $photo_portrait_obj);
                }
            }

            $vod_job_data = [];
            $video_url = '';

            $video_file = !empty($data['video']) ? $data['video'] : '';
            if (!empty($video_file)) {
                //upload to local drive
                $upload = $data['video'];
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
                $s3 = Storage::createS3Driver(Config::get('product.' . env('PRODUCT') . '.s3.rawvideos'));
                if (env('APP_ENV', 'stg') != 'local') {
                    $response = $s3->put($object_upload_path, file_get_contents($object_source_path), 'public');
                }
                $vod_job_data = [
                    'status' => 'submitted',
                    'object_name' => $imageName,
                    'object_path' => $object_upload_path,
                    'object_extension' => $obj_extension,
                    'bucket' => 'armsrawvideos'
                ];

                $video_url = $base_raw_video_url . $vod_job_data['object_name'];
                @unlink($fullpath);
            }

            $video_name = !empty($data['s3_video']) ? $data['s3_video'] : '';
            if (!empty($video_name)) {
                $video_url = $base_raw_video_url . $video_name;

                $vod_job_data = [
                    'status' => 'submitted',
                    'object_name' => $video_name,
                    'object_path' => $video_name,
                    'object_extension' => pathinfo($video_name, PATHINFO_EXTENSION),
                    'bucket' => 'armsrawvideos'
                ];
            }

            if (!empty($player_type)) {

                if ($player_type == 'internal') {
                    if (!empty($video_url)) {
                        array_set($default_arr, 'status', 'inactive');
                        $url = $video_url;
                        array_set($default_arr, 'video_status', 'uploaded');
                        array_set($default_arr, 'vod_job_data', $vod_job_data);
                        array_set($default_arr['video'], 'url', $url);
                        array_set($default_arr['video'], 'player_type', $player_type);
                    }
                } else {
                    if (!empty($embed_code) && !empty($url)) {
                        array_set($default_arr['video'], 'embed_code', $embed_code);
                        array_set($default_arr['video'], 'url', $url);
                        array_set($default_arr['video'], 'player_type', $player_type);
                    }
                }
            }


            if (!empty($duration)) {
                array_set($default_arr, 'duration', $duration);

                // Convert Duration into milliseconds and save in duration_ms attribute
                $duration_ms = self::convertDurationInMilliseconds($duration);
                array_set($default_arr, 'duration_ms', $duration_ms);
            }

            if (!empty($partial_play_duration)) {
                array_set($default_arr, 'partial_play_duration', $partial_play_duration);
            }

            // Video Files => Video Files for multiple languages
            if(isset($data['video_file'])) {
                $video_file_langs = [];
                foreach ($data['video_file'] as $key => $video_file) {
                    $video_file_langs[] = $video_file['language'];
                }

                $content_vod_job_data_new   = [];
                $content_vod_job_data   = [];
                $content_vod_job_data   = isset($contentObj['vod_job_data']) ? $contentObj['vod_job_data'] : [];
                if($content_vod_job_data) {
                    foreach ($content_vod_job_data as $key => $vod_job) {
                        if(isset($vod_job['language']) &&  in_array($vod_job['language'], $video_file_langs)) {
                            $content_vod_job_data_new[] = $vod_job;
                        }
                    }
                }

                $default_arr['vod_job_data'] = $content_vod_job_data_new;
            }

            // Subtitles
            if(isset($data['subtitles'])) {
                $content_subtitles_new  = [];
                $content_subtitle_langs = [];
                $content_subtitles = isset($contentObj['subtitles']) ? $contentObj['subtitles'] : [];
                if($content_subtitles) {
                    foreach ($content_subtitles as $key => $content_subtitle) {
                        $content_subtitle_langs[]   = $content_subtitle['language'];
                        $content_subtitles_new[]    = $content_subtitle;
                    }
                }

                $subtitles  = $data['subtitles'];

                foreach ($subtitles as $key => $subtitle) {
                    if(in_array($subtitle['language'], $content_subtitle_langs)) {
                        //$content_subtitles_new[] = $subtitle;
                        if(isset($subtitle['file']) && $subtitle['file']) {
                            // Update subtitle
                            $update_subtitle = $this->storeContentSubtitleFile($subtitle, $content_folder_name);
                            if($update_subtitle) {
                                // Remove Previous entry
                                foreach ($content_subtitles_new as $key => $value) {
                                    if($value['language'] == $subtitle['language']) {
                                        $content_subtitles_new[$key] = $update_subtitle;
                                        break;
                                    }
                                }

                            }
                        }
                    }
                    else {
                        // Save new subtitle
                        $new_subtitle = $this->storeContentSubtitleFile($subtitle, $content_folder_name);
                        if($new_subtitle) {
                            $content_subtitles_new[] = $new_subtitle;
                        }
                    }
                }

                $default_arr['subtitles'] = $content_subtitles_new;
            }
            else {
                $default_arr['subtitles'] = [];
            }
        }
        elseif ($type == 'audio') {
            $base_raw_audio_url = Config::get('product.' . env('PRODUCT') . '.s3.base_urls.audio');
            $aod_job_data = [];
            $audio_url = '';

            $audio_file = !empty($data['audio']) ? $data['audio'] : '';
            if (!empty($audio_file)) {
                //upload to local drive
                $upload = $data['audio'];
                $folder_path = 'uploads/contents/audio/';
                $obj_path = public_path($folder_path);
                $obj_extension = $upload->getClientOriginalExtension();
                $imageName = time() . '_' . str_slug($upload->getRealPath()) . '.' . $obj_extension;
                $fullpath = $obj_path . $imageName;
                $upload->move($obj_path, $imageName);
                chmod($fullpath, 0777);

                //upload to aws
                $object_source_path = $fullpath;
                $object_upload_path = $imageName;
                $s3 = Storage::createS3Driver(Config::get('product.' . env('PRODUCT') . '.s3.rawaudios'));
                if (env('APP_ENV', 'stg') != 'local') {
                    $response = $s3->put($object_upload_path, file_get_contents($object_source_path), 'public');
                }
                $aod_job_data = [
                    'status' => 'submitted',
                    'object_name' => $imageName,
                    'object_path' => $object_upload_path,
                    'object_extension' => $obj_extension,
                    'bucket' => 'armsrawaudios'
                ];

                $audio_url = $base_raw_audio_url . $aod_job_data['object_name'];
                @unlink($fullpath);
            }

            $audio_name = !empty($data['s3_audio']) ? $data['s3_audio'] : '';

            if (!empty($audio_name)) {
                $audio_url = $base_raw_audio_url . $audio_name;

                $aod_job_data = [
                    'status' => 'submitted',
                    'object_name' => $audio_name,
                    'object_path' => $audio_name,
                    'object_extension' => pathinfo($audio_name, PATHINFO_EXTENSION),
                    'bucket' => 'armsrawaudios'
                ];
            }

            $photo_obj = [];

            $photo_file = !empty($data['photo']) ? $data['photo'] : '';
            if (!empty($photo_file)) {

                $parmas     =   ['file' => $photo_file, 'type' => 'contents'];
                $photo      =   $this->kraken->uploadToAws($parmas);
                if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                    $photo_obj  = $photo['results'];
                }

                $default_arr['audio'] = [];
                $default_arr['audio'] = array_merge($default_arr['audio'], $contentObj['audio']);
                $default_arr['audio'] = array_merge($default_arr['audio'], $photo_obj);
            }

            if (!empty($duration)) {
                array_set($default_arr, 'duration', $duration);
                // Convert Duration into milliseconds and save in duration_ms attribute
                $duration_ms = self::convertDurationInMilliseconds($duration);
                array_set($default_arr, 'duration_ms', $duration_ms);
            }

            if (!empty($partial_play_duration)) {
                array_set($default_arr, 'partial_play_duration', $partial_play_duration);
            }

            if (!empty($audio_url)) {
                array_set($default_arr['audio'], 'url', $audio_url);
                array_set($default_arr, 'aod_job_data', $aod_job_data);
                array_set($default_arr, 'audio_status', 'uploaded');
            }


        }
        elseif ($type == 'poll') {

            $photo_obj = [];

            $photo_file = !empty($data['photo']) ? $data['photo'] : '';
            if (!empty($photo_file)) {
                $parmas     =   ['file' => $photo_file, 'type' => 'contents'];
                $photo      =   $this->kraken->uploadToAws($parmas);
                if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                    $photo_obj  = $photo['results'];
                    array_set($default_arr, 'photo', $photo_obj);
                }
            }

            if (!empty($data['expired_at'])) {
                $expired_at = hyphen_date($data['expired_at']) . ' 23:59:00';
                $expired_at = new \MongoDB\BSON\UTCDateTime(strtotime($expired_at) * 1000);
                array_set($default_arr, 'expired_at', $expired_at);
            }
        }


        if (empty($error_messages)) {

            $content = $this->repObj->contentUpdate($default_arr, $content_id);

            $contentObj = \App\Models\Content::where('_id', $content_id)->first();

            $languages  = $this->artistservice->getArtistCode2LanguageArray($artist_id);


            //--------------------------------------------Purge Cache Key--------------------------------------------------------

            $parent_id = (!empty($contentObj['parent_id']) && $contentObj['parent_id'] != '') ? trim($contentObj['parent_id']) : "";
            $purge_result = $this->awsElasticCacheRedis->purgeContentListCache(['content_id' => $content_id, 'bucket_id' => $bucket_id, 'parent_id' => $parent_id, 'languages' => $languages]);
            $purge_result = $this->awsElasticCacheRedis->purgeContentDetailCache(['content_id' => $content_id, 'bucket_id' => $bucket_id, 'parent_id' => $parent_id]);

            //--------------------------------------------Purge Cache Key--------------------------------------------------------


            $results['content'] = $content;
            $content_id = $content_id;

            //==========================================Transcode Video==========================================================
            //Push to Queue Job For transcode media convert
            if (!empty($contentObj) && isset($contentObj['video_status']) && $contentObj['video_status'] == 'uploaded') {

                $payload = ['content_id' => $content_id];
                $payload = array_merge($payload, $contentObj['vod_job_data']);

                $jobData = [
                    'label' => 'CreateHLSTranscodeJobForVideo',
                    'type' => 'transcode_video',
                    'payload' => $payload,
                    'status' => "scheduled",
                    'delay' => 0,
                    'retries' => 0,
                ];

                $recodset = new \App\Models\Job($jobData);
                $recodset->save();
            }
//==========================================Transcode Video==========================================================


//==========================================Transcode Audio=============================================================
            //Push to Queue Job For transcode audio
            if ($contentObj && isset($contentObj['audio_status']) && $contentObj['audio_status'] == 'uploaded') {
                $payload = ['content_id' => $content_id];
                $payload = array_merge($payload, $contentObj['aod_job_data']);
                $jobData = [
                    'label' => 'CreateHLSTranscodeJobForAudio',
                    'type' => 'transcode_audio',
                    'payload' => $payload,
                    'status' => "scheduled",
                    'delay' => 0,
                    'retries' => 0,
                ];
                $recodset = new \App\Models\Job($jobData);
                $recodset->save();
            }
//==========================================Transcode Audio=============================================================

            if (env('APP_ENV', 'stg') == 'production') {
                try {
                    $invalidate_result = $this->awscloudfrontService->invalidateContents();
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
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }




    public function contentEarnings($request)
    {

        $error_messages = [];
        $results = [];
        $requestData = $request->all();
        $requestData['entity_id'] = (isset($requestData['content_id']) && $requestData['content_id'] != '') ? $requestData['content_id'] : '';
        $results = $this->purchaseRepObj->contentEarnings($requestData);
        return ['error_messages' => $error_messages, 'results' => $results];

    }

    /**
     * Return Bucket Id for given bucket slug
     *
     *
     * @param   string
     * @return  string
     *
     * @author Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since 2019-04-11
     */
    public function getContentBucketIdBySlug($bucket_slug, $artist_id, $language_id)
    {
        $error_messages = [];
        $results        = [];
        $cacheParams    = [];
        $bucket         = [];
        $bucket_id      = '';

        $hash_name      = env_cache(Config::get('cache.hash_keys.bucket_id_by_slug') . $artist_id);
        $hash_field     = $bucket_slug;
        $cache_miss     = false;

        $cacheParams['hash_name']   = $hash_name;
        $cacheParams['hash_field']  = (string) $hash_field;
        $cacheParams['expire_time'] = Config::get('cache.1_hour') * 60;

        $bucket_id = $this->awsElasticCacheRedis->getHashData($cacheParams);
        if (empty($bucket)) {
            $bucket = \App\Models\Bucketlang::where('artist_id', '=', $artist_id)->where('slug', '=', $bucket_slug)->where('language_id', $language_id)->where('status', 'active')->first();
            if($bucket) {
                $bucket = $bucket->toArray();
                $cacheParams['hash_field_value'] = $bucket['bucket_id'];
                $saveToCache    = $this->awsElasticCacheRedis->saveHashData($cacheParams);
                $cache_miss     = true;
                $bucket_id      = $this->awsElasticCacheRedis->getHashData($cacheParams);
            } else {
                $error_messages[] = 'Bucket detail not found for slug (' . $bucket_slug . ') & artist id (' . $artist_id . ').';
            }
        }

        $results['bucket_id']   = $bucket_id;
        $results['cache']       = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    /**
     * Return Content Id for given Parent Content slug
     *
     *
     * @param   string
     * @return  string
     *
     * @author Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since 2019-04-11
     */
    public function getContentParentIdBySlug($parent_slug, $bucket_id)
    {
        $error_messages = [];
        $results        = [];
        $cacheParams    = [];
        $content        = [];
        $parent_id      = '';

        $hash_name      = env_cache(Config::get('cache.hash_keys.content_parent_id_by_slug') . $bucket_id);
        $hash_field     = $parent_slug;
        $cache_miss     = false;

        $cacheParams['hash_name']   = $hash_name;
        $cacheParams['hash_field']  = (string) $hash_field;
        $cacheParams['expire_time'] = Config::get('cache.1_hour') * 60;


        $parent_id = $this->awsElasticCacheRedis->getHashData($cacheParams);
        if (empty($content)) {
            $content = \App\Models\Content::where('bucket_id', '=', $bucket_id)->where('slug', '=', $parent_slug)->first()->toArray();
            if($content) {
                $cacheParams['hash_field_value'] = $content['_id'];
                $saveToCache    = $this->awsElasticCacheRedis->saveHashData($cacheParams);
                $cache_miss     = true;
                $parent_id      = $this->awsElasticCacheRedis->getHashData($cacheParams);
            }
            else {
                $error_messages[] = 'Content Detail not found for parent slug (' . $parent_slug . ') & Bucket Id (' . $bucket_id . ').';
            }
        }

        $results['parent_id']   = $parent_id;
        $results['cache']       = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    /**
     * Return Content Age Ratings
     *
     *
     * @return  array
     *
     * @author Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since 2019-05-13
     */
    public function getAgeRatings()
    {
        $error_messages = [];
        $results        = [];

        $results = array(
            '7+' => '7+',
            '13+' => '13+',
            '16+' => '16+',
            '18+' => '18+',
        );

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    /**
     * Sets Video Parameters
     *
     *
     * @return  array
     *
     * @author Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since 2019-05-13
     */
    public function updateVideoData($data, $default_arr)
    {
        if (isset($data['type']) && in_array($data['type'], ['video'])) {

            //  Publish Date
            if (!isset($data['publish_date'])) {
                $default_arr['publish_date'] = '';
            }
            else {
                array_set($default_arr, 'publish_date', $data['publish_date']);
            }

            // Studio Name
            if (!isset($data['studio_name'])) {
                $default_arr['publish_date'] = '';
            }
            else {
                array_set($default_arr, 'studio_name', $data['studio_name']);
            }

            // Casts
            if (!isset($data['casts'])) {
                $default_arr['casts'] = [];
            }
            else {
                array_set($default_arr, 'casts', $data['casts']);
            }

            // Release Year
            if (!isset($data['release_year'])) {
                $default_arr['release_year'] = '';
            }
            else {
                array_set($default_arr, 'release_year', $data['release_year']);
            }

            // Trailer
            if (!isset($data['trailer'])) {
                $default_arr['trailer'] = '';
            }
            else {
                array_set($default_arr, 'trailer', $data['trailer']);
            }

            // Video Type
            if (!isset($data['video_type'])) {
                $default_arr['video_type'] = '';
            }
            else {
                array_set($default_arr, 'video_type', $data['video_type']);
            }
        }

        return $default_arr;
    }

    /**
     * Returns Content Info
     *
     * @param   string $content_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-24
     */
    public function info($content_id, $request, $language)
    {
        $error_messages = $results = [];

        $cache_params   = [];
        $hash_name      = env_cache(Config::get('cache.hash_keys.content_info').$content_id);
        $hash_field     = $content_id;
        $cache_miss     = false;

        $cache_params['hash_name']   =  $hash_name;
        $cache_params['hash_field']  =  (string) $hash_field;

        $content_result = $this->awsElasticCacheRedis->getHashData($cache_params);

        if (empty($content_result)) {
            // Find Contestant Content Info form Database
            $responses  = $this->repObj->info($content_id, true, true, $language);
            $items = [];
            if($responses) {
                $items  = $this->transformer->info($responses);
            }
            $cache_params['hash_field_value'] = $items;
            $save_to_cache  = $this->awsElasticCacheRedis->saveHashData($cache_params);
            $cache_miss     = true;
            $content_result = $this->awsElasticCacheRedis->getHashData($cache_params);
        }

        $results['content']         = isset($content_result['content']) ? $content_result['content'] : [];
        $results['other_contents']  = isset($content_result['other_contents']) ? $content_result['other_contents'] : [];
        $results['children']        = isset($content_result['children']) ? $content_result['children'] : [];
        $results['cache']           = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];


        return ['error_messages' => $error_messages, 'results' => $results];
    }

    /*
     * Check Whether Content Slug is unique in bucket or not
     *
     *
     * @param   string  $slug
     * @param   string  $bucket_id
     * @return  Boolean
     *
     * @author Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since 2019-06-01
     */
    public function checkSlugUniqueInBucket($slug, $bucket_id)
    {
        $ret = true;

        $ret = $this->repObj->isSlugUniqueInBucket($slug, $bucket_id);

        return $ret;
    }


    /**
     * Store Content Video Files of various languages
     *
     *
     * @param   string  $id     Content Id
     * @param
     *
     * @return  Boolean
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-05
     */
    public function uploadContentVideoFile($data) {
        $ret = false;
        $base_raw_video_url = Config::get('product.' . env('PRODUCT') . '.s3.base_urls.raw_video');
        $video_url_key  = 'url';
        $video_url_raw  = '';

        $video_default_lang = isset($data->video_lang_default) ? $data->video_lang_default : false;
        if($video_default_lang == 'true') {
            $video_default_lang = true;
        }
        else {
            $video_default_lang = false;
        }
        $video_lang         = isset($data->video_lang) ? $data->video_lang : 'eng';
        $video_lang_label   = isset($data->video_lang_label) ? $data->video_lang_label : 'ENGLISH';
        $video_url_key      = 'url_' . $video_lang;

        // Find Language Label base on language code given
        $languages =  $this->languageService->getLabelsArrayBy('code_3');
        if($languages && $video_lang) {
            $video_lang_label = isset($languages[$video_lang]) ? $languages[$video_lang] : 'English';
        }

        //upload to local drive
        $upload             = $data;
        $folder_path        = 'uploads/contents/video/';
        $obj_path           = public_path($folder_path);
        $obj_extension      = $upload->getClientOriginalExtension();
        $video_file_name    = time() . '_' . str_slug($upload->getRealPath());
        $video_file_fullname= $video_file_name . '.' . $obj_extension;
        $fullpath           = $obj_path . $video_file_fullname;
        $upload->move($obj_path, $video_file_fullname);
        @chmod($fullpath, 0777);

        //upload to aws
        $object_source_path = $fullpath;
        $object_upload_path = $video_file_fullname;
/*

        $s3 = Storage::createS3Driver(Config::get('product.' . env('PRODUCT') . '.s3.rawvideos'));
        if (env('APP_ENV', 'stg') != 'local') {
            $response = $s3->put($object_upload_path, file_get_contents($object_source_path), 'public');
        }
*/

// NEW CODE with accelerate
$s2_client_options = [
'version'     => 'latest',
'region'    => Config::get('product.' . env('PRODUCT') . '.s3.region'),
  'credentials' => [
        'key'    => Config::get('product.' . env('PRODUCT') . '.s3.key'),
        'secret' => Config::get('product.' . env('PRODUCT') . '.s3.secret'),
  ],
 'endpoint' => 'https://bfmediarawvideos.s3-accelerate.amazonaws.com',
 'use_accelerate_endpoint' => True,
];


$S3_Client = new S3Client($s2_client_options);
$bucket = Config::get('product.' . env('PRODUCT') . '.s3.rawvideos.bucket');
$key = 'EC2.pdf';
$SourceFile = '/path/to/the/file/EC2.pdf';

$put_options = [
    'Bucket' => $bucket,
    'Key' => $object_upload_path,
    'SourceFile' => $object_source_path,
];


$put = $S3_Client->putObject($put_options);
if($put) {
Log::info(__METHOD__ .  ' $put : ' , $put->toArray());
}


        $video_url_val = $base_raw_video_url . $video_file_name;
        $video_url_raw = $base_raw_video_url . $video_file_name;

        $ret = [
            'status'            => 'uploaded',
            'object_name'       => $video_file_fullname,
            'object_path'       => $object_upload_path,
            'object_extension'  => $obj_extension,
            'bucket'            => Config::get('product.' . env('PRODUCT') . '.s3.rawvideos.bucket'),
            'language'          => $video_lang,
            'language_label'    => $video_lang_label,
            'is_default'        => $video_default_lang,
            'video_url_key'     => $video_url_key,
            'video_url_raw'     => $video_url_raw,
        ];

        @unlink($fullpath);

        return $ret;
    }


    /**
     * Store Content Video Files of various languages
     *
     *
     * @param   string  $id     Content Id
     * @param
     *
     * @return  Boolean
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-05
     */
    public function storeContentVideoFiles($id, $request) {
        $ret = true;
        $base_raw_video_url = Config::get('product.' . env('PRODUCT') . '.s3.base_urls.raw_video');

        if ($request->hasFile('video')) {

            $video_file         = $request['video'];
            $video_default_lang = isset($request['video_lang_default']) ? $request['video_lang_default'] : false;
            if($video_default_lang == 'true') {
                $video_default_lang = true;
            }
            else {
                $video_default_lang = false;
            }
            $video_lang         = isset($request['video_lang']) ? $request['video_lang'] : 'eng';
            $video_lang_label   = isset($request['video_lang_label']) ? $request['video_lang_label'] : 'ENGLISH';


            $video_file->video_default_lang = $video_default_lang;
            $video_file->video_lang         = $video_lang;
            $video_file->video_lang_label   = $video_lang_label;

            $vod_job_data       = $this->uploadContentVideoFile($video_file);

            if($vod_job_data) {
                $ret = $vod_job_data;
                $store = $this->repObj->storeContentVideoFile($id, $vod_job_data);

                // If Video File is stored in db successfuly then
                // create job for transcoding
                if($store) {
                    $this->createJobForTranscoding($id, $vod_job_data);
                }
            }
        }

        return $ret;
    }


    /**
     * Store Content Subtitle Files of various languages
     *
     *
     * @param   string  $id     Content Id
     * @param
     *
     * @return  Boolean
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-05
     */
    public function storeContentSubtitleFile($data, $content_folder_name) {
        $ret = true;
        $base_raw_video_url = Config::get('product.' . env('PRODUCT') . '.s3.base_urls.raw_video');

        //upload to local drive
        if(isset($data['file'])) {
            $subtitle_upload        = $data['file'];
            $subtitle_folder_path   = 'uploads/contents/video/subtitle/';
            $subtitle_obj_path      = public_path($subtitle_folder_path);
            $subtitle_obj_extension = $subtitle_upload->getClientOriginalExtension();
            $subtitle_file_name     = time() . '_' . str_slug($subtitle_upload->getRealPath()) . '.' . $subtitle_obj_extension;
            $subtitle_fullpath      = $subtitle_obj_path . $subtitle_file_name;
            $subtitle_upload->move($subtitle_obj_path, $subtitle_file_name);
            chmod($subtitle_fullpath, 0777);

            //upload to aws
            $subtitle_object_source_path = $subtitle_fullpath;
            // "object_path" : "<video_name>/subtitles/<lang_name>_1561806920_tmpphpov9j5f.srt",
            $subtitle_object_upload_path = $content_folder_name . '/subtitles/' . $data['language'] . '_' . $subtitle_file_name;
            $s3 = Storage::createS3Driver(Config::get('product.' . env('PRODUCT') . '.s3.rawvideos'));
            if (env('APP_ENV', 'stg') != 'local') {
                $response = $s3->put($subtitle_object_upload_path, file_get_contents($subtitle_object_source_path), 'public');
            }

            $subtitle_lang      = isset($data['language']) ? $data['language'] : 'eng';
            $subtitle_lang_label= $this->findLanguageLabel($subtitle_lang);

            $ret = [
                'language'          => $subtitle_lang,
                'language_label'    => $subtitle_lang_label,
                'object_name'       => $subtitle_file_name,
                'object_path'       => $subtitle_object_upload_path,
                'object_extension'  => $subtitle_obj_extension,
                'bucket'            => 'armsrawvideos'
            ];

            //$video_url = $base_raw_video_url . $vod_job_data['object_name'];
            @unlink($subtitle_fullpath);


        }

        return $ret;
    }

    /**
     * Generate unique id for content
     *
     *
     * @return  string
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-05
     */
    public function generateUniqueId() {
        $ret = uniqid() . time();

        return $ret;
    }

    public function getContentLanguage($language_id, $bucket_id, $content_id) {

        $contentlang = \App\Models\Contentlang::active()->where('language_id', $language_id)->where('bucket_id', $bucket_id)->where('content_id', $content_id)->first();

        $results = [
            'id' => "",
            'name' => "",
            'caption' => ''
        ];

        if($contentlang) {
            $results['id'] = $contentlang->_id;
            $results['name'] = $contentlang->name;
            $results['caption'] = $contentlang->caption;
        }

        return $results;

    }

    /**
     * Store Content Video Files of various languages
     *
     *
     * @param   string  $id     Content Id
     * @param
     *
     * @return  Boolean
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-05
     */
    public function deleteContentVideoFile($id, $lang = '') {
        $ret = true;

        $ret = $this->repObj->deleteContentVideoFile($id, $lang);

        return $ret;
    }


    /**
     * Store Content Video Files of various languages
     *
     *
     * @param   string  $id     Content Id
     * @param   string  $lang   Video Language code
     *
     * @return  Boolean
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-05
     */
    public function retranscode($id, $lang = '') {
        $ret = true;
        $vod_job_data_retrans   = null;

        $content = $this->repObj->find($id);
        if($content) {
            $vod_job_data       = isset($content->vod_job_data) ? $content->vod_job_data : [];
            $video              = isset($content->video) ? $content->video : null;
            if($vod_job_data) {
                // First check whether particular langauge data exists or not
                // IF EXISTS then reset given data
                foreach ($vod_job_data as $key => $vod_job) {
                    if(isset($vod_job['language']) && $vod_job['language'] == $lang) {
                        // Reset this data in recordset
                        $vod_job_data_retrans = $vod_job;
                        $vod_job_data_retrans['status'] = 'retranscode';

                        if(isset($vod_job_data_retrans['transcode_meta_data'])) {
                           unset($vod_job_data_retrans['transcode_meta_data']);
                        }

                        if(isset($vod_job_data_retrans['error'])) {
                           unset($vod_job_data_retrans['error']);
                        }

                        if($video) {
                            if(isset($video[$lang . '_url'])) {
                                unset($video[$lang . '_url']);
                            }
                        }
                    }
                }
            }
        }

        if($vod_job_data_retrans) {
            $store = $this->repObj->storeContentVideoFile($id, $vod_job_data_retrans);
            if($store) {
                $this->createJobForTranscoding($id, $vod_job_data_retrans);
            }
        }

        return $ret;
    }


    /**
     * Create Job for transcoding
     *
     *
     * @return  string
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-05
     */
    public function createJobForTranscoding($id, $vod_job) {
        $ret = false;

        $payload_default = [
            'content_id' => $id,
            'send_notification' => 'false',
        ];

        $payload = array_merge($vod_job, $payload_default);

        $job_data = [
            'label'     => 'CreateHLSTranscodeJobForVideo',
            'type'      => 'transcode_video',
            'payload'   => $payload,
            'status'    => "scheduled",
            'delay'     => 0,
            'retries'   => 0,
        ];

        $recodset = new \App\Models\Job($job_data);
        $ret = $recodset->save();
        return $ret;
    }

    /**
     * Converts Duration into milliseconds
     *
     *
     * @param   string  $duration
     * @return  integer
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-12
     */
    public static function convertDurationInMilliseconds($duration) {
        $ret = 0;

        $multiplication_matrix = [
            1000,       // Second to millisecond
            60000,      // Minute to millisecond
            3600000,    // Hour to millisecond
        ];

        $duration       = trim($duration);
        $duration_arr   = explode(':', $duration);
        if($duration_arr) {
            $multiplication_index = 0;
            for ($i=count($duration_arr); $i > 0 ; $i--) {
                $arr_index = $i - 1;
                $val = intval($duration_arr[$arr_index]);
                if($val) {
                    $ret = $ret + ($val * $multiplication_matrix[$multiplication_index]);
                }
                $multiplication_index++;
            }
        }

        return $ret;
    }

    /**
     * Return Language label base on language code_3 value
     *
     *
     * @param   string  $code_3
     * @return  string
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-13
     */
    public function findLanguageLabel($lang_code_3) {
        $ret = 'ENGLISH';

        // Find Language Label base on language code given
        $languages      = $this->languageService->getLabelsArrayBy('code_3');
        if($languages && $lang_code_3) {
            $ret = isset($languages[$lang_code_3]) ? $languages[$lang_code_3] : 'English';
        }

        return $ret;
    }


    /**
     * Store Contestant Artist Content
     *
     *
     * @param   array   $data
     * @param   string  $artist_id     Artist Id
     *
     * @return  Boolean
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-22
     */
    public function storeContestantContent($data, $artist_id) {
        $ret = false;

        // First store content in content collection
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

                if(!$artist_default_lang) {
                    $artist_default_lang = '5d1e144618a01f24ae0c9972';
                }
            }
            $data['language_id'] = $artist_default_lang;
        }

        if(!isset($data['is_default_language'])) {
            $data['is_default_language'] = true;
        }

        $content =  $this->repObj->store($data);
        if($content && isset($content->_id)) {
            $ret = $content;
            $this->repObj->contentUpdate($data, $content->_id);
        }

        return $ret;
    }


    /**
     * List Search Contents
     *
     * @param   \Illuminate\Http\Request
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-11-26
     */
    public function search($request) {
        $data               = $request->all();
        $artist_id          = (isset($data['artist_id']) && !empty($data['artist_id']) ) ? trim($data['artist_id']) : '';
        $keyword            = (isset($data['keyword']) && !empty($data['keyword']) ) ? trim(strtolower($data['keyword'])) : '';
        $page               = (isset($data['page']) && $data['page'] != '') ? trim($data['page']) : '1';
        $error_messages     = [];
        $results            = [];
        $response           = [];


        if(empty($error_messages)) {

            // First Check in Cache whether data exists or not
            $cache_params   = [];
            $hash_name      = env_cache(Config::get('cache.hash_keys.content_search_list') . ':' . $artist_id);
            $hash_field     = $keyword . ':' . $page;
            $cache_miss     = false;

            $cache_params['hash_name']   = $hash_name;
            $cache_params['hash_field']  = (string) $hash_field;

            $response   = $this->awsElasticCacheRedis->getHashData($cache_params);

            if(!$response) {
                $response_data = $this->repObj->search($data);
                if($response_data) {
                    $response  = $this->transformer->search($response_data);
                }
                $cache_params['hash_field_value'] = $response;
                $save_to_cache  = $this->awsElasticCacheRedis->saveHashData($cache_params);
                $cache_miss     = true;
                $greetings      = $this->awsElasticCacheRedis->getHashData($cache_params);
            }

            $results = $response;
            $results['cache']    = [
                'hash_name'     => $hash_name,
                'hash_field'    => $hash_field,
                'cache_miss'    => $cache_miss
            ];
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }



     public function paidContentList()
    {
        $error_messages = $results = [];
        $results = $this->repObj->paidContentList();

        return ['error_messages' => $error_messages, 'results' => $results];
    }
    public function searchListing($request)
    {

        $requestData = $request->all();
        $results     = $this->repObj->searchListing($requestData);
        return $results;
    }
}
