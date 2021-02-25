<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\ContentInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use Config;
use Google\Cloud\Dlp\V2\DateTime;
use Request;
use App\Services\Jwtauth;
use Carbon, Log;

use MongoDB\BSON\UTCDateTime;

use App\Models\Polloption as PollOption;
use App\Models\Pollresult as PollResult;
use App\Models\Contentlang as Contentlang;
use App\Services\RedisDb;
use Aws\S3\MultipartUploader;
use App\Services\ArtistService;



use App\Services\ContentService;
use App\Services\PassbookService;


class ContentRepository extends AbstractRepository implements ContentInterface
{

    protected $modelClassName = 'App\Models\Content';
    protected $jwtauth;
    protected $redisdb;
    protected $artistservice;
    protected $passbookservice;


    public function __construct(Jwtauth $jwtauth, RedisDb $redisdb, ArtistService $artistservice, PassbookService $passbookservice)
    {
        $this->jwtauth = $jwtauth;
        $this->model = \App::make($this->modelClassName);
        $this->redisdb = $redisdb;
	$this->artistservice = $artistservice;
	$this->passbookservice = $passbookservice;

    }

    private $unwanted_keys = [
        'mediaconvert_data',
        'transcode_meta_data',
        'vod_job_data',
        'video_status',
        'aod_job_data',
        'audio_status',
    ];


