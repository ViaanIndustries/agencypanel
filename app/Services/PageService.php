<?php

namespace App\Services;

use Input;
use Redirect;
use Config;
use Session;
use App\Repositories\Contracts\PageInterface;
use App\Models\Page as Page;
use App\Services\Image\Kraken;
use App\Services\AwsCloudfront;
use App\Services\Cache\AwsElasticCacheRedis;
use App\Services\ArtistService;

class PageService
{
    protected $repObj;
    protected $page;
    protected $kraken;
    protected $awscloudfrontService;
    protected $awsElasticCacheRedis;
    protected $serviceartist;

    public function __construct(Page $page, PageInterface $repObj, Kraken $kraken, AwsCloudfront $awscloudfrontService, AwsElasticCacheRedis $awsElasticCacheRedis, ArtistService $serviceartist)
    {
        $this->page                 = $page;
        $this->repObj               = $repObj;
        $this->kraken               = $kraken;
        $this->awscloudfrontService = $awscloudfrontService;
        $this->awsElasticCacheRedis = $awsElasticCacheRedis;
        $this->serviceartist        = $serviceartist;
    }

    public function index($request)
    {
        $results = $this->repObj->index($request);
        return $results;
    }


    public function paginate()
    {
        $error_messages     =   $results = [];
        $results = $this->repObj->paginateForApi();

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function activeLists()
    {
        $error_messages     =   $results = [];
        $results = $this->repObj->activeLists();

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function find($id)
    {
        $results = $this->repObj->find($id);
        return $results;
    }


    public function show($id)
    {
        $error_messages     =   $results = [];
        if(empty($error_messages)){
            $results['role']    =   $this->repObj->find($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function store($request)
    {
        $data               =   $request->all();
        $error_messages     =   $results = [];
        $slug               =   str_slug($data['name']);
        array_set($data, 'slug', $slug);

        if(empty($error_messages)){
            $results['role']    =   $this->repObj->store($data);

            $purge_result       =   $this->awsElasticCacheRedis->purgeHomePageListCache($data);

            if (env('APP_ENV', 'stg') == 'production') {
                try {
                    $invalidate_result = $this->awscloudfrontService->invalidateHomePageSections();
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
        $data               =   $request->all();
        $error_messages     =   $results = [];
        $slug               =   str_slug($data['name']);
        array_set($data, 'slug', $slug);

        if(empty($error_messages)){
            $results['role']   = $this->repObj->update($data, $id);

            $purge_result       =   $this->awsElasticCacheRedis->purgeHomePageListCache($data);

            if (env('APP_ENV', 'stg') == 'production') {
                try {
                    $invalidate_result = $this->awscloudfrontService->invalidateHomePageSections();
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

    public function getSetionTypes()
    {
        return $this->page->getSetionTypes();
    }

    public function artistBucketList($artist_id)
    {
        $error_messages = $results = [];
        $results = $this->repObj->artistBucketList($artist_id);

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function updateItems($data, $id)
    {
        $error_messages     =   $results = [];

        if(isset($data['type'])) {
            switch ($data['type']) {
                case 'banner':
                    $page   = $this->find($id);
                    foreach ($data['banners'] as $key => $banner_info) {
                        if(isset($banner_info['photo_file']) && $banner_info['photo_file']) {
                            $photo_obj  = null;
                            $parmas     = ['file' => $banner_info['photo_file'], 'type' => 'homepagebanners'];
                            $photo      = $this->kraken->uploadToAws($parmas);
                            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                                $data['banners'][$key]['photo'] =  $photo['results'];
                            }
                        }
                        else {
                            if(isset($page['banners']) && isset($page['banners'][$key]) && isset($page['banners'][$key]['photo'])) {
                                $data['banners'][$key]['photo'] =  $page['banners'][$key]['photo'];
                            }
                        }
                        unset($data['banners'][$key]['photo_file']);
                    }
                    break;

                default:
                    # code...
                    break;
            }
        }

        if(empty($error_messages)){
            $results['role']   = $this->repObj->updateItems($data, $id);

            $purge_result       =   $this->awsElasticCacheRedis->purgeHomePageListCache($data);

            if (env('APP_ENV', 'stg') == 'production') {
                try {
                    $invalidate_result = $this->awscloudfrontService->invalidateHomePageSections();
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

    public function getBucketContents($bucket_id)
    {
        return $this->page->getBucketContents($bucket_id);
    }

    public function homepage($request, $language = null)
    {
        $requestData    = $request->all();
        $error_messages = '';
        $platform       = (isset($requestData['platform']) && $requestData['platform'] != '') ? trim($requestData['platform']) : 'android';
        $page           = (isset($requestData['page']) && $requestData['page'] != '') ? trim($requestData['page']) : '1';

        $cacheParams    = [];
        $hash_name      = env_cache(Config::get('cache.hash_keys.homepage_listing') . $platform );
        $hash_field     = $page;
        $cache_miss     = false;

        $cacheParams['hash_name']   =  $hash_name;
        $cacheParams['hash_field']  =  (string)$hash_field;

        $results = $this->awsElasticCacheRedis->getHashData($cacheParams);
        if (empty($results)) {
            $responses  = $this->generateHomepageData($request, $language);
            $items      = ($responses) ? apply_cloudfront_url($responses) : [];
            $cacheParams['hash_field_value'] = $items;
            $saveToCache= $this->awsElasticCacheRedis->saveHashData($cacheParams);
            $cache_miss = true;
            $results    = $this->awsElasticCacheRedis->getHashData($cacheParams);
        }

        $results['cache']    = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        return ['error_messages' => $error_messages, 'results' => $results];
    }



    /**
     * Return Homepage Data
     *
     * @param   string  $id
     *
     * @return  array   Customer Detail
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-27
     */

    public function generateHomepageData($requestData, $language = null) {
        $results    = [];
        $responeData= [];
        $perpage    = 5;
        $platforms  = (isset($requestData['platforms']) && $requestData['platforms'] != '') ? $requestData['platforms'] : '';

        // Find distinct Artist Ids
        $artist_ids         = [];
        $artist_ids_deatils = [];
        $artist_columns     = ['first_name', 'last_name', 'identity', 'photo', 'stats'];

        // Find distinct Content Ids
        $content_ids        = [];
        $content_ids_details= [];
        $content_columns    = ['name', 'caption', 'photo', 'video', 'audio', 'type', 'slug', 'coins', 'stats', 'photo_portrait'];

        // Find Home Page Sections order by ORDERING
        $pages  = [];

        $language_code2 = $language;

        $page_query         = $this->page->with([
            'bucket' => function ($q) {
                $q->select('name', 'code');
            }
        ]);

        $page_query->where('page_name', '=', 'home')->where('status', '=', 'active')->orderBy('ordering');

        if ($platforms != '') {
            $page_query->whereIn('platforms', [$platforms]);
        }

        $page_query_result = $page_query->paginate($perpage)->toArray();

        $page_sections = $page_query_result['data'];
        if($page_sections) {
            foreach ($page_sections as $key => $page_section) {
                if(isset($page_section['type'])) {
                    switch ($page_section['type']) {
                        case 'artist':
                            if(!empty($page_section['artists'])) {
                                $artists_col = collect($page_section['artists']);
                                foreach ($artists_col as $key => $value) {
                                    if(!in_array($value['artist_id'], $artist_ids)) {
                                        $artist_ids[] = $value['artist_id'];
                                    }
                                }
                            }
                            break;

                        case 'content':
                            if(!empty($page_section['contents'])) {
                                $contents_col = collect($page_section['contents']);
                                foreach ($contents_col as $key => $value) {
                                    if(!in_array($value['content_id'], $content_ids)) {
                                        $content_ids[] = $value['content_id'];
                                    }
                                }
                            }

                            break;
                        default:
                            # code...
                            break;
                    }
                }
            }
        }

        // If Artist Ids exits then find all artists details
        if($artist_ids) {
            $artist_ids_deatils_obj = \App\Models\Cmsuser::where('status', 'active')->whereIn('_id', $artist_ids)->orderBy('_id', 'desc')->get($artist_columns)->keyBy('_id');
            if($artist_ids_deatils_obj) {
                $artist_ids_deatils = $artist_ids_deatils_obj->toArray();
            }
        }

        // If Content Ids exits then find all content details
        if($content_ids) {

            $language_ids = [];
            $default_lang_id = "";
            $requested_lang_id = "";

            $artist_id = Config::get('app.HOME_PAGE_ARTIST_ID');

            $config_language_data = $this->serviceartist->getConfigLanguages($artist_id);

            if($language_code2) {
                foreach ($config_language_data as $key => $lang) {
                    if($lang['code_2'] == $language_code2) {
                        $requested_lang_id = $lang['_id'];
                        array_push($language_ids, $lang['_id']);
                    }

                    if($lang['is_default'] == true) {
                        $default_lang_id = $lang['_id'];
                        array_push($language_ids, $lang['_id']);
                    }
                }
            }

            $language_ids = array_unique($language_ids);

            $content_ids_details_obj = \App\Models\Content::where('status', 'active')->whereIn('_id', $content_ids)->with(['contentlanguages' => function($query) use($language_ids) { $query->whereIn('language_id', $language_ids)->project(['content_id' => 1, 'bucket_id' => 1, 'language_id' => 1, 'name' => 1, 'caption' => 1, 'slug' => 1]); }])->orderBy('_id', 'desc')->get($content_columns)->keyBy('_id');
            if($content_ids_details_obj) {
                $content_ids_details = $content_ids_details_obj->toArray();
            }

        }

        // Prepare final result
        if($page_sections) {
            foreach ($page_sections as $key => $section) {
                $result = array_except($section, ['artists', 'contents']);
                if(isset($result['type'])) {
                    $artists    = [];
                    $contents   = [];
                    switch ($result['type']) {
                        case 'artist':
                            if(!empty($section['artists'])) {
                                foreach ($section['artists'] as $key => $value) {
                                    $artist     = isset($artist_ids_deatils[$value['artist_id']]) ? $artist_ids_deatils[$value['artist_id']] : null;
                                    if($artist) {
                                        $artist['order']    = $value['order'];
                                        $artist['artist_id']= $value['artist_id'];
                                        unset($artist['_id']);
                                        $artists[]  = $artist;
                                    }
                                }
                            }

                            // Randomize artists sorting
                            // So that home page api artist section
                            // will have ramdom artist ordering
                            $artists = $this->randomizeArtistArray($artists);
                            $result['artists'] = $artists;
                            break;

                        case 'content':
                            if(!empty($section['contents'])) {
                                foreach ($section['contents'] as $key => $value) {

                                    $content    = isset($content_ids_details[$value['content_id']]) ? $content_ids_details[$value['content_id']] : null;
                                    if($content) {

                                        $content_language = $content['contentlanguages'];

                                        foreach($content_language as $lang_data) {

                                            if(in_array($requested_lang_id, $lang_data)) {

                                                $content['caption'] = (isset($lang_data['caption']) && $lang_data['caption'] != '') ? trim($lang_data['caption']) : '';
                                                $content['name'] = (isset($lang_data['name']) && $lang_data['name'] != '') ? trim($lang_data['name']) : '';
                                                $content['slug'] = (isset($lang_data['slug']) && $lang_data['slug'] != '') ? trim($lang_data['slug']) : '';

                                                continue;

                                            } else {

                                                if(in_array($default_lang_id, $lang_data)) {

                                                    $content['caption'] = (isset($lang_data['caption']) && $lang_data['caption'] != '') ? trim($lang_data['caption']) : '';
                                                    $content['name'] = (isset($lang_data['name']) && $lang_data['name'] != '') ? trim($lang_data['name']) : '';
                                                    $content['slug'] = (isset($lang_data['slug']) && $lang_data['slug'] != '') ? trim($lang_data['slug']) : '';
                                                }
                                            }

                                        }

                                        unset($content['contentlanguages']);

                                        $genres = [];
                                        if(isset($content['genres'])) {
                                            $genres = \App\Models\Genre::whereIn('_id', $content['genres'])->where('status', 'active')->get(['name'])->toArray();
                                            $content['genres'] = $genres;
                                        }

                                        $content['order']        = $value['order'];
                                        $content['content_id']   = $value['content_id'];
                                        // If content type is video
                                        // And Video has casts then find cast details
                                        if($content['type'] == 'video' && isset($content['casts']) && !empty($content['casts'])) {
                                            $content_casts = [];
                                            $content_casts_obj = \App\Models\Cast::whereIn('_id', $content['casts'])->where('status', 'active')->get(['first_name', 'last_name', 'photo']);
                                            if($content_casts_obj) {
                                                $content_casts = $content_casts->toArray();
                                            }
                                            $content['casts'] = $content_casts;
                                        }
                                        $contents[] = $content;
                                    }
                                }
                            }
                            $result['contents'] = $contents;
                            break;
                        default:
                            # code...
                            break;
                    }
                }
                $results[] = $result;
            }
        }

        $responeData['list']                            = $results;
        $responeData['paginate_data']['total']          = (isset($page_query_result['total'])) ? $page_query_result['total'] : 0;
        $responeData['paginate_data']['per_page']       = (isset($page_query_result['per_page'])) ? $page_query_result['per_page'] : 0;
        $responeData['paginate_data']['current_page']   = (isset($page_query_result['current_page'])) ? $page_query_result['current_page'] : 0;
        $responeData['paginate_data']['last_page']      = (isset($page_query_result['last_page'])) ? $page_query_result['last_page'] : 0;
        $responeData['paginate_data']['from']           = (isset($page_query_result['from'])) ? $page_query_result['from'] : 0;
        $responeData['paginate_data']['to']             = (isset($page_query_result['to'])) ? $page_query_result['to'] : 0;
        return $responeData;
    }

    /**
     * Return Random Array Key Value
     *
     * @param   integer  $max_array_key_value
     * @param   array    $already_added_keys
     *
     * @return  integer  Random Array Key Value
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-16
     */

    public function generateRandomArrayKey($max_array_key_value, $already_added_keys = [])
    {
        $ret = 0;
        $not_added_keys = [];
        if(empty($already_added_keys)) {
            $ret = rand(0, ($max_array_key_value -1));
        }
        else {
            for ($i=0; $i < $max_array_key_value; $i++) {
                if(!in_array($i, $already_added_keys)) {
                    $not_added_keys[$i] = $i;
                }
            }

            $ret = array_rand($not_added_keys, 1);
        }

        return $ret;
    }

    /**
     * Return Randomize Artist Array
     *
     * @param   array   $artits // Orginal Artist Array
     *
     * @return  array   Randomize Artist Array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-16
     */

    public function randomizeArtistArray($artists = [])
    {
        $ret = [];
        $already_added_keys = [];
        if($artists) {
            $total_artists  = count($artists);

            for ($i=0; $i < $total_artists; $i++) {
                $rand_key   = $this->generateRandomArrayKey($total_artists, $already_added_keys);
                $already_added_keys[] = $rand_key;

                if(isset($artists[$rand_key])) {
                    $ret[] = $artists[$rand_key];
                }
            }
        }

        return $ret;
    }
}