    public function getDashboardStatsQuery($requestData)
    {
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';
        $type = (isset($requestData['type']) && $requestData['type'] != '') ? $requestData['type'] : '';

//        $created_at = mongodb_start_date_millsec((isset($requestData['created_at']) && $requestData['created_at'] != '') ? $requestData['created_at'] : '');
//        $created_at_end = mongodb_end_date_millsec((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? $requestData['created_at_end'] : '');

        $created_at = ((isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '');
        $created_at_end = ((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '');

        $query = \App\Models\Content::orderBy('created_at', 'desc');

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

//        if ($created_at != '') {
//            $query->where("created_at", '>=', $created_at);
//        }
//
//        if ($created_at_end != '') {
//            $query->where("created_at", '<=', $created_at_end);
//        }
        if ($created_at != '') {
            $query->where('created_at', '>=', mongodb_start_date($created_at));
        }

        if ($created_at_end != '') {
            $query->where('created_at', '<=', mongodb_end_date($created_at_end));
        }

        if ($type != '') {
            $query->where('type', $type);
        }

        if ($status != '') {
            $query->where('status', $status);
        }

        return $query;

    }

    public function getArtistWiseContentStats($requestData)
    {
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';

        if (!empty($artist_id)) {
            $artists = \App\Models\Cmsuser::where('status', '=', 'active')->where('_id', $artist_id)->get(['first_name', 'last_name', '_id']);
            if($artists) {
                $artists = $artists->toArray();
            }
            $artist_ids = array($artist_id);
        } else {
            $artist_role_ids = \App\Models\Role::where('slug', 'artist')->lists('_id');
            $artist_role_ids = ($artist_role_ids) ? $artist_role_ids->toArray() : [];
            $artists = \App\Models\Cmsuser::where('status', '=', 'active')->whereIn('roles', $artist_role_ids)->get(['first_name', 'last_name', '_id']);
            if($artists) {
                $artists = $artists->toArray();
            }
            $artist_ids = array_pluck($artists, '_id');
        }

        /*
         * http://iknowit.inf.ovh/database/mongodb/aggregate-queries-examples
         * https://differential.com/insights/mongodb-aggregation-pipeline-patterns-part-1/
         */

//        $created_at = mongodb_start_date_millsec((isset($requestData['created_at']) && $requestData['created_at'] != '') ? $requestData['created_at'] : Config::get('app.start_date'));
//        $created_at_end = mongodb_end_date_millsec((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? $requestData['created_at_end'] : date('m/d/Y h:i:s', time()));

        $created_at = mongodb_start_date((isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '');
        $created_at_end = mongodb_end_date((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '');

        $artistwise_customers = \App\Models\Content::raw(function ($collection) use ($artist_ids, $created_at, $created_at_end) {

            $aggregate = [
                [
                    '$match' => [
                        'artist_id' => ['$in' => $artist_ids],
                        'status' => ['$in' => ['active']],
                        '$and' => [
                            ["created_at" => ['$gte' => $created_at]],
                            ["created_at" => ['$lte' => $created_at_end]]
                        ],

                    ]
                ],
                [
                    '$group' => [
                        '_id' => ['artist_id' => '$artist_id'],
                        'total_contents' => ['$sum' => 1],
                        "total_photos" => ['$sum' => ['$cond' => [['$eq' => ['$type', 'photo']], 1, 0]]],
                        "total_videos" => ['$sum' => ['$cond' => [['$eq' => ['$type', 'video']], 1, 0]]]
                    ]
                ],
                [
                    '$project' => [
                        '_id' => '$_id.artist_id',
                        'artist_id' => '$_id.artist_id',
                        'total_contents' => '$total_contents',
                        'total_photos' => '$total_photos',
                        'total_videos' => '$total_videos',

                    ]
                ]
            ];


            return $collection->aggregate($aggregate);
        });

        $artistsArr = [];
        $artistwise_customers = $artistwise_customers->toArray();


        foreach ($artists as $artist) {

            $artist_id = $artist['_id'];
            $artistobj = $artist;
            $name = $artist['first_name'] . ' ' . $artist['last_name'];

            $stats = head(array_where($artistwise_customers, function ($key, $value) use ($artist_id) {
                if ($value['_id'] == $artist_id) {
                    return $value;
                }
            }));


            $stats['total_contents'] = (isset($stats['total_contents'])) ? $stats['total_contents'] : 0;
            $stats['total_photos'] = (isset($stats['total_photos'])) ? $stats['total_photos'] : 0;
            $stats['total_videos'] = (isset($stats['total_videos'])) ? $stats['total_videos'] : 0;
            $stats['artist_id'] = $artist_id;
            $artist_info = array_merge($artist, $stats, ['name' => ucwords($name)]);
            array_push($artistsArr, $artist_info);
        }
        return $artistsArr;

    }

    public function getRecentComments($requestData)
    {

        $data = $requestData;
        $artist_id = (isset($data['artist_id']) && $data['artist_id'] != '') ? $data['artist_id'] : '';

//        $created_at = (isset($data['created_at']) && $data['created_at'] != '') ? hyphen_date($data['created_at']) : '';
//        $created_at_end = (isset($data['created_at_end']) && $data['created_at_end'] != '') ? hyphen_date($data['created_at_end']) : '';

        $created_at = ((isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '');
        $created_at_end = ((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '');


        $perpage = (isset($data['perpage'])) ? intval($data['perpage']) : 5;
        $comments = \App\Models\Comment::where('status', '=', 'active')
            ->where('entity', 'content')
            ->where("level", 1)
            ->with('customer')
            ->with('producer')
            ->orderBy('_id', 'desc')
            ->take($perpage);

        if ($artist_id != '') {
            $comments->where('artist_id', $artist_id);
        }

        if ($created_at != '') {
            $comments->where('created_at', '>', mongodb_start_date($created_at));
        }

        if ($created_at_end != '') {
            $comments->where('created_at', '<', mongodb_end_date($created_at_end));
        }
        $comments = $comments->get();
        if($comments) {
            $comments = $comments->toArray();
        }

        $commentArr = [];

        foreach ($comments as $comment) {

            $commented_by = (isset($comment['commented_by']) && $comment['commented_by'] != "") ? $comment['commented_by'] : "customer";
            $customer_default = ['_id' => '', 'first_name' => '', 'last_name' => '', 'picture' => 'https://storage.googleapis.com/arms-razrmedia/customers/profile/default.png'];
            $customer = [];


            if (isset($comment['customer']) && $commented_by == "customer") {
                $customer = array_only($comment['customer'], ['_id', 'first_name', 'last_name', 'picture']);
            }

            if (isset($comment['producer']) && $commented_by == "artist") {
                $customer = array_only($comment['producer'], ['_id', 'first_name', 'last_name', 'picture']);
            }


            $comment['customer'] = array_merge($customer_default, $customer);

            if (isset($comment['is_social']) && $comment['is_social'] == 1 && isset($comment['social_user_name'])) {
                $customer = ['_id' => '', 'first_name' => $comment['social_user_name'], 'last_name' => '', 'picture' => 'https://storage.googleapis.com/arms-razrmedia/customers/profile/default.png'];
                $comment['customer'] = array_merge($customer_default, $customer);
            }

            $comment['human_readable_created_date'] = Carbon\Carbon::parse($comment['created_at'])->format('F j\\, Y h:i A');
            $comment['date_diff_for_human'] = Carbon\Carbon::parse($comment['created_at'])->diffForHumans();
            $comment['commented_by'] = (isset($comment['commented_by']) && $comment['commented_by'] != "") ? $comment['commented_by'] : "customer";
            $comment = array_except($comment, ['entity', 'customer_id', 'customer_name', 'social_user_name', 'social_comment_id', 'producer']);

            array_push($commentArr, $comment);
        }
        return $commentArr;
    }

    public function listing($bucket_id, $level = NULL, $requestData)
    {
        $bucket_id = trim($bucket_id);
        $level = ($level == NULL) ? 1 : intval($level);
//        $perpage = ($requestData['perpage'] == NULL) ? Config::get('app.perpage') : intval($requestData['perpage']);
        $perpage = 25;
        $name = (isset($requestData['name']) && $requestData['name'] != '') ? $requestData['name'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';
        $pin_to_top = (isset($requestData['pin_to_top']) && $requestData['pin_to_top'] != '') ? $requestData['pin_to_top'] : '';
        $platforms = (isset($requestData['platforms']) && $requestData['platforms'] != '') ? $requestData['platforms'] : '';

        $appends_array = array(
            'name' => $name,
            'status' => $status,
            'pin_to_top' => $pin_to_top,
            'platforms' => $platforms
        );

        $artist_info = \App\Models\Bucket::active()->where('_id', $bucket_id)->first();
        if($artist_info) {
            $artist_id = $artist_info->artist_id;
        }

        \DB::connection('arms_contents')->enableQueryLog();

        $query = \App\Models\Content::with(['bucket', 'artistconfig'])
            ->with(['contentlanguages' => function ($query) {
                $query->where('is_default_language', true);
            }])
            ->where('bucket_id', $bucket_id)
            ->where('level', intval($level))
            ->orderBy('published_at', 'desc'); //->get(['contentlanguages','_id']);

         // dd($data = $query->toArray());

        // dd(\DB::connection('arms_contents')->getQueryLog($query));

        $pin_to_top = $pin_to_top == 'yes' ? true : false;

        if ($name != '') {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }

        if ($pin_to_top != '') {
            $query->where('pin_to_top', $pin_to_top);
        }

        if ($status != '') {
            $query->where('status', $status);
        }

        if ($platforms != '') {
            $query->whereIn('platforms', [$platforms]);
        }

        $results['contents'] = $query->paginate($perpage);

        $results['appends_array'] = $appends_array;

        return $results;
    }

    public function getRecentPostforArtist($request)
    {
        $error_messages = [];
        $domain = ($request['domain']);
        $perpage = ($request['perpage'] == NULL) ? Config::get('app.perpage') : intval($request['perpage']);
        $artistExists = \App\Models\Artistconfig::where('domain', $domain)->first();

        if ($artistExists != NULL) {

            $bucket_id = (isset($artistExists['social_bucket_id'])) ? $artistExists['social_bucket_id'] : '';

            if ($bucket_id != '') {

                $data = \App\Models\Content::with('bucket')->where('bucket_id', $bucket_id)->orderBy('created_at', 'desc')->paginate($perpage);
                if($data) {
                    $data = $data->toArray();
                }

                $contents = (isset($data['data'])) ? $data['data'] : [];
                $responeData = [];
                $responeData['list'] = $contents;
                $responeData['paginate_data']['total'] = (isset($data['total'])) ? $data['total'] : 0;
                $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
                $responeData['paginate_data']['current_page'] = (isset($data['current_page'])) ? $data['current_page'] : 0;
                $responeData['paginate_data']['last_page'] = (isset($data['last_page'])) ? $data['last_page'] : 0;
                $responeData['paginate_data']['from'] = (isset($data['from'])) ? $data['from'] : 0;
                $responeData['paginate_data']['to'] = (isset($data['to'])) ? $data['to'] : 0;

            } else {
                $error_messages[] = 'Social Bucket ID Not Valid';
            }

        } else {
            $error_messages[] = 'The Artist Does Not Exist';
        }
        $results['error_messages'] = $error_messages;

        return $responeData;
    }

    public function photoListing($content_id, $requestData)
    {
        $content_id = trim($content_id);
        // $perpage        =   ($perpage == NULL)  ?   Config::get('app.perpage') : intval($perpage);
        $perpage = ($requestData['perpage'] == NULL) ? Config::get('app.perpage') : intval($requestData['perpage']);


        $name = (isset($requestData['name']) && $requestData['name'] != '') ? $requestData['name'] : '';
        $caption = (isset($requestData['caption']) && $requestData['caption'] != '') ? $requestData['caption'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';
        $platforms = (isset($requestData['platforms']) && $requestData['platforms'] != '') ? $requestData['platforms'] : '';


        $appends_array = array('name' => $name, 'caption' => $caption, 'status' => $status, 'platforms' => $platforms);

        $query = \App\Models\Content::with('bucket', 'contentlanguages')
            ->where('parent_id', $content_id)
            ->where('level', 2)
            ->where('type', 'photo')
            ->orderBy('published_at', 'desc');//->paginate($perpage);

        if ($name != '') {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }

        if ($caption != '') {
            $query->where('caption', 'LIKE', '%' . $caption . '%');
        }

        if ($status != '') {
            $query->where('status', $status);
        }

        if ($platforms != '') {
            $query->whereIn('platforms', [$platforms]);
        }

        $results['contents'] = $query->paginate($perpage);
        $results['appends_array'] = $appends_array;

        return $results;

    }

    public function videoListing($content_id, $perpage = NULL)
    {
        $content_id = trim($content_id);
//        $perpage = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
        $perpage = 25;

        $contents = \App\Models\Content::where('parent_id', $content_id)
            ->where('level', 2)
            ->where('type', 'video')
            ->orderBy('published_at', 'desc')->paginate($perpage);

        return $contents;

    }

    public function audioListing($content_id, $perpage = NULL)
    {
        $content_id = trim($content_id);
        $perpage = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);

        $contents = \App\Models\Content::where('parent_id', $content_id)
            ->where('level', 2)
            ->where('type', 'audio')
            ->orderBy('published_at', 'desc')->paginate($perpage);

        return $contents;

    }

    public function lists($requestData)
    {
        $data                           =   $requestData;
        $visiblity                      =   (isset($data['visiblity'])) ? trim($data['visiblity']) : 'customer';
        $artist_id                      =   (isset($data['artist_id']) && $data['artist_id'] != '') ? trim($data['artist_id']) : '';
        $bucket_id                      =   (isset($data['bucket_id'])) ? trim($data['bucket_id']) : '';
        $parent_id                      =   (isset($data['parent_id'])) ? trim($data['parent_id']) : '';
        $source                         =   (isset($data['source'])) ? array_map('trim', $data['source']) : [];
        $platform                       =   (isset($data['platform']) && $data['platform'] != '') ? trim($data['platform']) : 'android';
        $platform_version               =   (!empty($data['v'])) ? strtolower(trim($data['v'])) : '';
        $perpage                        =   10;
        $page                           =   (isset($data['page'])) ? intval($data['page']) : 1;
        $is_test_enable                 =   (!empty($data['is_test_enable'])) ? strtolower(trim($data['is_test_enable'])) : '';
        $language_code2                 =   (isset($requestData['lang']) && $requestData['lang'] != '') ? $requestData['lang'] : 'en';

        $language_ids = [];
        $default_lang_id = "";
        $requested_lang_id = "";

        $config_language_data = $this->artistservice->getConfigLanguages($artist_id);

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



//        $dataQuery = \App\Models\Content::where('pin_to_top', false)->whereIn('platforms', [$platform])->where('bucket_id', $bucket_id);
//        $pinToTopQuery = \App\Models\Content::where('pin_to_top', true)->whereIn('platforms', [$platform])->where('bucket_id', $bucket_id);

//        $dataQuery = \App\Models\Content::where('pin_to_top', false)->where('bucket_id', $bucket_id);
//        $dataQuery = \App\Models\Content::where('bucket_id', $bucket_id);

        if($visiblity != 'customer') {
            $dataQuery          =   \App\Models\Content::where('bucket_id', $bucket_id)->with(['contentlanguages' => function($query) use($language_ids) { $query->whereIn('language_id', $language_ids)->project(['content_id' => 1, 'bucket_id' => 1, 'language_id' => 1, 'name' => 1, 'caption' => 1, 'slug' => 1]); }]);
        } else {
            $dataQuery          =   \App\Models\Content::where('bucket_id', $bucket_id)->with(['contentlanguages' => function($query) use($language_ids) { $query->whereIn('language_id', $language_ids)->project(['content_id' => 1, 'bucket_id' => 1, 'language_id' => 1, 'name' => 1, 'caption' => 1, 'slug' => 1]); }])->whereIn('platforms', [$platform]);
        }

        $check_age_content  =   \App\Models\Artistconfig::where('artist_id', $data['artist_id'])->first(['18_plus_age_content_android', '18_plus_age_content_ios']);

        if (!empty($check_age_content)) {
            $age_content_android = $check_age_content['18_plus_age_content_android'];
            $age_content_ios = $check_age_content['18_plus_age_content_ios'];

            if ($platform == 'android') {
                $content_age_wise = !empty($age_content_android) ? $age_content_android : '';
            } else {
                $content_age_wise = !empty($age_content_ios) ? $age_content_ios : '';
            }

            if ($content_age_wise == 'false') {
                $dataQuery->whereIn('18_plus_age_content', ['false', null]);
            }
        }

        // For Is Test Reivew manipulations
        if($is_test_enable == 'true' ) {
            $dataQuery->whereIn('is_test_enable', [$is_test_enable]);
        }

        $pinToTopQuery = \App\Models\Content::where('pin_to_top', true)->where('bucket_id', $bucket_id);

        if (!empty($source) && count($source) > 0) {
            $dataQuery->whereIn('source', $source);
        }

        if ($parent_id == '') {
            $dataQuery->where('level', 1);
            $pinToTopQuery->where('level', 1);
        } else {
            $dataQuery->where('parent_id', $parent_id)->where('level', 2);
            $pinToTopQuery->where('parent_id', $parent_id)->where('level', 2);
        }

        if($is_test_enable == 'true' ){
            $pinToTopQuery->whereIn('is_test_enable', [$is_test_enable]);
        }

//        if ($visiblity == 'producer') {
//            $dataQuery->whereIn('status', ['active', 'uploaded']);
//            $dataQuery->whereIn('video_status', ['uploaded', 's']);

//        if ($visiblity == 'producer') {
//            $dataQuery->where(function ($query) {
//                $query->orWhereIn('status', ['active']);
//                $query->orWhereIn('video_status', ['uploaded', 'submitted']);
//                $query->orWhereIn('audio_status', ['uploaded', 'submitted']);
//            });
//        }else{
//
//            $dataQuery->where('status', 'active');
//        }

        $dataQuery->whereIn('status', ['active']);

        // For consumer only completed videos and audio should be displayed
        // and all active photos and polls content type
        if ($visiblity == 'consumer') {
            $dataQuery->where(function ($query) {
                $query->orWhereIn('video_status', ['completed']);
                $query->orWhereIn('audio_status', ['completed']);
                $query->orWhereIn('type', ['photo', 'poll']);
            });
        }

        $pinToTopQuery->whereIn('status', ['active', 'uploaded']);

//        } else {
////            $dataQuery->where('status', '=', 'active');
//            $dataQuery->where(function ($query) {
//                $query->orWhereIn('status', ['active']);
//                $query->orWhereIn('video_status', ['uploaded', 'submitted']);
//                $query->orWhereIn('audio_status', ['uploaded', 'submitted']);
//            });
//
//            $pinToTopQuery->where('status', '=', 'active');
//        }

//        if ($parent_id == '') {
//            $data = $dataQuery->orderBy('published_at', 'desc')->paginate($perpage);
////            $data = $dataQuery->orderBy('created_at', 'desc')->paginate($perpage);
//        } else {
////            $data = $dataQuery->orderBy('created_at', 'asc')->paginate($perpage);
//            $data = $dataQuery->orderBy('published_at', 'desc')->paginate($perpage);
//        }

        $data = $dataQuery->orderBy('published_at', 'desc')->paginate($perpage);
        $pin_to_top_data = $pinToTopQuery->orderBy('_id', 'desc')->get();
        if($pin_to_top_data) {
            $pin_to_top_data = $pin_to_top_data->toArray();
        }


        if ($data) {
            $data = $data->toArray();
        }

        $contentArr = [];
        $contents = (isset($data['data'])) ? $data['data'] : [];

        //Manage Pin to top content
        if ($page == 1) {
            foreach ($pin_to_top_data as $content) {
                if (isset($content['published_at'])) {
                    $content['human_readable_created_date'] = Carbon\Carbon::parse($content['published_at'])->format('F j\\, Y h:i A');
                    $content['date_diff_for_human'] = Carbon\Carbon::parse($content['published_at'])->diffForHumans();
                } else {
                    $content['human_readable_created_date'] = Carbon\Carbon::parse($content['updated_at'])->format('F j\\, Y h:i A');
                    $content['date_diff_for_human'] = Carbon\Carbon::parse($content['updated_at'])->diffForHumans();
                }

                if(!empty($content['pin_to_top']) && $content['pin_to_top'] == true){
                    $content['pin_to_top'] = true;
                }else{
                    $content['pin_to_top'] = false;
                }
                $content['caption'] = (isset($content['caption']) && $content['caption'] != '') ? trim($content['caption']) : '';
                $content['feeling_activity'] = (isset($content['feeling_activity']) && $content['feeling_activity'] != '') ? trim($content['feeling_activity']) : '';
//                array_push($contentArr, $content);
            }
        }

        //Manage Normal content
        foreach ($contents as $content) {


            $content_language = $content['contentlanguages'];

            $is_found = false;


            foreach ($content_language as $key => $content_lang) {

                if(in_array($requested_lang_id, $content_lang)) {

                    $content['caption'] = (isset($content_lang['caption']) && $content_lang['caption'] != '') ? trim($content_lang['caption']) : '';
                    $content['name'] = (isset($content_lang['name']) && $content_lang['name'] != '') ? trim($content_lang['name']) : '';
                    $content['slug'] = (isset($content_lang['slug']) && $content_lang['slug'] != '') ? trim($content_lang['slug']) : '';

                    $is_found = true;
                }
            }

            if($is_found == false) {

                foreach ($content_language as $key => $content_lang) {

                    if(in_array($default_lang_id, $content_lang)) {

                        $content['caption'] = (isset($content_lang['caption']) && $content_lang['caption'] != '') ? trim($content_lang['caption']) : '';
                        $content['name'] = (isset($content_lang['name']) && $content_lang['name'] != '') ? trim($content_lang['name']) : '';
                        $content['slug'] = (isset($content_lang['slug']) && $content_lang['slug'] != '') ? trim($content_lang['slug']) : '';
                    }
                }
            }

            unset($content['contentlanguages']);

            $content_parent_id = (isset($content['parent_id']) && $content['parent_id'] != '') ? trim($content['parent_id']) : '';
            if (isset($content['published_at'])) {
                $content['human_readable_created_date'] = Carbon\Carbon::parse($content['published_at'])->format('F j\\, Y h:i A');
                $content['date_diff_for_human'] = Carbon\Carbon::parse($content['published_at'])->diffForHumans();
            } else {
                $content['human_readable_created_date'] = Carbon\Carbon::parse($content['updated_at'])->format('F j\\, Y h:i A');
                $content['date_diff_for_human'] = Carbon\Carbon::parse($content['updated_at'])->diffForHumans();
            }
            // $content['caption'] = (isset($content['caption']) && $content['caption'] != '') ? trim($content['caption']) : '';
            $content['feeling_activity'] = (isset($content['feeling_activity']) && $content['feeling_activity'] != '') ? trim($content['feeling_activity']) : '';

            $content_stats = isset($content['stats']) ? $content['stats'] : [];
            if ($content_stats && isset($content_stats['likes'])) {
                $content_stats['likes'] = intval(str_replace("-", "", $content_stats['likes']));
            }

            if ($content_stats && isset($content_stats['comments'])) {
                $content_stats['comments'] = intval(str_replace("-", "", $content_stats['comments']));
            }

            if ($content_stats && isset($content_stats['shares'])) {
                $content_stats['shares'] = intval(str_replace("-", "", $content_stats['shares']));
            }

            if ($content_stats && isset($content_stats['childrens'])) {
                $content_stats['childrens'] = intval(str_replace("-", "", $content_stats['childrens']));
            }

            $content['stats'] = $content_stats;


            // need to modified and store and update method whenever parent content is created or update commercial_type must update child contents if exist
//            if ($content_parent_id == '') {
//                //first level
//                $commercial_type = (isset($content['commercial_type']) && $content['commercial_type'] != '') ? trim(strtolower($content['commercial_type'])) : 'free';
//            } else {
//                //second level
//                $parentContent = \App\Models\Content::where('_id', '=', $content_parent_id)->first();
//                $commercial_type = ($parentContent && isset($parentContent['commercial_type']) && $parentContent['commercial_type'] != '') ? trim(strtolower($parentContent['commercial_type'])) : 'free';
//            }
//

            if(!empty($content['pin_to_top']) && $content['pin_to_top'] == true){
                $content['pin_to_top'] = true;
            }else{
                $content['pin_to_top'] = false;
            }

            $content['commercial_type']         = (!empty($content['commercial_type'])) ? trim(strtolower($content['commercial_type'])) : 'free';
            $content['duration']                   = (isset($content['duration']) && $content['duration'] != '') ? trim($content['duration']) : '';
            $content['coins']                   = (isset($content['coins']) && $content['coins'] != '') ? trim($content['coins']) : '0';
            $content['partial_play_duration']   = (isset($content['partial_play_duration']) && $content['partial_play_duration'] != '') ? trim($content['partial_play_duration']) : '0';
            $content['is_commentbox_enable']    = (!empty($content['is_commentbox_enable'])) ? trim(strtolower($content['is_commentbox_enable'])) : 'true';
//            $content['is_partial_play']         = (!empty($content['is_partial_play'])) ? trim(strtolower($content['is_partial_play'])) : 'false';


//            if (isset($content['photo'])) {
//                if ($content['photo'] == '' || !array_key_exists("cover", $content['photo'])) {
//                    $content['photo'] = ['cover' => '', 'thumb' => ''];
//                }
//            }

            $content['photo']           = (!empty($content['photo'])) ? $content['photo'] : null;
            $content['photo_portrait']  = (!empty($content['photo_portrait'])) ? $content['photo_portrait'] : null;

            $type = $content['type'];
            $content_id = $content['_id'];


            if($type == 'video') {
                // Generate Content Languages
                $content_langs = [];
                if(isset($content['vod_job_data'])) {
                    $content_langs = $this->generateContentLanguages($content['vod_job_data']);
                }

                $content['content_languages'] = $content_langs;
            }


            if ($type == 'poll') {
                $polloptions = \App\Models\Polloption::where('content_id', $content_id)->where('status', 'active')->get(['name']);

                $polloptions = !empty($polloptions) ? $polloptions->toArray() : [];

                $pollstats = !empty($content['pollstats']) ? $content['pollstats'] : [];

                $output = [];
                foreach ($polloptions as $key => $val) {
                    $option_id = $val['_id'];

                    $stat = array_where($pollstats, function ($key, $value) use ($option_id) {
                        if ($value['id'] == $option_id) {
                            return $value;
                        }
                    });

                    if ($stat) {
                        $option = head($stat);
                    } else {
                        $option['id'] = $option_id;
                        $option['label'] = $val['name'];
                        $option['votes'] = 0;
                        $option['votes_in_percentage'] = 0;
                    }

                    array_push($output, $option);
                }
                $content['pollstats'] = $output;

                $expired_at = !empty($content['expired_at']) ? $content['expired_at'] : '';

                if (!empty($expired_at)) {
                    $expire = strtotime($expired_at);
                    $today = strtotime("today midnight");

                    $content['is_expired'] = 'false';

                    $content['total_votes'] = !empty($content['total_votes']) ? intval($content['total_votes']) : 0;
//                    $content['human_readable_expired_at'] = Carbon\Carbon::parse($expired_at)->format('F j\\, Y H:i');
                    $content['expired_at'] = rtrim(str_replace("from now", "", Carbon\Carbon::parse($expired_at)->diffForHumans()));

                    if ($today >= $expire) {
                        $content['is_expired'] = 'true';
//                        unset($content['human_readable_expired_at']);
                        unset($content['expired_at']);
                    }
                }

            } else {
                unset($content['total_votes']);
            }

            $genres = [];


            if(!empty($content['genres'])) {
                $genres = \App\Models\Genre::whereIn('_id', $content['genres'])->where('status', 'active')->get(['name']);
                if($genres) {
                    $content['genres'] = $genres->toArray();
                }
            }
            else {
                unset($content['genres']);
            }



            $comments = \App\Models\Comment::with(array('customer' => function ($query) {$query->select('_id', 'first_name', 'last_name', 'picture', 'photo');}))->where('entity_id', $content_id)
                ->project(['_id' => 0])// Removing _id
                ->take(3)
                ->orderBy('created_at', 'desc')
                ->get();

//            $content['latestcomments'] = !empty($comments) ? array_column($comments->toArray(), 'comment') : [];

            $latestcommentsArr         = [];
            if(!empty($comments)){

                foreach($comments->toArray() as $comment ){

                    $commentArr                                 =    $comment;
                    $commentArr['human_readable_created_date']  =   !empty($comment['created_at']) ? Carbon\Carbon::parse($comment['created_at'])->format('F j\\, Y h:i A') : null;
                    $commentArr['date_diff_for_human']          =   !empty($comment['created_at']) ? Carbon\Carbon::parse($comment['created_at'])->diffForHumans() : null;

                    array_push($latestcommentsArr, $commentArr);
                }
            }
            $content['latestcomments'] = $latestcommentsArr;


//            if (!empty($content['mediaconvert_data'])) {
//                unset($content['mediaconvert_data']);
//            }
//            if (!empty($content['transcode_meta_data'])) {
//                unset($content['transcode_meta_data']);
//            }
//            if (!empty($content['content_types'])) {
//                unset($content['content_types']);
//            }
//

            // If content type is video
            // And Video has casts then find cast details
            if($content['type'] == 'video' && isset($content['casts']) && !empty($content['casts'])) {
                $content_casts = [];
                $content_casts = \App\Models\Cast::whereIn('_id', $content['casts'])->where('status', 'active')->get(['first_name', 'last_name', 'photo']);
                if($content_casts) {
                    $content['casts'] = $content_casts->toArray();
                }
            }
            else {
                unset($content['casts']);
            }

            $unwanted_keys = [
                'mediaconvert_data', 'transcode_meta_data', 'content_types', 'vod_job_data', 'video_status',  'aod_job_data', 'audio_status', 'likes', 'comments' , 'trailer',
                'is_test_enable', 'pin_to_top', 'flags', 'url_rewrite','platform','s3_video','s3_photo','has_photo','has_video', 'subtitles'
            ];

            foreach ($unwanted_keys as $unwanted_key){

                if (!empty($content[$unwanted_key])) {
                    unset($content[$unwanted_key]);
                }

            }

            $force_inactive   =  (!empty($contentObj['force_inactive']) && $contentObj['force_inactive'] == 'true') ? 'true' : 'false';


            if($force_inactive == 'false'){
                array_push($contentArr, $content);
            }


        }

        $responeData = [];
        $responeData['list'] = $contentArr;
        $responeData['paginate_data']['total'] = (isset($data['total'])) ? $data['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($data['current_page'])) ? $data['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($data['last_page'])) ? $data['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($data['from'])) ? $data['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($data['to'])) ? $data['to'] : 0;

        // Add Parent Content Info
        if($parent_id) {
            $parent_content = $this->getParentContentInfo($parent_id);
            if($parent_content) {
                $responeData['parent_content'] = $parent_content;
            }
        }

        // Add Bucket Meta Data Info
        $responeData['bucket_meta_data'] = [];
        $bucket_meta_data = $this->getBucketMetaInfo($bucket_id);
        if($bucket_meta_data) {
            $responeData['bucket_meta_data'] = $bucket_meta_data;
        }


//        $bucket = \App\Models\Bucket::find($bucket_id, []);
//        $responeData['bucket'] = $bucket;

//        $bucket                                         = \App\Models\Bucket::find($bucket_id, []);
//        $responeData['bucket']                          = ($bucket) ? $bucket->toArray() : [];


        return $responeData;

    }

    public function getBucketContents($requestData)
    {

        $data = $requestData;
        $visiblity = (isset($data['visiblity'])) ? trim($data['visiblity']) : 'customer';
        $bucket_id = (isset($data['bucket_id'])) ? trim($data['bucket_id']) : '';
        $parent_id = (isset($data['parent_id'])) ? trim($data['parent_id']) : '';
        $source = (isset($data['source'])) ? array_map('trim', $data['source']) : [];
        $perpage = (isset($data['perpage'])) ? intval($data['perpage']) : 15;
        $page = (isset($data['page'])) ? intval($data['page']) : 1;


        $dataQuery = \App\Models\Content::where('pin_to_top', '!=', true)->where('bucket_id', $bucket_id);
        $pinToTopQuery = \App\Models\Content::where('pin_to_top', true)->where('bucket_id', $bucket_id);

        if (!empty($source) && count($source) > 0) {
            $dataQuery->whereIn('source', $source);
        }

        if ($parent_id == '') {
            $dataQuery->where('level', 1);
            $pinToTopQuery->where('level', 1);
        } else {
            $dataQuery->where('parent_id', $parent_id)->where('level', 2);
            $pinToTopQuery->where('parent_id', $parent_id)->where('level', 2);
        }

        if ($visiblity == 'producer') {
            $dataQuery->whereIn('status', ['active', 'uploaded']);
            $pinToTopQuery->whereIn('status', ['active', 'uploaded']);
        } else {
            $dataQuery->where('status', '=', 'active');
            $pinToTopQuery->where('status', '=', 'active');
        }

        if ($parent_id == '') {
            $data = $dataQuery->orderBy('created_at', 'desc')->paginate($perpage);
        } else {
            $data = $dataQuery->orderBy('created_at', 'asc')->paginate($perpage);
        }

        $pin_to_top_data = $pinToTopQuery->orderBy('_id', 'desc')->get();
        if($pin_to_top_data) {
            $pin_to_top_data = $pin_to_top_data->toArray();
        }


        if ($data) {
            $data = $data->toArray();
        }

        $contentArr = [];

        $contents = (isset($data['data'])) ? $data['data'] : [];
        $like_content_ids = [];
        $purchase_content_ids = [];

        if (Request::header('Authorization')) {
            $customer = $this->jwtauth->customerFromToken();
            $customer_id = $customer['_id'];
            $content_ids = array_pluck($data['data'], '_id');
            $like_content_ids = \App\Models\Like::where('customer_id', '=', $customer_id)->where('entity', 'content')->where("status", "active")->whereIn('entity_id', $content_ids)->lists('entity_id');
            if($like_content_ids) {
                $like_content_ids = $like_content_ids->toArray();
            }

            $content_parent_ids = array_pluck($data['data'], 'parent_id');
            $all_purchase_content_ids = array_merge($content_ids, $content_parent_ids);
            $purchase_content_ids = \App\Models\Purchase::where('customer_id', '=', $customer_id)->where('entity', 'contents')->whereIn('entity_id', $all_purchase_content_ids)->lists('entity_id');
            if($purchase_content_ids) {
                $purchase_content_ids = $purchase_content_ids->toArray();
            }
        }


//        var_dump($purchase_content_ids);exit;

        //Manage Pin to top content
        if ($page == 1) {

            foreach ($pin_to_top_data as $content) {
                $content_id = $content['_id'];
                $content['is_like'] = (in_array($content_id, $like_content_ids)) ? true : false;
                if (isset($content['published_at'])) {
                    $content['human_readable_created_date'] = Carbon\Carbon::parse($content['published_at'])->format('F j\\, Y h:i A');
                    $content['date_diff_for_human'] = Carbon\Carbon::parse($content['published_at'])->diffForHumans();
                } else {
                    $content['human_readable_created_date'] = Carbon\Carbon::parse($content['created_at'])->format('F j\\, Y h:i A');
                    $content['date_diff_for_human'] = Carbon\Carbon::parse($content['created_at'])->diffForHumans();
                }

                $content['caption'] = (isset($content['caption']) && $content['caption'] != '') ? trim($content['caption']) : '';
                $content['feeling_activity'] = (isset($content['feeling_activity']) && $content['feeling_activity'] != '') ? trim($content['feeling_activity']) : '';
                array_push($contentArr, $content);
            }
        }

        //Manage Normal content
        foreach ($contents as $content) {

            $content_id = $content['_id'];
            $content_parent_id = (isset($content['parent_id']) && $content['parent_id'] != '') ? trim($content['parent_id']) : '';
            $content_coins = (isset($content['coins']) && $content['coins'] != '') ? trim($content['coins']) : 0;
            $content['is_like'] = (in_array($content_id, $like_content_ids)) ? true : false;
            if (isset($content['published_at'])) {
                $content['human_readable_created_date'] = Carbon\Carbon::parse($content['published_at'])->format('F j\\, Y h:i A');
                $content['date_diff_for_human'] = Carbon\Carbon::parse($content['published_at'])->diffForHumans();
            } else {
                $content['human_readable_created_date'] = Carbon\Carbon::parse($content['created_at'])->format('F j\\, Y h:i A');
                $content['date_diff_for_human'] = Carbon\Carbon::parse($content['created_at'])->diffForHumans();
            }
            $content['caption'] = (isset($content['caption']) && $content['caption'] != '') ? trim($content['caption']) : '';
            $content['feeling_activity'] = (isset($content['feeling_activity']) && $content['feeling_activity'] != '') ? trim($content['feeling_activity']) : '';

            $content_stats = $content['stats'];
            if ($content_stats && isset($content_stats['likes'])) {
                $content_stats['likes'] = intval(str_replace("-", "", $content_stats['likes']));
            }

            if ($content_stats && isset($content_stats['comments'])) {
                $content_stats['comments'] = intval(str_replace("-", "", $content_stats['comments']));
            }

            if ($content_stats && isset($content_stats['shares'])) {
                $content_stats['shares'] = intval(str_replace("-", "", $content_stats['shares']));
            }

            if ($content_stats && isset($content_stats['childrens'])) {
                $content_stats['childrens'] = intval(str_replace("-", "", $content_stats['childrens']));
            }

            $content['stats'] = $content_stats;

            if ($content_parent_id == '') {
                //first level
                $commercial_type = (isset($content['commercial_type']) && $content['commercial_type'] != '') ? trim(strtolower($content['commercial_type'])) : 'free';
            } else {
                //second level
                $parentContent = \App\Models\Content::where('_id', '=', $content_parent_id)->first();
                $commercial_type = ($parentContent && isset($parentContent['commercial_type']) && $parentContent['commercial_type'] != '') ? trim(strtolower($parentContent['commercial_type'])) : 'free';
            }
            $content['commercial_type'] = $commercial_type;


            $locked = false;  // its for free content
            $coins = 0;      // its for free content
            if ($commercial_type == 'paid') {

                $locked = (in_array($content_id, $purchase_content_ids) || in_array($content_parent_id, $purchase_content_ids)) ? false : true;
                $coins = ($content_parent_id == '') ? intval($content['coins']) : 0;
            } elseif ($commercial_type == 'partial_paid') {
                $locked = ($content_parent_id == '' || in_array($content_id, $purchase_content_ids) || $content_coins == 0) ? false : true;
                $coins = ($content_parent_id != '' && isset($content['coins'])) ? intval($content['coins']) : 0;
            }

            $content['locked'] = $locked;
            $content['coins'] = $coins;

            if (isset($content['photo'])) {
                if ($content['photo'] == '' || !array_key_exists("cover", $content['photo'])) {
                    $content['photo'] = ['cover' => '', 'thumb' => ''];
                }
            }
            array_push($contentArr, $content);
        }
        $responeData = [];
        $responeData['list'] = $contentArr;
        $responeData['paginate_data']['total'] = (isset($data['total'])) ? $data['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($data['current_page'])) ? $data['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($data['last_page'])) ? $data['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($data['from'])) ? $data['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($data['to'])) ? $data['to'] : 0;

        $bucket = \App\Models\Bucket::find($bucket_id, []);
        $responeData['bucket'] = $bucket;

        return $responeData;
    }

    public function store($postData)
    {
        $data = $postData;

//        if (isset($data['bucket_code']) && $data['bucket_code'] != "" && $data['bucket_code'] == 'polls') {
        if (!empty($data['content_types']) && $data['content_types'] == 'polls') {
            array_set($data, 'type', 'poll');
        } else {
            array_set($data, 'type', 'photo');
        }

        $pin_to_top = false;

        if (!isset($data['name'])) {
            array_set($data, 'name', '');
        }

        if (!isset($data['ordering'])) {
            array_set($data, 'ordering', 0);
        }

        if (!isset($data['status'])) {
            array_set($data, 'status', 'active');
        }

        if (!isset($data['source'])) {
            array_set($data, 'source', 'custom');
        }

        if (empty($data['is_album'])) {
            array_set($data, 'is_album', 'false');
        }

        if (empty($data['commercial_type'])) {
            array_set($data, 'commercial_type', 'free');
        }

        if (empty($data['18_plus_age_content'])) {
            array_set($data, '18_plus_age_content', 'true');
        }

        if (isset($data['pin_to_top'])) {
            $pin_to_top = (empty($data['pin_to_top']) || $data['pin_to_top'] == 'false' || $data['pin_to_top'] == false) ? false : true;
        }

        if (isset($data['coins'])) {
            array_set($data, 'coins', intval($data['coins']));
        }

        array_set($data, 'published_at', new \MongoDB\BSON\UTCDateTime(strtotime(date('Y-m-d H:i:s')) * 1000));

        $expired_at = (isset($data['expired_at']) && $data['expired_at'] != '') ? hyphen_date($data['expired_at']) : '';
        $expired_at = $expired_at . ' 23:59:00';

        if (!empty($expired_at)) {
            $expired_at = new \MongoDB\BSON\UTCDateTime(strtotime($expired_at) * 1000);
        } else {
            $expired_at = Carbon::now();
        }

        array_set($data, 'expired_at', $expired_at);
        array_set($data, 'pin_to_top', $pin_to_top);
        array_set($data, 'level', intval($data['level']));
        array_set($data, 'stats', Config::get('app.stats'));
        array_set($data, 'likes', ['internal' => 0]);
        array_set($data, 'comments', ['internal' => 0]);
        array_set($data, 'flags', Config::get('app.content.flags'));
        array_set($data, 'pollstats', null);
        array_set($data, 'total_votes', 0);

        if (isset($data['parent_id']) && $data['parent_id'] == '' || intval($data['level']) == 1) {
            unset($data['parent_id']);
        }

        $data = array_except($data, ['photos', 'videos', 'player_type', 'embed_code', 'content_type', 'send_notification', 'test', 'bucket_code']);

        if (isset($postData['player_type']) && $postData['player_type'] != '') {

//            $is_video = false;
            $player_type = trim(strtolower($postData['player_type']));

            $cover = (isset($postData['photo']) && $postData['photo'] != '') ? $postData['photo'] : Config::get('kraken.contents_photo');

            $embed_code = '';
            $url = '';

            if ($player_type == 'internal') {
                if (isset($postData['vod_job_data'])) {
//                    $is_video = true;
                    array_set($data, 'status', 'inactive');
                    $video_bucket_url = Config::get('app.base_raw_video_url');
                    $url = (!empty($postData['vod_job_data']) && !empty($postData['vod_job_data']['object_name'])) ? $video_bucket_url . $postData['vod_job_data']['object_name'] : '';
                }
            } elseif ($player_type == 'youtube') {
                if (isset($postData['embed_code']) && $postData['embed_code'] != "" || isset($postData['url']) && $postData['url'] != "") {
//                    $is_video = true;
                    $embed_code = (isset($postData['embed_code']) && $postData['embed_code'] != "") ? trim($postData['embed_code']) : "";
                    $url = (isset($postData['url']) && $postData['url'] != "") ? trim($postData['url']) : "";
                }
            }

//            $video = [
//                'player_type' => $player_type,
//                'embed_code' => $embed_code,
//                'url' => $url
//            ];

//            if ($is_video) {
            $video = [
                'player_type' => $player_type,
                'embed_code' => $embed_code,
                'url' => $url
            ];
//            }

            if (!empty($cover)) {
                $video = array_merge($video, $cover);
            }

            array_set($data, 'video', $video);
            if (isset($postData['photo']) && $data['photo']) {
                unset($data['photo']);
            }

            array_set($data, 'type', 'video');
        }

//        if (!empty($postData['webview_url']) && $postData['content_types'] == 'webviews' && !empty($postData['content_types'])) {
//            array_set($data, 'type', 'webview');
//        }

        if (isset($data['type']) && in_array($data['type'], ['photo', 'poll', 'webview']) && isset($data['photo'])) {
            array_set($data, 'photo', $data['photo']);
        }

//        if (isset($postData['audio']) && $postData['bucket_code'] == 'audios') {
        if (isset($postData['audio']) || !empty($postData['duration'])) {

            $cover = (isset($postData['photo']) && $postData['photo'] != '') ? $postData['photo'] : Config::get('kraken.contents_photo');
            $audio_bucket_url = Config::get('app.base_raw_audio_url');
            $audio = [
                'url' => (!empty($postData['aod_job_data']) && !empty($postData['aod_job_data']['object_name'])) ? $audio_bucket_url . $postData['aod_job_data']['object_name'] : ''
            ];

            if (isset($postData['photo']) && isset($data['photo'])) {
                unset($data['photo']);
            }

            if (!empty($cover)) {
                $audio = array_merge($audio, $cover);
            }
            array_set($data, 'type', 'audio');
            array_set($data, 'audio', $audio);
            array_set($data, 'status', 'inactive');

        }

        $recodset = new \App\Models\Content($data);
        $recodset->save();

        artist_last_visited($recodset['artist_id']); //Update Last seen of Artist

        try {
            if ($recodset && isset($recodset->parent_id) && $recodset->parent_id != '') {
                $this->updateChildrenCount($recodset->parent_id);
            }
        } catch (Exception $e) {
            $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            Log::info('UpdateChildrenCount : Error ', $message);
        }

        return $recodset;
    }

    public function validateContentSlugData($title, $content_id, $bucket_id) {

        $content_lang_data_cnt = \App\Models\Contentlang::active()->where('content_id', $content_id)->where('bucket_id', $bucket_id)->where('slug', $title)->count();

        if($content_lang_data_cnt > 0) {
            $slug_data = $title. (intval($content_lang_data_cnt) + 1);
        } else {
            $slug_data = $title;
        }

        $slug_data = preg_replace('#[ -]+#', '-', $slug_data);

        return str_slug($slug_data);
    }


    /**
     * Store Content Data In Database
     *
     *
     * @param   array   $postData
     *
     * @return  Object  Content Model
     *
     * @author
     * @since
     */
    public function storeContent($postData)
    {
        $postData = array_except($postData, ['send_notification', 'bucket_code']);

        $recodset = new \App\Models\Content($postData);
        $recodset->save();

        $slug = $this->validateContentSlugData($postData['name'], $recodset->_id, $recodset->bucket_id);

        $langData['content_id']         = $recodset->_id;
        $langData['bucket_id']          = $recodset->bucket_id;
        $langData['language_id']        = $postData['language_id'];
        $langData['name']               = trim($postData['name']);
        $langData['caption']            = trim($postData['caption']);
        $langData['status']             = 'active'; // Status will be alway active
        $langData['is_default_language']= true;
        $langData['slug']               = $slug;

        $recodsetlang = new \App\Models\Contentlang($langData);
        $recodsetlang->save();

        artist_last_visited($recodset['artist_id']); //Update Last seen of Artist

        try {
            if ($recodset && isset($recodset->parent_id) && $recodset->parent_id != '') {
                $this->updateChildrenCount($recodset->parent_id); //Update Child Count
            }
        }
        catch (Exception $e) {
            $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            Log::info('UpdateChildrenCount : Error ', $message);
        }

        return $recodset;
    }


    public function contentUpdate($requestData, $id)
    {
        $artist_id  = isset($requestData['artist_id']) ? $requestData['artist_id'] : '';
        $contentObj = \App\Models\Content::where('_id', $id)->first();
        $contentObj->update($requestData);

        $langdata = [];
        if(isset($requestData['language_id']) && !empty($requestData)) {

            // Find Default Language Id

            $default_lang_id = $this->getArtistLanguageId($artist_id);

            $slug = $this->validateContentSlugData(trim($requestData['name']), $contentObj->_id, $contentObj->bucket_id);

            $bucket_id  = $contentObj->bucket_id;
            $content_id = $contentObj->_id;
            $language_id= $requestData['language_id'];
            $name       = trim($requestData['name']);
            $caption    = trim($requestData['caption']);
            $status     = $requestData['status'];
            $slug       = $slug;

            $contentlangdata = \App\Models\Contentlang::active()->where('bucket_id', $bucket_id)->where('content_id', $content_id)->where('language_id', $language_id)->first();

            $langdata = [
                'content_id'    => $content_id,
                'bucket_id'     => $bucket_id,
                'language_id'   => $language_id,
                'name'          => trim($name),
                'caption'       => trim($caption),
                'status'        => 'active', // Status will be alway active
                'slug'          => $slug,
            ];

            if($language_id == $default_lang_id) {
                $langdata['is_default_language'] = true;
            }

            if($contentlangdata !== null) {
                $contentlangObj = \App\Models\Contentlang::where('_id', $contentlangdata->_id)->first();
                $contentlangObj->update($langdata);
            }
            else {
                $contentlangObj = new \App\Models\Contentlang($langdata);
                $contentlangObj->save();
            }
        }

        $this->syncCasts($requestData, $contentObj);
    }


    public function update($postData, $id)
    {

        $data = $postData;

        $recodset = \App\Models\Content::findOrFail($id);


        if($recodset && !empty($recodset['level'])){
            $data['level'] =     intval($recodset['level']);
        }



//        if (isset($data['bucket_code']) && $data['bucket_code'] != "" && $data['bucket_code'] == 'polls') {
//        if (!empty($data['content_types']) && $data['content_types'] == 'polls') {
//            array_set($data, 'type', 'poll');
//        } else {
//            array_set($data, 'type', 'photo');
//        }

//        array_set($data, 'level', intval($data['level']));
        if (!isset($data['ordering'])) {
            array_set($data, 'ordering', 0);
        }

//        if (empty($data['name'])) {
//            array_set($data, 'name', is_null('name'));
//        }

//        if (!isset($data['is_album'])) {
//            array_set($data, 'is_album', 'false');
//        }

        if (empty($data['18_plus_age_content'])) {
            array_set($data, '18_plus_age_content', 'true');
        }

        if (isset($data['parent_id']) && $data['parent_id'] == '') {
            unset($data['parent_id']);
        }

        if (isset($data['parent_id']) && $data['parent_id'] == '' || intval($data['level']) == 1) {
            unset($data['parent_id']);
        }

        $data = array_except($data, ['content_id', 'photos', 'videos', 'player_type', 'embed_code', 'content_type', 'send_notification', 'test', 'bucket_code']);

        if (!empty($postData['player_type'])) {
//            $is_video = false;
            $player_type    =   trim(strtolower($postData['player_type']));
            $cover          =   (!empty($postData['photo'])) ? $postData['photo'] : [];
            $video          =   ($recodset && !empty($recodset['video']) ) ? $recodset['video'] : [];
            if ($player_type == 'internal') {
                if (isset($postData['vod_job_data'])) {
                    array_set($data, 'status', 'inactive');
                    $video_bucket_url = Config::get('app.base_raw_video_url');
                    $url = (!empty($postData['vod_job_data']) && !empty($postData['vod_job_data']['object_name'])) ? $video_bucket_url . $postData['vod_job_data']['object_name'] : '';
                    $video['player_type']   =   'internal';
                    $video['url']           =   $url;
                }

            } elseif ($player_type == 'youtube') {
                if (isset($postData['embed_code']) && $postData['embed_code'] != "" || isset($postData['url']) && $postData['url'] != "") {
                    $embed_code = (isset($postData['embed_code']) && $postData['embed_code'] != "") ? trim($postData['embed_code']) : "";
                    $url = (isset($postData['url']) && $postData['url'] != "") ? trim($postData['url']) : "";
                    $video['player_type']   =   'internal';
                    $video['embed_code']    =   $embed_code;
                    $video['url']           =   $url;
                }
            }
            if (!empty($cover)) {
                $video = array_merge($video, $cover);
            }
            array_set($data, 'video', $video);
            if (isset($postData['photo']) && $data['photo']) {
                unset($data['photo']);
            }
            array_set($data, 'type', 'video');
        }

        if (isset($data['coins'])) {
            array_set($data, 'coins', intval($data['coins']));
        }

//        if (isset($data['pin_to_top'])) {
        if ($data['level'] == 1) {
            $pin_to_top = (empty($data['pin_to_top']) || $data['pin_to_top'] == 'false' || $data['pin_to_top'] == false) ? false : true;
            array_set($data, 'pin_to_top', $pin_to_top);
        }

        if (!empty($postData['audio']) || !empty($postData['duration'])) {

            if (isset($postData['photo']) && $postData['photo'] != '') {
                $cover = $postData['photo'];
                unset($data['photo']);
            } else {
                $cover = Config::get('kraken.contents_photo');
            }

            $audio['url'] = (!empty($recodset['audio']) && !empty($recodset['audio']['url'])) ? $recodset['audio']['url'] : '';

            if (!empty($postData['aod_job_data'])) {
                $audio_bucket_url = Config::get('app.base_raw_audio_url');
                $audio['url'] = (!empty($postData['aod_job_data']['object_name'])) ? $audio_bucket_url . $postData['aod_job_data']['object_name'] : '';
                array_set($data, 'status', 'inactive');
            }

            if (!empty($cover)) {
                $audio = array_merge($audio, $cover);
                array_set($data, 'audio', $audio);
            }

            array_set($data, 'type', 'audio');
        }

//        if (!empty($postData['webview_url']) && $postData['content_types'] == 'webviews' && !empty($postData['content_types'])) {
//            array_set($data, 'type', 'webview');
//        }

        if (isset($data['type']) && in_array($data['type'], ['photo', 'poll', 'webview']) && isset($data['photo'])) {
            array_set($data, 'photo', $data['photo']);
        }


//        print_pretty($data);exit;
        $recodset->update($data);

        if ($recodset['type'] == 'photo') {
            $recodset->unset(['audio_status', 'aod_job_data', 'audio', 'video', 'vod_job_data', 'video_status']);
        }

        if ($recodset['type'] == 'audio') {
            $recodset->unset(['video', 'vod_job_data', 'video_status', 'photo']);
        }

        if ($recodset['type'] == 'video') {
            $recodset->unset(['audio_status', 'aod_job_data', 'audio', 'photo']);
        }

        artist_last_visited($recodset['artist_id']); //Update Last seen of Artist

        // Manage Commercial type For Children

        try {
            if ($recodset && isset($recodset->parent_id) && $recodset->parent_id != '' && isset($recodset['commercial_type']) && $recodset['commercial_type'] != '') {
                $parent_id = trim($recodset->parent_id);
                $commercial_type = (isset($recodset['commercial_type']) && $recodset['commercial_type'] != '') ? trim(strtolower($recodset['commercial_type'])) : 'free';
            }
        } catch (Exception $e) {
            $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            Log::info('Manage Commercial type For Children : Error ', $message);
        }

        // Manage Children Count
        try {
            if ($recodset && isset($recodset->parent_id) && $recodset->parent_id != '') {
                $this->updateChildrenCount($recodset->parent_id);
            }
        } catch (Exception $e) {
            $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            Log::info('UpdateChildrenCount : Error ', $message);
        }

        //Manage Video Image Forcefully
        try {
            if ($recodset && isset($recodset->_id)) {
                $this->updateVideoImageForcefully($recodset->_id, $postData);
            }
        } catch (Exception $e) {
            $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            Log::info('updateVideoImageForcefully : Error ', $message);
        }


        //Manage Forcefully incative
        try {

            if (isset($data['status']) && $data['status'] == 'inactive') {

                $force_incative_data  = ['force_inactive' => 'true', 'status' => 'inactive'];

                if ($recodset && isset($recodset->video_status)) {
                    $force_incative_data['video_status'] = 'completed';
                }

                if ($recodset && isset($recodset->audio_status)) {
                    $force_incative_data['audio_status'] = 'completed';
                }

                $recodset->update($force_incative_data);

            }

        } catch (Exception $e) {
            $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            Log::info('Manage Forcefully incative : Error ', $message);
        }



        return $recodset;
    }



    public function updateVideoImageForcefully($content_id, $postData = [])
    {

        $content        =   \App\Models\Content::where('_id', '=', $content_id)->first();
        $cover          =   (!empty($postData['photo']) && !empty($postData['photo']['cover'])) ? $postData['photo']['cover'] : "";
        $player_type    =   (!empty($postData['player_type'])) ? trim(strtolower($postData['player_type'])) : '';

        if ($content && isset($content['video']) && $cover != "" && $player_type != 'internal') {
            $video = $content['video'];
            $video['cover'] = $cover;
            $contentData = ['video' => $video];

            Log::info('updateVideoImageForcefully  ', $contentData);
            $content->update($contentData);
        }

    }

    public function updateChildrenCount($parent_id)
    {
        $content = \App\Models\Content::where('_id', '=', $parent_id)->first();
        if ($content) {
            $stats = $content->stats;
            $children_counts = \App\Models\Content::where('status', '=', 'active')
                ->where('parent_id', '=', $parent_id)
                ->count();
            $stats['childrens'] = intval($children_counts);
            $contentData = ['stats' => $stats, 'is_album' => "true"];
            $content->update($contentData);
        }
    }

    public function getLikes($requestData)
    {

        $data = $requestData;
        $content_id = (isset($data['content_id'])) ? trim($data['content_id']) : '';
        $perpage = (isset($data['perpage'])) ? intval($data['perpage']) : 15;

        $data = \App\Models\Like::where('status', '=', 'active')
            ->where('entity', 'content')
            ->where('entity_id', $content_id)
            ->with('customer')->orderBy('_id', 'desc')->paginate($perpage);

        if ($data) {
            $data = $data->toArray();
        }

        $likeArr = [];
        $likes = (isset($data['data'])) ? $data['data'] : [];
        foreach ($likes as $like) {

            $like['customer'] = (isset($like['customer'])) ? array_only($like['customer'], ['_id', 'first_name', 'last_name', 'picture', 'photo']) : null;
            $like['human_readable_created_date'] = Carbon\Carbon::parse($like['created_at'])->format('F j\\, Y h:i A');
            $like['date_diff_for_human'] = Carbon\Carbon::parse($like['created_at'])->diffForHumans();

            $like = array_except($like, ['entity', 'customer_id', 'customer_name']);
            array_push($likeArr, $like);
        }


        $responeData = [];
        $responeData['list'] = $likeArr;
        $responeData['paginate_data']['total'] = (isset($data['total'])) ? $data['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($data['current_page'])) ? $data['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($data['last_page'])) ? $data['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($data['from'])) ? $data['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($data['to'])) ? $data['to'] : 0;

        return $responeData;
    }

    public function saveShare($requestData) {

        $data = $requestData;

        $content_id = (isset($data['content_id'])) ? trim($data['content_id']) : '';
        $artist_id = (isset($data['artist_id'])) ? trim($data['artist_id']) : '';
        $share = (isset($data['share'])) ? intval($data['share']) : true;

        if (!empty($data['customer_id']) && $data['customer_id'] != '') {
            $customer_id = (isset($data['customer_id'])) ? trim($data['customer_id']) : ''; // If function calling from redisdb service
        } else {
            $customer_id = $this->jwtauth->customerFromToken()['_id'];                      // If function calling from content service
        }

        $shareObj = [];

        //if content
        $content = \App\Models\Content::where('_id', '=', $content_id)->first();

        if ($content) {

            //insert update customer share
            $shareData = [
                'entity' => 'content',
                'entity_id' => $content_id,
                'artist_id' => $artist_id,
                'customer_id' => $customer_id,
                'status' => 'active',
            ];

            if (!empty($data['created_at']) && $data['created_at'] != '') {
                array_set($shareData, 'created_at', $data['created_at']);
            }

            $shareExsits = \App\Models\Share::where('customer_id', '=', $customer_id)->where('entity', 'content')->where('entity_id', $content_id)->first();

            if ($shareExsits) {
                $shareObj = $shareExsits->update($shareData);
                $shareObj = $shareExsits;
            } else {
                $shareObj = new \App\Models\Share($shareData);
                $shareObj->save();
            }

            $share_status = $shareObj['status'];
            $shares = (isset($content->shares)) ? $content->shares : ['internal' => 0];
            $stats = $content->stats;

            if ($shares && isset($shares['internal'])) {
                $content_share = intval(str_replace("-", "", $shares['internal']));

                if ($shareExsits) {
                    $shares['internal'] = $content_share;
                } else {
                    $shares['internal'] = $content_share + 1;
                }
            }

            if ($stats && isset($stats['shares'])) {
                $content_share = intval(str_replace("-", "", $stats['shares']));

                if($shareExsits) {
                    $stats['shares'] = $content_share;
                } else {
                    $stats['shares'] = $content_share + 1;
                }
            } else {
                $stats['shares'] = 1;
            }

            $contentData = ['shares' => $shares, 'stats' => $stats];

            $content->update($contentData);

        }

        return $shareObj;
    }

    public function saveView($requestData) {

        $data = $requestData;

        $content_id = (isset($data['content_id'])) ? trim($data['content_id']) : '';
        $artist_id = (isset($data['artist_id'])) ? trim($data['artist_id']) : '';
        $view = (isset($data['view'])) ? intval($data['view']) : true;

        if (!empty($data['customer_id']) && $data['customer_id'] != '') {
            $customer_id = (isset($data['customer_id'])) ? trim($data['customer_id']) : ''; // If function calling from redisdb service
        } else {
            $customer_id = $this->jwtauth->customerFromToken()['_id'];                      // If function calling from content service
        }

        $viewObj = [];

        //if content
        $content = \App\Models\Content::where('_id', '=', $content_id)->first();

        if ($content) {

            //insert update customer view
            $viewData = [
                'entity' => 'content',
                'entity_id' => $content_id,
                'artist_id' => $artist_id,
                'customer_id' => $customer_id,
                'status' => 'active',
            ];

            if (!empty($data['created_at']) && $data['created_at'] != '') {
                array_set($viewData, 'created_at', $data['created_at']);
            }

            $viewExsits = \App\Models\View::where('customer_id', '=', $customer_id)->where('entity', 'content')->where('entity_id', $content_id)->first();

            if ($viewExsits) {
                $viewObj = $viewExsits->update($viewData);
                $viewObj = $viewExsits;
            } else {
                $viewObj = new \App\Models\View($viewData);
                $viewObj->save();
            }

            $view_status = $viewObj['status'];
            $views = (isset($content->views)) ? $content->views : ['internal' => 0];
            $stats = $content->stats;

            if ($views && isset($views['internal'])) {
                $content_view = intval(str_replace("-", "", $views['internal']));

                if($viewExsits) {
                    $views['internal'] = $content_view;
                } else {
                    $views['internal'] = $content_view + 1;
                }
            }

            if ($stats && isset($stats['views'])) {
                $content_view = intval(str_replace("-", "", $stats['views']));

                if($viewExsits) {
                    $stats['views'] = $content_view;
                } else {
                    $stats['views'] = $content_view + 1;
                }
            } else {
                $stats['views'] = 1;
            }

            $contentData = ['views' => $views, 'stats' => $stats];

            $content->update($contentData);

        }

        return $viewObj;
    }

    public function saveLike($requestData)
    {

        $data = $requestData;
        $content_id = (isset($data['content_id'])) ? trim($data['content_id']) : '';
        $artist_id = (isset($data['artist_id'])) ? trim($data['artist_id']) : '';
        $like = (isset($data['like'])) ? intval($data['like']) : true;

        if (!empty($data['customer_id']) && $data['customer_id'] != '') {
            $customer_id = (isset($data['customer_id'])) ? trim($data['customer_id']) : ''; // If function calling from redisdb service
        } else {
            $customer_id = $this->jwtauth->customerFromToken()['_id'];                      // If function calling from content service
        }

        $likeObj = [];

        //if content
        $content = \App\Models\Content::where('_id', '=', $content_id)->first();

        if ($content) {

            //insert update customer like
            $likeData = [
                'entity' => 'content',
                'entity_id' => $content_id,
                'artist_id' => $artist_id,
                'customer_id' => $customer_id,
                'status' => 'active',
            ];

            if (!empty($data['created_at']) && $data['created_at'] != '') {
                array_set($likeData, 'created_at', $data['created_at']);
            }

            $likeExsits = \App\Models\Like::where('customer_id', '=', $customer_id)->where('entity', 'content')->where('entity_id', $content_id)->first();

            if ($likeExsits) {
                $likeObj = $likeExsits->update($likeData);
                $likeObj = $likeExsits;
            } else {
                $likeObj = new \App\Models\Like($likeData);
                $likeObj->save();
            }

            $like_status = $likeObj['status'];
            $likes = (isset($content->likes)) ? $content->likes : ['internal' => 0];
            $stats = $content->stats;

            if ($likes && isset($likes['internal'])) {
                // $likes['internal'] = ($like_status == 'active' ) ? intval($likes['internal']) + 1 : intval($likes['internal']) - 1;
                $content_like = intval(str_replace("-", "", $likes['internal']));
                $likes['internal'] = $content_like + 1;
            }

            if ($stats && isset($stats['likes'])) {
                // $stats['likes'] = ($like_status == 'active' ) ? intval($stats['likes']) + 1 : intval($stats['likes']) - 1;
                $content_like = intval(str_replace("-", "", $stats['likes']));
                $stats['likes'] = $content_like + 1;
            }

            $contentData = ['likes' => $likes, 'stats' => $stats];

            $content->update($contentData);

        }

        return $likeObj;
    }

    public function getCommentsOld($requestData)
    {
        $data = $requestData;
        $content_id = (isset($data['content_id'])) ? trim($data['content_id']) : '';
        $perpage = (isset($data['perpage'])) ? intval($data['perpage']) : 15;
        $results = \App\Models\Comment::where('status', '=', 'active')->where('entity', 'content')->where('entity_id', $content_id)->where("level", 1)->with('customer')->with('producer')->orderBy('_id', 'desc')->paginate($perpage)->toArray();


        $commentArr = [];
        $comments = (isset($results['data'])) ? $results['data'] : [];

        foreach ($comments as $comment) {

            $commented_by = (isset($comment['commented_by']) && $comment['commented_by'] != "") ? $comment['commented_by'] : "customer";

            $customer = [
                '_id' => '',
                'first_name' => '',
                'last_name' => '',
                'picture' => 'https://storage.googleapis.com/arms-razrmedia/customers/profile/default.png'
            ];

            if (isset($comment['customer']) && $commented_by == "customer") {
                $customer = array_only($comment['customer'], ['_id', 'first_name', 'last_name', 'picture', 'photo']);
            }

            if (isset($comment['producer']) && $commented_by == "artist") {
                $customer = array_only($comment['producer'], ['_id', 'first_name', 'last_name', 'picture', 'photo']);
            }

            $comment['customer'] = $customer;


            if (isset($comment['is_social']) && $comment['is_social'] == 1 && isset($comment['social_user_name'])) {

                $customer = ['_id' => '', 'first_name' => $comment['social_user_name'], 'last_name' => '', 'picture' => 'https://storage.googleapis.com/arms-razrmedia/customers/profile/default.png'];
                $comment['customer'] = $customer;
            }

            $comment['human_readable_created_date'] = Carbon\Carbon::parse($comment['created_at'])->format('F j\\, Y h:i A');
            $comment['date_diff_for_human'] = Carbon\Carbon::parse($comment['created_at'])->diffForHumans();
            $comment['commented_by'] = (isset($comment['commented_by']) && $comment['commented_by'] != "") ? $comment['commented_by'] : "customer";

            $repliesParams = ['content_id' => $content_id, 'comment_id' => $comment['_id'], 'per_page' => 100, 'page' => 1];
            $comment['replies'] = $this->getCommentReplies($repliesParams);


            $comment = array_except($comment, ['entity', 'customer_id', 'customer_name', 'social_user_name', 'social_comment_id', 'producer']);
            array_push($commentArr, $comment);
        }


        $responeData = [];
        $responeData['list'] = $commentArr;
        $responeData['paginate_data']['total'] = (isset($results['total'])) ? $results['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($results['per_page'])) ? $results['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($results['current_page'])) ? $results['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($results['last_page'])) ? $results['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($results['from'])) ? $results['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($results['to'])) ? $results['to'] : 0;

        return $responeData;
    }

    public function getCommentRepliesOld($params)
    {

        $data = $params;
        $content_id = (isset($params['content_id'])) ? trim($params['content_id']) : '';
        $comment_id = (isset($params['comment_id'])) ? trim($params['comment_id']) : '';
        $perpage = 100;
        $responeData = NULL;
        $repliesArr = [];

//        echo "content_id - $content_id,  comment_id - $comment_id , perpage - $perpage";

        if ($content_id != "" && $comment_id != "") {

            $results = \App\Models\Comment::where('status', '=', 'active')
                ->where('entity', 'content')
                ->where('parent_id', $comment_id)
                ->where("level", 2)
                ->with('customer')
                ->with('producer')
                ->orderBy('_id', 'asc')
                ->get()->toArray();

            $replies = (isset($results)) ? $results : [];

            foreach ($replies as $item) {

                $replied_by = (isset($item['replied_by']) && $item['replied_by'] != "") ? $item['replied_by'] : "customer";

                $customer = [
                    '_id' => '',
                    'first_name' => '',
                    'last_name' => '',
                    'picture' => 'https://storage.googleapis.com/arms-razrmedia/customers/profile/default.png'
                ];

                if (isset($item['customer']) && $replied_by == "customer") {
                    $customer = array_only($item['customer'], ['_id', 'first_name', 'last_name', 'picture', 'photo']);
                }

                if (isset($item['producer']) && $replied_by == "artist") {
                    $customer = array_only($item['producer'], ['_id', 'first_name', 'last_name', 'picture', 'photo']);
                }

                $item['customer'] = $customer;
                $item['human_readable_created_date'] = Carbon\Carbon::parse($item['created_at'])->format('F j\\, Y h:i A');
                $item['date_diff_for_human'] = Carbon\Carbon::parse($item['created_at'])->diffForHumans();
                $item = array_except($item, ['entity', 'customer_id', 'customer_name', 'producer']);
                $item['replied_by'] = $replied_by;
                array_push($repliesArr, $item);
            }


            if (count($repliesArr) > 0) {
                $responeData['list'] = $repliesArr;
                $responeData['paginate_data']['total'] = count($repliesArr);
                $responeData['paginate_data']['per_page'] = $perpage;
                $responeData['paginate_data']['current_page'] = 1;
                $responeData['paginate_data']['last_page'] = 1;
                $responeData['paginate_data']['from'] = 1;
                $responeData['paginate_data']['to'] = $perpage;
            }
        }

        return $responeData;
    }

    public function getComments($requestData)
    {
        $data = $requestData;
        $content_id = (isset($data['content_id'])) ? trim($data['content_id']) : '';
        $perpage = (isset($data['perpage'])) ? intval($data['perpage']) : 15;

        $results = \App\Models\Comment::with(array('producer' => function ($query) {
            $query->select('_id', 'first_name', 'last_name', 'picture', 'photo');
        }))->with(array('customer' => function ($query) {
            $query->select('_id', 'first_name', 'last_name', 'picture', 'photo');
        }))->where('status', '=', 'active')
            ->where('entity', 'content')
            ->where('entity_id', $content_id)
            ->where("level", 1)
            ->orderBy('created_at', 'desc')
            ->paginate($perpage);

        $results->getCollection()->transform(function ($comments, $key) use ($content_id) {

            $comment = $comments;

            $commented_by = (isset($comment['commented_by']) && $comment['commented_by'] != "") ? $comment['commented_by'] : "customer";

            $customer = [
                '_id' => '',
                'first_name' => '',
                'last_name' => '',
                'picture' => 'https://storage.googleapis.com/arms-razrmedia/customers/profile/default.png',
                'photo' => Config::get('kraken.customerprofile_photo')
            ];

            if (isset($comment['customer']) && $commented_by == "customer") {
                $customer = $comment['customer'];
            }

            if (isset($comment['producer']) && $commented_by == "artist") {
                $customer = $comment['producer'];
            }

            $comment['user'] = $customer;

            if (isset($comment['is_social']) && $comment['is_social'] == 1 && isset($comment['social_user_name'])) {

//                $customer = [
//                    '_id' => '',
//                    'first_name' => $comment['social_user_name'],
//                    'last_name' => '',
//                    'picture' => 'https://storage.googleapis.com/arms-razrmedia/customers/profile/default.png'
//                ];

                $customer = [
                    '_id' => '',
                    'first_name' => $comment['social_user_name'],
                    'last_name' => '',
                    'picture' => $comment['user']['picture'],
                    'photo' => $comment['user']['photo']
                ];

                $comment['user'] = $customer;
            }

            $comment['human_readable_created_date'] = Carbon\Carbon::parse($comment['created_at'])->format('F j\\, Y h:i A');
            $comment['date_diff_for_human'] = Carbon\Carbon::parse($comment['created_at'])->diffForHumans();
            $comment['commented_by'] = (isset($comment['commented_by']) && $comment['commented_by'] != "") ? $comment['commented_by'] : "customer";
            $comment['type'] = !empty($comment['type']) ? $comment['type'] : 'text';

            $comment = array_except($comment, ['customer', 'entity', 'customer_id', 'customer_name', 'social_user_name', 'social_comment_id', 'producer']);

            return $comment;
        });

        $results = $results->toArray();


        $responeData = [];
        $responeData['list'] = !empty($results['data']) ? $results['data'] : [];
        $responeData['paginate_data']['total'] = (isset($results['total'])) ? $results['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($results['per_page'])) ? $results['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($results['current_page'])) ? $results['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($results['last_page'])) ? $results['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($results['from'])) ? $results['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($results['to'])) ? $results['to'] : 0;

        return $responeData;
    }

    public function getCommentReplies($params)
    {
        $comment_id = (isset($params['comment_id'])) ? trim($params['comment_id']) : '';
        $perpage = 10;
        $responeData = NULL;
        $repliesArr = [];

        $results = \App\Models\Comment::
        with(array('customer' => function ($query) {
            $query->select('_id', 'first_name', 'last_name', 'picture', 'photo');
        }))->with(array('producer' => function ($query) {
            $query->select('_id', 'first_name', 'last_name', 'picture', 'photo');
        }))->where('status', '=', 'active')
            ->where('entity', 'content')
            ->where('parent_id', $comment_id)
            ->where("level", 2)
            ->orderBy('created_at', 'desc')
            ->paginate($perpage);

        $results->getCollection()->transform(function ($comments, $key) use ($comment_id) {

            $item = $comments;

            $replied_by = (isset($item['replied_by']) && $item['replied_by'] != "") ? $item['replied_by'] : "customer";

            $customer = [
                '_id' => '',
                'first_name' => '',
                'last_name' => '',
                'picture' =>  Config::get('app.cf_base_url') . '/default/customersprofile/default.png',
                'photo' => Config::get('kraken.customerprofile_photo')
            ];

            if (isset($item['customer']) && $replied_by == "customer") {
                $customer = $item['customer'];
            }

            if (isset($item['producer']) && $replied_by == "artist") {
                $customer = $item['producer'];
            }

            $item['user'] = $customer;
            $item['human_readable_created_date'] = Carbon\Carbon::parse($item['created_at'])->format('F j\\, Y h:i A');
            $item['date_diff_for_human'] = Carbon\Carbon::parse($item['created_at'])->diffForHumans();
            $item = array_except($item, ['entity', 'customer_id', 'customer_name', 'producer', 'customer']);
            $item['replied_by'] = $replied_by;
            $item['type'] = !empty($item['type']) ? $item['type'] : 'text';

            return $item;
        });

        $results = $results->toArray();

        $responeData = [];
        $responeData['list'] = !empty($results['data']) ? $results['data'] : [];
        $responeData['paginate_data']['total'] = (isset($results['total'])) ? $results['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($results['per_page'])) ? $results['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($results['current_page'])) ? $results['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($results['last_page'])) ? $results['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($results['from'])) ? $results['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($results['to'])) ? $results['to'] : 0;

        return $responeData;
    }

    public function saveComment($requestData)
    {
        $data = $requestData;
        $content_id = (isset($data['content_id'])) ? trim($data['content_id']) : '';
        $comment = (isset($data['comment'])) ? trim($data['comment']) : '';
        $commented_by = (isset($data['commented_by'])) ? trim($data['commented_by']) : 'customer';
        $type = (!empty($data['type'])) ? trim($data['type']) : 'text';

        if (!empty($data['customer_id']) && $data['customer_id'] != '') {
            $customer_id = (isset($data['customer_id'])) ? trim($data['customer_id']) : ''; // If function calling from redisdb service
        } else {
            $customer_id = $this->jwtauth->customerFromToken()['_id'];                    // If function calling from content service
        }

        $content = [];

        //if content
        $content = \App\Models\Content::where('_id', '=', $content_id)->first();
        $commentObj = [];

        if ($content) {

            $artist_id = $content['artist_id'];
            $bucket_id = $content['bucket_id'];


            $commentData = [
                'entity' => 'content',
                'artist_id' => $artist_id,
                'bucket_id' => $bucket_id,
                'entity_id' => $content_id,
                'customer_id' => $customer_id,
                'parent_id' => '',
                'comment' => $comment,
                'commented_by' => $commented_by,
                'status' => 'active',
                'level' => 1,
                'type' => $type,
                'stats' => ['replies' => 0],
            ];

            if (!empty($data['created_at']) && $data['created_at'] != '') {
                array_set($commentData, 'created_at', $data['created_at']);
            }


            //insert

            $commentObj = new \App\Models\Comment($commentData);
            $commentObj->save();
            $commentObj->root_id = $commentObj->_id;
            $commentObj->save();


            $comments = (isset($content->comments)) ? $content->comments : ['internal' => 0];
            $stats = $content->stats;

            if ($comments && isset($comments['internal'])) {
                $comments['internal'] = intval($comments['internal']) + 1;
            }

            if ($stats && isset($stats['comments'])) {
                $stats['comments'] = intval($stats['comments']) + 1;
            }

            $contentData = ['comments' => $comments, 'stats' => $stats];

            $content->update($contentData);
        }

        return $commentObj;
    }

    public function replyOnComment($requestData)
    {
        $data = $requestData;
        $content_id = (isset($data['content_id'])) ? trim($data['content_id']) : '';
        $parent_id = (isset($data['parent_id'])) ? trim($data['parent_id']) : '';
        $comment = (isset($data['comment'])) ? trim($data['comment']) : '';
        $replied_by = (isset($data['replied_by'])) ? trim($data['replied_by']) : 'customer';
        $customer = $this->jwtauth->customerFromToken();
        $type = (!empty($data['type'])) ? trim($data['type']) : 'text';

        $customer_id = $customer['_id'];
        $content = [];

        //if content
        $content = \App\Models\Content::where('_id', '=', $content_id)->first();
        $parentComment = \App\Models\Comment::where('_id', '=', $parent_id)->first();
        $commentObj = [];

        if ($content && $parentComment) {

            $artist_id = $content['artist_id'];
            $bucket_id = $content['bucket_id'];

            $root_id = $parentComment->root_id;
            $level = intval($parentComment->level) + 1;

            //insert
            $commentData = [
                'is_social' => 0,
                'artist_id' => $artist_id,
                'bucket_id' => $bucket_id,
                'entity' => 'content',
                'entity_id' => $content_id,
                'customer_id' => $customer_id,
                'parent_id' => $parent_id,
                'root_id' => $root_id,
                'comment' => $comment,
                'replied_by' => $replied_by,
                'status' => 'active',
                'level' => $level,
                'type' => $type
            ];

            $commentObj = new \App\Models\Comment($commentData);
            $commentObj->save();

            if (isset($parentComment->stats)) {
                $stats = $parentComment->stats;
            } else {
                $stats = ['stats'];
                $stats = ['replies' => 0];
            }

            if ($stats && isset($stats['replies'])) {
                $stats['replies'] = intval($stats['replies']) + 1;
            } else {
                $stats['replies'] = 1;
            }

            $contentData = ['stats' => $stats];

            $parentComment->update($contentData);
        }

        return $commentObj;
    }

    public function pinToTop($postData)
    {
        $data = $postData;
        $bucket_id = (isset($data['bucket_id'])) ? trim($data['bucket_id']) : '';
        $content_id = (isset($data['content_id'])) ? trim($data['content_id']) : '';
        $content = [];

        if ($bucket_id != '' && $content_id != '') {
            $reset_pin_to_top = \App\Models\Content::where('bucket_id', $bucket_id)->update(['pin_to_top' => false], ['upsert' => true]);
            $content = \App\Models\Content::where('bucket_id', $bucket_id)->where('_id', $content_id)->first();
            $content->update(['pin_to_top' => true]);
        }

        return $content;
    }

    public function purchaseContent($requestData)
    {

        $data = $requestData;
        $content_id = (isset($data['content_id'])) ? trim($data['content_id']) : "";
        $artist_id = (isset($data['artist_id']) && $data['artist_id'] != '') ? trim($data['artist_id']) : "";
        $platform = (isset($data['platform']) && $data['platform'] != '') ? trim($data['platform']) : "";
        $coins = 0;

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

        $customer = $this->jwtauth->customerFromToken();
        $customer_id = $customer['_id'];
        $purchaseObj = [];

        $customerInfoExists = \App\Models\Customer::where('_id', '=', $customer_id)->first();

        $purchaseObj = \App\Models\Purchase::where('customer_id', '=', $customer_id)->where('artist_id', '=', $artist_id)->where('entity', 'contents')->where('entity_id', $content_id)->first();

        if ($customerInfoExists != NULL && !$purchaseObj && $coins > 0) {

            $coins_before_purchase = (isset($customerInfoExists->coins)) ? $customerInfoExists->coins : 0;

            $coins_after_purchase = (isset($customerInfoExists->coins)) ? $customerInfoExists->coins - $coins : 0;


            if ($coins_after_purchase < 0) {
                $coins_after_purchase = 0;
            }

            $data = ['coins' => $coins_after_purchase];

            $customerArtistObj = $customerInfoExists->update($data);

            $this->redisdb->saveCustomerCoins($customer_id, $coins_after_purchase);

            //insert customer purchase content data
            $purchaseData = [
                'entity' => 'contents',
                'entity_id' => $content_id,
                'customer_id' => $customer_id,
                'artist_id' => $artist_id,
                'platform' => $platform,
                'coins' => $coins,
                'total_quantity' => 1,
                'coin_of_one' => intval($coins), //coin_of_one_content
                'coins_before_purchase' => $coins_before_purchase,
                'coins_after_purchase' => $coins_after_purchase,
                'passbook_applied' => true
            ];

            $purchaseObj = new \App\Models\Purchase($purchaseData);
            $purchaseObj->save();


            // will used later by using queues
//            $activityData = [
//                'name' => 'purchase_content',
//                'customer_id' => $customer_id,
//                'artist_id' => $artist_id,
//                'purchase_id' => $purchaseObj['_id'],
//                'entity' => 'contents',
//                'entity_id' => $content_id,
//                'total_quantity' => 1,
//                'total_coins' => intval($coins),
//                'coin_of_one' => intval($coins), //coin_of_one_content
//                'platform' => $platform,
//                'coins_before_purchase' => $coins_before_purchase,
//                'coins_after_purchase' => $coins_after_purchase,
//
//            ];
//            $activityObj = new \App\Models\CustomerActivity($activityData);
//            $activityObj->save();


//            $purchaseObj['coins_before_purchase']   =   $coins_before_purchase;
//            $purchaseObj['coins_after_purchase']    =   $coins_after_purchase;
//            $purchaseObj['available_coins']         =   $coins_after_purchase;

        }

        return $purchaseObj;
    }

    public function getHistoryPurchaseContents($requestData)
    {
        $data = $requestData;
        $perpage = ($requestData['perpage'] == NULL) ? \Config::get('app.perpage') : intval($requestData['perpage']);
        $artist_id = (isset($data['artist_id']) && $data['artist_id'] != '') ? trim($data['artist_id']) : "";
        $platform = (isset($data['platform']) && $data['platform'] != '') ? trim($data['platform']) : "";
        $customer = $this->jwtauth->customerFromToken();
        $customer_id = $customer['_id'];
        $customerInfo = \App\Models\Customer::where('_id', '=', $customer_id)->first();
        $query = \App\Models\Purchase::with('content')->where('artist_id', '=', $artist_id)->where('entity', 'contents')->where('customer_id', $customer_id);

        $data = $query->orderBy('_id', 'desc')->paginate($perpage);
        if($data) {
            $data = $data->toArray();
        }
        $purchases = (isset($data['data'])) ? $data['data'] : [];
        $responeData = [];
        $responeData['list'] = $purchases;
        $responeData['paginate_data']['total'] = (isset($data['total'])) ? $data['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($data['current_page'])) ? $data['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($data['last_page'])) ? $data['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($data['from'])) ? $data['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($data['to'])) ? $data['to'] : 0;

        return $responeData;
    }

    public function fetchResults($requestData)
    {
        $perpage = (isset($requestData['perpage']) && $requestData['perpage'] != '') ? $requestData['perpage'] : '';

        $query = \App\Models\Pollresult::with([
            'content' => function ($q) {
                $q->select('name');
            },
            'polloption' => function ($q) {
                $q->select('name');
            },
            'customer' => function ($q) {
                $q->select('first_name', 'last_name');
            },
        ])->where('content_id', '=', $requestData['content_id'])->paginate($perpage)->toArray();

        return $query;
    }

    public function submitPollResult($postData)
    {
        $data = array_except($postData, ['_token', 'cover']);

        $content_id = $data['content_id'];
        $customer_id = $data['cust_id'];

        $exists = \App\Models\Pollresult::where('content_id', '=', $content_id)->where('cust_id', '=', $customer_id)->first();

        if (empty($exists)) {
            $result = new Pollresult($data);
            $result->save();
        } else {
            $result = \App\Models\Pollresult::where('_id', $exists['_id'])->update($data);
        }

        $pollContent = \App\Models\Pollresult::with('polloption')->where('content_id', $content_id)->get();
        $total_votes = count($pollContent);

        foreach ($pollContent as $key => $val) {
            $option_id = $val['option_id'];
            $pollresults[$key]['id'] = !empty($val['polloption']['_id']) ? $val['polloption']['_id'] : '';
            $pollresults[$key]['label'] = !empty($val['polloption']['name']) ? $val['polloption']['name'] : $val['polloption']['photo'];
            $pollresults[$key]['votes'] = \App\Models\Pollresult::where('option_id', $option_id)->count();
            $pollresults[$key]['votes_in_percentage'] = ($pollresults[$key]['votes'] / $total_votes) * 100;
        }
        $pollresults = !empty($pollresults) ? $pollresults : [];
        $poll_stat = array_unique($pollresults, SORT_REGULAR);

        $contentData = ['pollstats' => $poll_stat, 'total_votes' => intval($total_votes)];

        $contents = \App\Models\Content::where('_id', '=', $content_id)->update($contentData);


        return $result;


    }

    public function fetchList($requestData)
    {
        $results = [];
        $perpage = (isset($requestData['perpage']) && $requestData['perpage'] != '') ? $requestData['perpage'] : '';
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $query = \App\Models\Content::with('artist')->orderBy('name');

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        $results['contents'] = $query->paginate($perpage)->toArray();

        foreach ($results['contents']['data'] as $key => $val) {
            $res[$key]['artist_id'] = $val['artist']['_id'];
            $res[$key]['artist_fname'] = $val['artist']['first_name'];
            $res[$key]['artist_lname'] = $val['artist']['last_name'];
            $res[$key]['content_id'] = $val['_id'];
            $res[$key]['content_name'] = @$val['name'];
            if (!empty($val['photo']['cover'])) {
                $res[$key]['cover'] = $val['photo']['cover'];
            }
            if (!empty($val['pollstats'])) {
                $res[$key]['pollstats'] = @$val['pollstats'];
            }
            $res[$key]['status'] = $val['status'];

            $subquery = \App\Models\PollOption::with('content')->where('content_id', $val['_id'])->paginate($perpage)->toArray();

            $i = 0;
            foreach ($subquery['data'] as $subKey => $subVal) {
                if ($subVal['content_id'] == $val['_id']) {
                    $res[$key]['options'][$i]['option_id'] = $subVal['_id'];
                    $res[$key]['options'][$i]['option_name'] = $subVal['name'];
                    if (!empty($subVal['photo']['thumb'])) {
                        $res[$key]['options'][$i]['option_cover'] = $subVal['photo']['thumb'];
                    }
                    if (!empty($subVal['slug'])) {
                        $res[$key]['options'][$i]['slug'] = $subVal['slug'];
                    }
                    $res[$key]['options'][$i]['status'] = $subVal['status'];
                    $i++;
                }
            }
        }

        $responeData = [];
        $responeData['list'] = $res;

        $responeData['paginate_data']['total'] = (isset($results['contents']['total'])) ? $results['contents']['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($results['contents']['per_page'])) ? $results['contents']['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($results['contents']['current_page'])) ? $results['contents']['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($results['contents']['last_page'])) ? $results['contents']['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($results['contents']['from'])) ? $results['contents']['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($results['contents']['to'])) ? $results['contents']['to'] : 0;
        return $responeData;
    }

    public function getDashboardStickersStatsQuery($requestData)
    {
        $artist_id = !empty($requestData['artist_id']) ? $requestData['artist_id'] : '';
        $created_at = ((isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '');
        $created_at_end = ((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '');

        $query = \App\Models\Gift::orderBy('created_at', 'desc');

        if (!empty($artist_id)) {
            $query->whereIn('artists', [$artist_id]);
        }

        if ($created_at != '') {
            $query->where('created_at', '>', mongodb_start_date($created_at));
        }

        if ($created_at_end != '') {
            $query->where('created_at', '<', mongodb_end_date($created_at_end));
        }

        $results = $query->get();

        return $results;
    }

    /**
     * Sync Content Casts
     *
     *
     * @return  array
     *
     * @author Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since 2019-05-13
     */
    public function syncCasts($postData, $content)
    {
        \App\Models\Cast::whereIn('contents', [$content->_id])->pull('contents', $content->_id);

        if (!empty($postData['casts'])) {
            $casts = array_map('trim', $postData['casts']);

            $content->casts()->sync(array());

            foreach ($casts as $key => $value) {
                $content->casts()->attach($value);
            }
        }
    }

    /**
     * Returns Parent Content Info
     *
     * @param   string $content_id
     * @return  array
     *
     * @author Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since 2019-05-31
     */
    public function getParentContentInfo($content_id)
    {
        $ret = null;
        $content = \App\Models\Content::select('_id', 'name', 'caption', 'slug', 'coins', 'type')->where('_id', '=', $content_id)->first();
        if($content) {
            $ret = $content->toArray();
        }

        return $ret;
    }

    /**
     * Returns Content Info
     *
     * @param   string $content_id
     * @return  array
     *
     * @author Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since 2019-05-31
     */
    public function show($content_id, $language = null)
    {
        $ret = null;
        $bucket  = [];

        $language_ids = [];
        $default_lang_id = "";
        $requested_lang_id = "";

        $content_info = $this->model->where('_id', $content_id)->first();
        $artist_id = $content_info->artist_id;

        $language_code2 = $language;
        $config_language_data = $this->artistservice->getConfigLanguages($artist_id);

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

        $content = $this->model->where('_id', $content_id)->with(['contentlanguages' => function($query) use($language_ids) { $query->whereIn('language_id', $language_ids)->project(['content_id' => 1, 'bucket_id' => 1, 'language_id' => 1, 'name' => 1, 'slug' => 1, 'caption' => 1]); }])->first();

        if($content) {

            $content    = $content->toArray();
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
            if(!empty($content['genres'])) {
                $genres = \App\Models\Genre::whereIn('_id', $content['genres'])->where('status', 'active')->get(['name']);
                $content['genres'] = (!empty($genres)) ? $genres->toArray() : [];
            }

            if($content['type'] == 'video' && isset($content['casts']) && !empty($content['casts'])) {
                $content_casts = [];
                $content_casts = \App\Models\Cast::whereIn('_id', $content['casts'])->where('status', 'active')->get(['first_name', 'last_name', 'photo']);
                if($content_casts) {
                    $content_casts = $content_casts->toArray();
                }
                $content['casts'] = $content_casts;
            }

            $ret['content'] = $content;

            if( isset($content->type) &&  ($content->type == 'video')) {
                // Generate Content Languages
                $content_langs = [];
                if(isset($content->vod_job_data)) {
                    $content_langs = $this->generateContentLanguages($content->vod_job_data);
                }
                $ret['content']['content_languages'] = $content_langs;
            }

            if(isset($content->parent_id)) {
                $parent_content = $this->getParentContentInfo($content->parent_id);
                if($parent_content) {
                    $ret['parent_content'] = $parent_content;
                }
            }

            // Bucket Detail
            if(isset($content['bucket_id'])) {
                $bucket = $this->getBucketInfo($content['bucket_id']);
            }

            $ret['bucket'] = $bucket;

        }

        return $ret;
    }

    /**
     * Returns Content Detail
     *
     * @param   string $content_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-24
     */
    public function detail($content_id, $remove_unwanted_keys = true, $language = 'en')
    {
        $ret = array();

        $language_ids = [];
        $default_lang_id = "";
        $requested_lang_id = "";

        $content_info = $this->model->where('_id', $content_id)->first();
        $artist_id = $content_info->artist_id;

        $language_code2 = $language;
        $config_language_data = $this->artistservice->getConfigLanguages($artist_id);

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

        $content_detail = $this->model->where('_id', $content_id)->with(['contentlanguages' => function($query) use($language_ids) { $query->whereIn('language_id', $language_ids)->project(['content_id' => 1, 'bucket_id' => 1, 'language_id' => 1, 'name' => 1, 'slug' => 1, 'caption' => 1]); }])->first();

        if($content_detail) {

            $content    = $content_detail->toArray();
            $content_language = $content['contentlanguages'];

            $title =  $slug = $caption = "";

            foreach($content_language as $lang_data) {

                if(in_array($requested_lang_id, $lang_data)) {

                    $content_detail['caption'] = (isset($lang_data['caption']) && $lang_data['caption'] != '') ? trim($lang_data['caption']) : '';
                    $content_detail['name'] = (isset($lang_data['name']) && $lang_data['name'] != '') ? trim($lang_data['name']) : '';
                    $content_detail['slug'] = (isset($lang_data['slug']) && $lang_data['slug'] != '') ? trim($lang_data['slug']) : '';

                    continue;

                } else {

                    if(in_array($default_lang_id, $lang_data)) {

                        $content_detail['caption'] = (isset($lang_data['caption']) && $lang_data['caption'] != '') ? trim($lang_data['caption']) : '';
                        $content_detail['name'] = (isset($lang_data['name']) && $lang_data['name'] != '') ? trim($lang_data['name']) : '';
                        $content_detail['slug'] = (isset($lang_data['slug']) && $lang_data['slug'] != '') ? trim($lang_data['slug']) : '';
                    }
                }

            }

            $genres = [];
            if(!empty($content_detail['genres'])) {
                $genres = \App\Models\Genre::whereIn('_id', $content_detail['genres'])->where('status', 'active')->get(['name']);
                $content_detail['genres'] = (!empty($genres)) ? $genres->toArray() : [];
            }
            else {
                //unset($content_detail['genres']);
            }

            unset($content_detail['contentlanguages']);

            if( isset($content_detail->type) &&  ($content_detail->type == 'video')) {
                // Generate Content Languages
                $content_langs = [];
                if(isset($content_detail->vod_job_data)) {
                    $content_langs = $this->generateContentLanguages($content_detail->vod_job_data);

                }
                $content_detail->content_languages = $content_langs;
            }

            if($content['type'] == 'video' && isset($content['casts']) && !empty($content['casts'])) {
                $content_casts = [];
                $content_casts = \App\Models\Cast::whereIn('_id', $content['casts'])->where('status', 'active')->get(['first_name', 'last_name', 'photo']);
                $content_detail->casts = (!empty($content_casts)) ? $content_casts->toArray() : [];
            }
            else {
                //unset($content_detail['casts']);
            }

            // Unset unwanted data
            if($remove_unwanted_keys) {
                foreach ($this->unwanted_keys as $key => $unwanted_key) {
                    if(isset($content_detail->$unwanted_key)) {
                        unset($content_detail->$unwanted_key);
                    }
                }
            }

            $ret = $content_detail;
        }

        return $ret;
    }


    /**
     * Returns Content related other content IDs
     *
     * @param   string $content_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-25
     */
    public function findRelatedContentIds($content_id, $limit = 5)
    {
        $ret = array();
        $bucket_id = '';

        $content_detail = $this->find($content_id);
        if($content_detail) {
            $bucket_id = $content_detail->bucket_id;
        }

        $contents = \App\Models\Content::where('_id', '!=', $content_id)->where('level', '=', 1)->where('status', 'active')->where('bucket_id', $bucket_id)->limit($limit)->get(['_id']);
        if($contents) {
            $ret = array_pluck($contents->toArray(), '_id');
        }

        return $ret;
    }

    /**
     * Returns Content Info
     *
     * @param   string $content_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-25
     */
    public function info($content_id, $other_contents_required = true, $children_required = true, $language = null)
    {
        $ret = array();
        $otherContents  = [];
        $children       = [];

        $content_detail = $this->detail($content_id, true, $language);
        if($content_detail) {
            $ret['content'] = $content_detail;

            // Find children content of content
            if($children_required) {
                $children_ids = $this->findContentChildrenIds($content_id);
                if($children_ids) {
                    foreach ($children_ids as $key => $children_id) {
                        $content_children = $this->detail($children_id, true, $language);
                        if($content_children) {
                            $children[] = $content_children;
                        }
                    }
                }

                $ret['children'] = $children;
            }

            // Find Other Content Detail
            if($other_contents_required) {
                $other_content_ids = $this->findRelatedContentIds($content_id);
                if($other_content_ids) {
                    foreach ($other_content_ids as $key => $other_content_id) {
                        $other_content = $this->detail($other_content_id, true, $language);
                        if($other_content) {
                            $otherContents[] = $other_content;
                        }
                    }
                }

                $ret['other_contents'] = $otherContents;
            }
        }

        return $ret;
    }

    /**
     * Check whether content slug is unique in bucket
     *
     * @param   string $slug
     * @param   string $bucket_id
     * @return  boolean
     *
     * @author Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since 2019-05-31
     */
    public function isSlugUniqueInBucket($slug, $bucket_id)
    {
        $ret = true;
        $content_query = \App\Models\Content::where('slug', $slug);

        $content_query->where('status', 'active');
        $content_query->where('bucket_id', $bucket_id);

        $content = $content_query->first();
        if($content) {
            $ret = false;

        }

        return $ret;
    }

    /**
     * Returns Bucket Meta Info
     *
     * @param   string $bucket_id
     * @return  array
     *
     * @author Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since 2019-06-07
     */
    public function getBucketMetaInfo($bucket_id)
    {
        $ret = null;
        $result = \App\Models\Bucket::select('meta')->where('_id', '=', $bucket_id)->first();
        if($result) {
            $result = $result->toArray();
            if(isset($result['meta'])){
                $ret = $result['meta'];
            }
        }

        return $ret;
    }

    /**
     * Returns Bucket Detail
     *
     * @param   string $bucket_id
     * @return  array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function getBucketInfo($bucket_id) {
        $select = array('name', 'slug', 'code');
        $except = array('updated_at', 'created_at');
        $ret = null;

        $result = \App\Models\Bucket::select($select)->where('_id', '=', $bucket_id)->first();
        if($result) {
            $ret = $result->toArray();
            $ret = array_except($ret, $except);
        }

        return $ret;
    }

    /**
     * Returns Content children contents
     *
     * @param   string $content_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-25
     */
    public function findContentChildrenIds($content_id, $limit ='')
    {
        $ret = array();
        $bucket_id = '';

        $content_detail = $this->find($content_id);
        if($content_detail) {
            $bucket_id = $content_detail->bucket_id;
        }

        $contents = \App\Models\Content::where('parent_id', $content_id)->where('status', 'active')->get(['_id']);
        if($contents) {
            $ret = array_pluck($contents->toArray(), '_id');
        }

        return $ret;
    }

    /**
     * Store Content Video File
     *
     * @param   string $id  Content Id
     * @return  array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-05
     */
    public function storeContentVideoFile($id, $data) {
        $ret = false;

        $recodset = $this->model->findOrFail($id);
        if($recodset) {
            $vod_job_data = isset($recodset->vod_job_data) ? $recodset->vod_job_data : [];
            if($vod_job_data) {
                // First check whether particular langauge data exists or not
                // IF NOT then add given data
                // IF EXISTS then update given data
                $lang           = $data['language'];
                $lang_default   = $data['is_default'];
                $vod_job_data_new = [];
                $is_new         = true;

                foreach ($vod_job_data as $key => $vod_job) {
                    if($lang == $vod_job['language']) {
                        $vod_job_data_new[] = $data;
                        $is_new  = false;
                    }
                    else {
                        if($lang_default) {
                            $vod_job['is_default'] = false;
                        }
                        $vod_job_data_new[] = $vod_job;
                    }
                }

                if($is_new) {
                    $vod_job_data_new[] = $data;
                }

                $recodset->vod_job_data = $vod_job_data_new;
            }
            else {
                // If vod_job_data is not set
                $recodset->vod_job_data = [$vod_job_data];
            }

            $ret = $recodset->save();
        }

        return $ret;
    }


    /**
     * Store Content Video File
     *
     * @param   string $id  Content Id
     * @return  array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-05
     */
    public function deleteContentVideoFile($id, $lang = '') {
        $ret = false;

        $recodset = $this->model->findOrFail($id);
        if($recodset) {
            $vod_job_data_new   = [];
            $vod_job_data       = isset($recodset->vod_job_data) ? $recodset->vod_job_data : [];
            $video              = isset($recodset->video) ? $recodset->video : null;
            if($vod_job_data) {
                // First check whether particular langauge data exists or not
                // IF EXISTS then delete given data
                foreach ($vod_job_data as $key => $vod_job) {
                    if(isset($vod_job['language']) && $vod_job['language'] == $lang) {
                        // Remove this data form recordset

                        if($video) {
                            if(isset($video[$lang . '_url'])) {
                                unset($video[$lang . '_url']);
                            }
                        }
                    }
                    else {
                        $vod_job_data_new[] = $vod_job;
                    }
                }

                $recodset->vod_job_data = $vod_job_data_new;
                $recodset->video = $video;
            }
            else {
                // If vod_job_data is not set
                $recodset->vod_job_data = [$vod_job_data];
            }

            $ret = $recodset->save();
        }

        return $ret;
    }

    /**
     * Find and update vod_job_data
     *
     * @param   array $vod_job_data
     * @param   array $vod_job_data collection
     * @return  array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function findAndUpdateVodJobDataCollection($vod_job_new,  $vod_job_data = []) {
        $ret = [];

        $lang   = isset($vod_job_new['video_lang']) ? $vod_job_new['video_lang'] : 'eng';
        if($vod_job_data) {
            // First check whether particular langauge data exists or not
            // IF EXISTS then delete given data
            foreach ($vod_job_data as $key => $vod_job_old) {
                if(isset($vod_job_old['language']) && $vod_job_old['language'] == $lang) {
                    // Remove this data form recordset
                    $ret[] = $vod_job_new;
                }
                else {
                    $ret[] = $vod_job_old;
                }
            }
        }
        else {
            $ret[] = $vod_job_new;
        }

        return $ret;
    }

    /**
     * Find and update vod_job_data
     *
     * @param   array   $vod_job_data
     * @param   object  $video
     * @return  array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function findAndUpdateVideoObject($vod_job_new,  $video = []) {
        $ret    = $video;
        $lang   = isset($vod_job_new['video_lang']) ? $vod_job_new['video_lang'] : 'eng';

        if(isset($vod_job_new['video_url_key']) && $vod_job_new['video_url_key']) {
            $video_url_key = $vod_job_new['video_url_key'];
            $video_url_raw = isset($vod_job_new['video_url_raw']) ? $vod_job_new['video_url_raw'] : '';
            if($video_url_key == 'url_eng') {
                $ret['url'] = $video_url_raw;
            }

            if($video_url_key && $video_url_raw) {
                $ret[$video_url_key] = $video_url_raw;
            }
        }
        return $ret;
    }


    /**
     * Generate Content Languages data from vod_job_data
     *
     * @param   array   $vod_job_data
     * @return  array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-17
     */
    public function generateContentLanguages($vod_job_data) {
        $ret    = [];
        foreach ($vod_job_data as $key => $vod) {
            $content_lang = [];
            $content_lang['is_default']     = isset($vod['is_default']) ? $vod['is_default'] : false;
            $content_lang['language']       = isset($vod['language']) ? $vod['language'] : 'eng';
            $content_lang['language_label'] = isset($vod['language_label']) ? $vod['language_label'] : 'English';
            $content_lang['language_url']   = isset($vod['video_url']) ? $vod['video_url'] : 'video_url';
            $ret[] = $content_lang;
        }
        return $ret;
    }

    /**
     * Returns Artist Default Language ID
     *
     * @param   string $artist_id
     * @return  string
     *
     * @author Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since 2019-06-07
     */
    public function getArtistLanguageId($artist_id)
    {
        $ret    = '';
        $result = \App\Models\Artistconfig::select('language_default')->where('artist_id', '=', $artist_id)->first();
        if($result) {
            $ret = isset($result->language_default) ? $result->language_default : '';
        }

        // If not found then give English Language Id as default language for artist
        if(!$ret) {
            $english = \App\Models\Language::select('_id')->where('code_3', '=', 'eng')->first();
            if($english) {
                $ret = isset($english->_id) ? $english->_id : '';
            }
        }

        return $ret;
    }


    /**
     * Returns Searched Contents
     *
     * @param   array
     * @return  string
     *
     * @author Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since 2019-06-07
     */
    public function search($data)
    {
        $ret    = [];
        $perpage    = 12;
        $artist_id  = (isset($data['artist_id']) && $data['artist_id'] != '') ? $data['artist_id'] : '';
        $keyword    = (isset($data['keyword']) && $data['keyword'] != '') ? $data['keyword'] : '';

        $default_lang_id    = Config::get('app.default_language_id');
        $return_attributes  = [
            '_id',
            'type',
            'name',
            'caption',
            'slug',
            'photo',
        ];

        $language_ids       = [$default_lang_id];
        $data               = null;

        $query  = \App\Models\Contentlang::with(['content' => function($query) use($artist_id) {
            if($artist_id) {
                $query->where('artist_id', $artist_id);
            }

            $query->where('status', 'active');

        }]);


        $query->where('status', 'active');
        $query->where('language_id', $default_lang_id);
        $query->where('name',  'like', '%' . $keyword . '%');

        $content_langs    = $query->paginate($perpage);

        if($content_langs) {
            $data = $content_langs->toArray();
            foreach ($content_langs as $key => $content_lang) {
                $name       = '';
                $caption    = '';
                $slug       = '';
                $photo      = null;

                $name       = trim($content_lang->name);
                $caption    = trim($content_lang->caption);
                $slug       = trim($content_lang->slug);

                $content = $content_lang->content;
                $content_id             = isset($content->_id) ? $content->_id : '';
                $content_type           = isset($content->type) ? trim(strtolower($content->type))  : 'photo';

                $ret_content['_id']             = $content_id;
                $ret_content['type']            = $content_type;
                $ret_content['name']            = $name;
                $ret_content['caption']         = $caption;
                $ret_content['slug']            = $slug;
                $ret_content['photo']           = $photo;

                switch ($content_type) {
                    case 'video':
                        $photo = isset($content->video) && !empty($content->video) ? $content->video : null;
                        break;

                    case 'photo':
                    default:
                        $photo = isset($content->photo) && !empty($content->photo) ? $content->photo : null;

                        if(!$photo) {
                            $photo = isset($content->photo_portrait) && !empty($content->photo_portrait) ? $content->photo_portrait : null;
                        }
                        break;
                }

                $ret_content['photo']   = $photo;

                if($ret_content) {
                    if($content_id) {
                        $ret['list'][] = array_only($ret_content, $return_attributes);
                    }
                }
            }

            $ret['paginate_data']['total']          = (isset($data['total'])) ? $data['total'] : 0;
            $ret['paginate_data']['per_page']       = (isset($data['per_page'])) ? $data['per_page'] : 0;
            $ret['paginate_data']['current_page']   = (isset($data['current_page'])) ? $data['current_page'] : 0;
            $ret['paginate_data']['last_page']      = (isset($data['last_page'])) ? $data['last_page'] : 0;
            $ret['paginate_data']['from']           = (isset($data['from'])) ? $data['from'] : 0;
            $ret['paginate_data']['to']             = (isset($data['to'])) ? $data['to'] : 0;
        }



        return $ret;
    }



 public function paidContentList()
    {
        $pvcontents         = \App\Models\Content::where('type', 'video')->where('commercial_type','paid')->where('status','active')->get()->pluck('name', '_id');
        return $pvcontents;
     }

     public function searchListing($requestData)
    {
        $results = [];
        $perpage            =   (isset($requestData['perpage']) && $requestData['perpage'] != '') ? intval($requestData['perpage']) : 10;
        $entity_id          =   (isset($requestData['entity_id']) && $requestData['entity_id'] != '') ? $requestData['entity_id'] : [];
        $artist_id          =   (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $platform           =   (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $customer_name      =   (isset($requestData['customer_name']) && $requestData['customer_name'] != '') ? $requestData['customer_name'] : '';
        $user_type          =   (isset($requestData['user_type']) && $requestData['user_type'] != '') ? $requestData['user_type'] : '';
        $txn_id             =   (isset($requestData['txn_id']) && $requestData['txn_id'] != '') ? $requestData['txn_id'] : '';
        $vendor_txn_id      =   (isset($requestData['vendor_txn_id']) && $requestData['vendor_txn_id'] != '') ? $requestData['vendor_txn_id'] : '';
        $vendor             =   (isset($requestData['vendor']) && $requestData['vendor'] != '') ? $requestData['vendor'] : '';
        $reward_event       =   (isset($requestData['reward_event']) && $requestData['reward_event'] != '') ? $requestData['reward_event'] : '';
        $status             =   (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : 'success';
        $created_at         =   (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end     =   (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';


        $appends_array = [
            'entity_id' => $entity_id,
            'artist_id' => $artist_id,
            'platform' => $platform,
            'customer_name' => $customer_name,
            'user_type' => $user_type,
            'txn_id' => $txn_id,
            'vendor_txn_id' => $vendor_txn_id,
            'vendor' => $vendor,
            'status' => $status,
            'reward_event' => $reward_event,
            'created_at' => $created_at,
            'created_at_end' => $created_at_end,

        ];


        $items                      =   $this->getContentsQuery($requestData);  //->paginate($perpage);
        $results['items']           =   $items;
  //      $results['coins']           =   $this->getPassbookQuery($requestData)->sum('total_coins');
    //    $results['amount']          =   $this->getPassbookQuery($requestData)->sum('amount');
     //   $results['count']           =   $items->total();
        $results['appends_array']   =   $appends_array;
//        dd($results);exit;

        return $results;
    }
   
    public function getContentsQuery($requestData)
    {
	$perpage= 50;
        $artists          =   [];
        if(!empty($requestData['artist_id'])){
            $artists  =  (is_array($requestData['artist_id'])) ? $requestData['artist_id'] : [trim($requestData['artist_id'])];
        }
        $entity             =   (!empty($requestData['entity']) && count($requestData['entity']) > 0) ? $requestData['entity'] : [];
        $entity_id          =   (isset($requestData['entity_id']) && $requestData['entity_id'] != '') ? $requestData['entity_id'] : [];
        $platform           =   (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $user_type          =   (isset($requestData['user_type']) && $requestData['user_type'] != '') ? $requestData['user_type'] : '';
        $status             =   (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : 'success';
        $created_at         =   (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end     =   (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';

        $txn_types          =   Config::get('app.passbook.txn_types');

	$query              =   \App\Models\content::with(array('artist'=>function($query){$query->select('_id','first_name','last_name','email');}));
					

        
    
        if (!empty($artists)) {
            $query->whereIn('artist_id', $artists);
        }
        if ($entity_id != '' && !empty($entity_id) && !empty($entity_id[0])) {

            $query->whereIn('_id', $entity_id);
	}
	$query->where('status', 'active');
        $query->where('commercial_type' , 'paid');
        $query->where('type', 'video');
	$cdata = $query->get();  //paginate($perpage);
	$finaldata= array();
	foreach($cdata as $c)
	{
		$requestData['entity_id'] = $c->_id;
		$carray =$c;
		$responseData               = $this->passbookservice->searchContentReport($requestData);
		$ccount = (isset($responseData['items'])) ? $responseData['items'] : 0;
		$carray['purchased_count']= $ccount; // $this->passbookservice->purchasedContentPassbook($requestData);
		$carray['coins_spends']= (isset($responseData['coins'])) ? $responseData['coins'] : 0; //$this->passbookservice->contentPassbookCoins($requestData); 
		$finaldata[] = $carray;		

	}





        return $finaldata;
    }


}

