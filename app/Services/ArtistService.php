<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Input, Redirect, Config, Session, Hash, Cache, Log;
use Carbon;

use App\Services\Jwtauth;
use App\Services\CachingService;
use App\Services\Cache\AwsElasticCacheRedis;
use App\Services\Image\Kraken;
 
use App\Repositories\Contracts\ArtistInterface;
 

class ArtistService
{
    protected $jwtauth;
    protected $repObj;
     protected $caching;
    protected $awsElasticCacheRedis;
    protected $kraken;
 

    private $cf_base_url;

    public function __construct(
        Jwtauth $jwtauth,
        ArtistInterface $repObj,
         CachingService $caching,
        AwsElasticCacheRedis $awsElasticCacheRedis,
        Kraken $kraken
     )
    {
        $this->jwtauth = $jwtauth;
        $this->repObj = $repObj;
         $this->caching = $caching;
        $this->awsElasticCacheRedis = $awsElasticCacheRedis;
        $this->kraken = $kraken;
 
        $this->cf_base_url = Config::get('product.'. env('PRODUCT').'.cloudfront.urls.base');
    }

    public function index($request)
    {
        $results = $this->repObj->index($request);
        return $results;
    }

    public function find($id)
    {
        $results = $this->repObj->find($id);
        return $results;
    }


    public function activelists()
    {
        $results = $this->repObj->activelists();
        return $results;
    }


    public function showArtistConfig($artist_id)
    {
        $results = $this->repObj->showArtistConfig($artist_id);
        return $results;
    }

    public function artistList($request)
    {
        $error_messages = $results = [];
        $results = $this->repObj->artistList($request);

        return ['error_messages' => $error_messages, 'results' => $results];
    }



    public function activeArtistList()
    {
        $error_messages = $results = [];
        $results = $this->repObj->activeArtistList();

        return ['error_messages' => $error_messages, 'results' => $results];
    }



    public function artistListIdWise($artist_id)
    {
        $artists = \App\Models\Cmsuser::where('status', '=', 'active')->whereIn('_id', $artist_id)->get()->pluck('full_name', '_id');
        return $artists;
    }


    public function askToArtist($request)
    {
        $error_messages = $results = [];
        $results = $this->repObj->askToArtist($request);
        return $results;
        //  return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function getArtistConfig($artist_id)
    {
        $error_messages = $results = [];

        if (empty($error_messages)) {

            /*

            //OLD

            $cachetag_name = 'artistconfigs';
            $env_cachetag = env_cache_tag_key($cachetag_name);  //  ENV_artistconfigs
            $cachetag_key = $artist_id;                         //  ARTISTID
            $cache_time = Config::get('cache.cache_time');

            $item = Cache::tags($env_cachetag)->has($cachetag_key);

            if (!$item) {
                $items = ($configArr) ? $configArr : [];
                $items = apply_cloudfront_url($items);
                Cache::tags($env_cachetag)->put($cachetag_key, $items, $cache_time);
            }

            $results['cache'] = ['tags' => $env_cachetag, 'key' => $cachetag_key];
            $results['artistconfig'] = Cache::tags($env_cachetag)->get($cachetag_key);

            */


            $cacheParams = [];
            $hash_name = env_cache(Config::get('cache.hash_keys.artist_config') . $artist_id);
            $hash_field = $artist_id;
            $cache_miss = false;

            $cacheParams['hash_name'] = $hash_name;
            $cacheParams['hash_field'] = (string)$hash_field;

            $artistconfig = $this->awsElasticCacheRedis->getHashData($cacheParams);
            if (empty($artistconfig)) {

                $artistArr = [];
                $configArr = $this->repObj->showArtistConfig($artist_id);

                // Find artist languages
                $artist_langs = $this->getConfigLanguages($artist_id);
                if($artist_langs) {
                    $configArr['artist_languages'] = $artist_langs;
                }

                if(isset($configArr['languages'])) {
                    unset($configArr['languages']);
                }
                if(isset($configArr['language_default'])) {
                    unset($configArr['language_default']);
                }

                if (!empty($configArr['comment_channel_no'])) {
                    $gift_channel_no = Array($artist_id . ".g.0");
                    foreach ($configArr['comment_channel_no'] as $key => $val) {
                        $gift_channel_no[$key + 1] = $artist_id . ".g." . $val;
                    }
                    array_set($configArr, 'gift_channel_name', $gift_channel_no);
                } else {
                    array_set($configArr, 'gift_channel_name', Array($artist_id . ".g.0"));
                }

                if (!empty($configArr['comment_channel_no'])) {
                    $comment_channel_no = Array($artist_id . ".c.0");
                    foreach ($configArr['comment_channel_no'] as $key => $val) {
                        $comment_channel_no[$key + 1] = $artist_id . ".c." . $val;
                    }
                    array_except($configArr, ['comment_channel_no']);
                    array_set($configArr, 'comment_channel_name', $comment_channel_no);
                } else {
                    array_set($configArr, 'comment_channel_name', Array($artist_id . ".c.0"));
                }


                $artist = $this->repObj->find($artist_id);

                if ($artist) {
                    $artistArr = array_only($artist->toArray(), ['first_name', 'last_name', 'last_visited', 'picture']);
                    if (isset($artistArr['last_visited'])) {
                        $artistArr['human_readable_created_date'] = Carbon\Carbon::parse($artistArr['last_visited'])->format('F j\\, Y h:i A');
                        $artistArr['date_diff_for_human'] = Carbon\Carbon::parse($artistArr['last_visited'])->diffForHumans();
                    }
                    $configArr['artist'] = $artistArr;
                }

                $items = ($configArr) ? $configArr : [];
                $items = apply_cloudfront_url($items);
                $cacheParams['hash_field_value'] = $items;
                $saveToCache = $this->awsElasticCacheRedis->saveHashData($cacheParams);
                $cache_miss = true;
                $artistconfig = $this->awsElasticCacheRedis->getHashData($cacheParams);
            }


            $results['artistconfig'] = $artistconfig;
            $results['cache'] = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];


        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function updateArtistConfig($request)
    {

        $data = $request->all();
        $error_messages = $results = [];

        if ($request->hasFile('logo')) {
            $parmas = ['file' => $request->file('logo'), 'type' => 'artistlogo'];
            $logo   = $this->kraken->uploadToAws($parmas);
            if(!empty($logo) && !empty($logo['success']) && $logo['success'] === true && !empty($logo['results'])){
                array_set($data, 'logo', $logo['results']);
            }
        }

        if (empty($error_messages)) {
            $results['artistconfig'] = $this->repObj->updateArtistConfig($data);

            $artist_id = $results['artistconfig']['artist_id'];

            $purge_result = $this->awsElasticCacheRedis->purgeArtistConfigCache(['artist_id' => $artist_id]);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function getLeadboardList($request)
    {

        $data = $request->all();

        $error_messages = $results = [];

        //Leadboard will on artist bases
        $artist_id = $data['artist_id'];

        if (empty($error_messages)) {


            $cacheParams = [];
            $hash_name = env_cache(Config::get('cache.hash_keys.artist_leaderboards') . $artist_id);
            $hash_field = $artist_id;
            $cache_miss = false;

            $cacheParams['hash_name'] = $hash_name;
            $cacheParams['hash_field'] = (string)$hash_field;
            $cacheParams['expire_time'] = Config::get('cache.1_day') * 60;
            $results = $responses = $this->awsElasticCacheRedis->getHashData($cacheParams);
            if (empty($results)) {

                $fan_of_the_month_userid = [];
                $columns = ['_id', 'first_name', 'last_name', 'email', 'picture', 'identity', 'photo'];
                $fan_customerids = \App\Models\Fan::where('artist_id', $artist_id)->orderBy('created_at', 'desc')->where('status', 'active')->lists('customer_id')->first();
                if (!empty($fan_customerids)) {
                    //From Fan Section
                    $fan_of_the_month_cutomers = \App\Models\Customer::where('_id', $fan_customerids)->get($columns)->toArray();
                    $fan_of_the_month = ($fan_of_the_month_cutomers) ? $fan_of_the_month_cutomers[0] : [];
                } else {

                    //Random
                    $count = \App\Models\Customerartist::where('artist_id', $artist_id)->count();

                    if ($count > 10) {
                        $customerids = \App\Models\Customerartist::where('artist_id', $artist_id)->take(5)->orderBy('_id', 'desc')->lists('customer_id');
                    } else {
                        $customerids = \App\Models\Customerartist::where('artist_id', $artist_id)->take(1)->orderBy('_id', 'desc')->lists('customer_id');
                    }
                    $fan_of_the_month_customerids = ($customerids) ? $customerids->toArray() : [];


                    if ($artist_id == '598aa3d2af21a2355d686de2') {
//                 Poonam Pandey
                        $fan_of_the_month_customerids = ['5b17dd0340cd1378c6276d72'];
                    } elseif ($artist_id == '59858df7af21a2d01f54bde2') {
//                Zareen Khan
                        $fan_of_the_month_customerids = ['5a7198a26d90125a4c0c5c52'];
                    } elseif ($artist_id == '5a3373be9353ad4b0b0c2242') {
//                Karan Kundrra
                        $fan_of_the_month_customerids = ['5ad33c96b75a1a442c5b24a8'];
                    }

                    $fan_of_the_month_cutomers = \App\Models\Customer::whereIn('_id', $fan_of_the_month_customerids)->get($columns)->toArray();
                    $fan_of_the_month = ($fan_of_the_month_cutomers) ? $fan_of_the_month_cutomers[0] : [];
                }

                if (!empty($fan_of_the_month)) {
                    if (!isset($fan_of_the_month['first_name']) || $fan_of_the_month['first_name'] == '') {
                        $pieces = explode("@", $fan_of_the_month['email']);
                        $fan_of_the_month['first_name'] = $pieces[0];
                        array_push($fan_of_the_month_userid, $fan_of_the_month['_id']);
                    }
                    $fan_of_the_month['badge'] = [
                        'name' => 'fan of month',
                        'icon' => $this->cf_base_url . '/default/badges/fan-of-the-month.png'
                    ];
                }


                $leader_board_users_arr = [];
                $artist_customers = \App\Models\Customerartist::where('artist_id', $artist_id)->whereNotIn('customer_id', $fan_of_the_month_userid)->skip(6)->take(25)->orderBy('_id', 'desc')->lists('customer_id');
                $customer_ids = ($artist_customers) ? $artist_customers->toArray() : [];
                $leader_board_users = \App\Models\Customer::where('status', 'active')->where('first_name', '!=', '')->whereIn('_id', $customer_ids)->orderBy('_id', 'desc')->get($columns)->toArray();

                foreach ($leader_board_users as $user) {
                    $fan = $user;
                    if (!isset($fan['first_name']) || $fan['first_name'] == '') {
                        $pieces = explode("@", $fan['email']);
                        $fan['first_name'] = $pieces[0];
                    }
                    $fan['badge'] = [
                        'name' => 'super fan',
                        'icon' => $this->cf_base_url . '/default/badges/super-fan.png'
                    ];
                    array_push($leader_board_users_arr, $fan);
                }


                $responses['fan_of_the_month'] = $fan_of_the_month;
                $responses['leader_board_users'] = $leader_board_users_arr;

                $items = ($responses) ? $responses : [];
                $items = apply_cloudfront_url($items);
                $cacheParams['hash_field_value'] = $items;
                $saveToCache = $this->awsElasticCacheRedis->saveHashData($cacheParams);
                $cache_miss = true;
                $results = $this->awsElasticCacheRedis->getHashData($cacheParams);
            }

            $results['cache'] = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];


        }


        return ['error_messages' => $error_messages, 'results' => $results];

    }


    public function login($request)
    {

        $error_messages = $results = [];
        $data = array_except($request->all(), []);

        $identity = trim($data['identity']);
        $email = trim($data['email']);
        $cmsuser = \App\Models\Cmsuser::where('email', '=', $email)->first();

        if (empty($cmsuser)) {
            $error_messages[] = 'Artist does not exists';
        }

        if (!empty($cmsuser) && isset($cmsuser['status']) && $cmsuser['status'] != 'active') {
            $error_messages[] = 'Artist account is suspended';
        }


        if (!empty($cmsuser) && isset($data['password']) && $data['password'] != '' && $identity == 'email') {
            if (!Hash::check(trim($data['password']), $cmsuser['password'])) {
                $error_messages[] = 'Invalid credentials, please try again';
            }
        }


        $results = ['error_messages' => $error_messages];

        if (empty($error_messages)) {

            $cmsuser->last_visited = Carbon::now();

            $cmsuser->update();

            $results['artist'] = $cmsuser;

            $results['token'] = $this->jwtauth->createLoginToken($cmsuser);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function profile()
    {

        $error_messages = $results = [];
        if (empty($error_messages)) {
            $results['artist'] = $this->cmsuserRepObj->profile();
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function getIsLive($request)
    {
        $data = $request->all();
        $error_messages = $results = [];

        if (empty($error_messages)) {
            $results['artist'] = $this->cmsuserRepObj->getIsLive($data);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function isLive($request)
    {
        $data = $request->all();
        $error_messages = $results = [];

        if (empty($error_messages)) {
            $results['artist'] = $this->cmsuserRepObj->isLive($data);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function updateLastVisit()
    {

        $error_messages = $results = [];

        $user_id = $this->jwtauth->customerIdFromToken();
        $cmsuser = \App\Models\Cmsuser::where('_id', '=', $user_id)->first();

        if ($cmsuser) {
            $cmsuser->last_visited = Carbon::now();
            $cmsuser->update();
        }

        return ['error_messages' => $error_messages, 'results' => $results];

    }

    public function create_activity($data)
    {
        $error_messages = $results = [];

        if (empty($error_messages)) {
            $results = $this->repObj->create_activity($data);

            $artistActivitiesData = \App\Models\Artistactivity::where('artist_id', $data[0]['artist_id'])
                ->whereIn('activity_id', array_column($data, 'activity_id'))
                ->get()
                ->toArray();
            $masterActivitiesData = \App\Models\Activity::orderBy('name')->get()->toArray();

            $activityArr = [];
            foreach ($masterActivitiesData as $key => $value) {
                $activity_id = $value['_id'];
                $master_artist = head(array_where($artistActivitiesData, function ($master_key, $master_val) use ($activity_id) {
                    if ($master_val['activity_id'] == $activity_id) {
                        return $master_val;
                    }
                }));

                array_set($value, 'xp', (!empty($master_artist['xp'])) ? $master_artist['xp'] : $value['xp']);
                array_push($activityArr, $value);
            }
        }

        return ['error_messages' => $error_messages, 'results' => (!empty($activityArr)) ? $activityArr : $masterActivitiesData];
    }

    public function getDailystats($request)
    {
        $error_messages = $results = [];
        $requestData = $request->all();
        $results = $this->repObj->getDailyStats($requestData);

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function getDailyStatsIdWise($id)
    {
        $error_messages = $results = [];

        $results = \App\Models\Dailystats::findOrFail($id);

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function storeDailyStats($request)
    {
        $error_messages = $results = [];
        $requestData = array_except($request->all(), '_token');

        $date = (isset($requestData['stats_at']) && $requestData['stats_at'] != '') ? hyphen_date($requestData['stats_at']) : '';
        $date = new \MongoDB\BSON\UTCDateTime(strtotime($date) * 1000);

        array_set($requestData, 'stats_at', !empty($date) ? $date : Carbon::now());
        array_set($requestData, 'revenue', intval($requestData['revenue']));
        array_set($requestData, 'comments', intval($requestData['comments']));
        array_set($requestData, 'likes', intval($requestData['likes']));
        array_set($requestData, 'paid_customers', intval($requestData['paid_customers']));
        array_set($requestData, 'downloads', intval($requestData['downloads']));
        array_set($requestData, 'active_users', intval($requestData['active_users']));

        $results = $this->repObj->storeDailyStats($requestData);
        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function updateDailyStats($requestData, $id)
    {
        $error_messages = $results = [];

        $date = (isset($requestData['stats_at']) && $requestData['stats_at'] != '') ? hyphen_date($requestData['stats_at']) : '';
        $date = new \MongoDB\BSON\UTCDateTime(strtotime($date) * 1000);

        array_set($requestData, 'stats_at', !empty($date) ? $date : Carbon::now());
        array_set($requestData, 'revenue', intval($requestData['revenue']));
        array_set($requestData, 'comments', intval($requestData['comments']));
        array_set($requestData, 'likes', intval($requestData['likes']));
        array_set($requestData, 'paid_customers', intval($requestData['paid_customers']));
        array_set($requestData, 'downloads', intval($requestData['downloads']));
        array_set($requestData, 'active_users', intval($requestData['active_users']));

        if (empty($error_messages)) {
            $results['dailystats'] = $this->repObj->updateDailyStats($requestData, $id);
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function dailyStatsArtistWise($postData)
    {
//        echo Carbon::now()->subDays(30)->diffForHumans();die;
        $error_messages = $results = [];
        $getDailyStatsArtistWise = \App\Models\Dailystats::where('artist_id', $postData['artist_id'])
//            ->where('date', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 1 MONTH)'))
//            ->where('date', '>=', Carbon::now()->subMonth(30))
            ->where('stats_at', '>=', Carbon::now()->subDays(30))
            ->orderBy('stats_at', 'desc')
            ->where('status', 'active')
            ->get();
        $results['lists'] = !empty($getDailyStatsArtistWise) ? $getDailyStatsArtistWise : '';
        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function updateStatsStatus($request)
    {
        $error_messages = $results = [];

        $requestData = array_except($request->all(), '_token');

        $dailystats = \App\Models\Dailystats::findOrFail($requestData['_id']);
        $data = [];

        if ($requestData['status'] == 'active') {
            array_set($data, 'status', 'inactive');
        } else {
            array_set($data, 'status', 'active');
        }
        $dailystats->update($data);
    }

    public function assignChannel($customer_id, $artist_id)
    {
        $error_messages = $results = [];

        $results = $this->repObj->assignChannel($customer_id, $artist_id);

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function artistChannelNamespace($requestData)
    {
        $error_messages = $results = [];

        $results = $this->repObj->artistChannelNamespace($requestData);

        return ['error_messages' => $error_messages, 'results' => $results];

    }


    public function allArtist($requestData)
    {

        $error_messages = $results = [];

        $results = $this->repObj->artistInfoRechargeWise($requestData);

        return ['error_messages' => $error_messages, 'results' => $results];


    }

    public function packageArtistWise($requestData)
    {
        $error_messages = $results = [];

        $results = $this->repObj->packageArtistWise($requestData);

        return ['error_messages' => $error_messages, 'results' => $results];

    }

    public function validateEmail($request)
    {
        $error_messages = $results = [];
        $email = $request['email'];
        $artist_id = $request['artist_id'];

        $results    = \App\Models\Customer::where('email', $email)->whereIn('artists', [$artist_id])->first();
        $artist     = \App\Models\Cmsuser::where('_id', $artist_id)->first();
        $celeb_name = strtolower(@$artist['first_name']) . '-' . strtolower(@$artist['last_name']);

        if (!empty($results)) {
            return ['results' => $results];
        } else {
            $error_messages[0] = 'This Email is not registerd with this Artist';
            $error_messages[1] = !empty($celeb_name) ? $celeb_name : '';
            return ['error_messages' => $error_messages];
        }
    }

    public function generateAuthToken($email)
    {
        $results = \App\Models\Customer::where('email', $email)->first();

        $authToken = $this->jwtauth->createLoginToken($results);
        return ['results' => $authToken];

    }

    /**
     * Returns Artist Bio Detail
     *
     * @param   string $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-24
     */
    public function bio($artist_id, $request)
    {
        $error_messages = $results = [];

        $cache_params    = [];
        $hash_name      = env_cache(Config::get('cache.hash_keys.artist_bio'));
        $hash_field     = $artist_id;
        $cache_miss     = false;

        $cache_params['hash_name']   =  $hash_name;
        $cache_params['hash_field']  =  (string) $hash_field;

        $artist = $this->awsElasticCacheRedis->getHashData($cache_params);
        if (empty($artist)) {
            // Find Contestant Artist Info form Database
            $responses  = $this->repObj->bio($artist_id);
            $items      = ($responses) ? apply_cloudfront_url($responses) : [];
            $cache_params['hash_field_value'] = $items;
            $saveToCache    = $this->awsElasticCacheRedis->saveHashData($cache_params);
            $cache_miss     = true;
            $artist         = $this->awsElasticCacheRedis->getHashData($cache_params);
        }

        $results['artist']  = isset($artist['artist']) ? $artist['artist'] : [];
        $results['cache']   = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Returns All Active Languages
     *
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-02
     */
    public function getLanguages() {
        $ret = [];
        $error_messages = $results = [];
        $lang_by    = '_id';

        $cache_params    = [];
        $hash_name      = env_cache(Config::get('cache.hash_keys.language_list'));
        $hash_field     = $lang_by;
        $cache_miss     = false;

        $cache_params['hash_name']   =  $hash_name;
        $cache_params['hash_field']  =  (string) $hash_field;

        $languages = $this->awsElasticCacheRedis->getHashData($cache_params);
        if (empty($languages)) {

            // Find all languages form Database
            $languages = $this->languageService->getLabelsArrayBy($lang_by);

            if($languages) {
                $cache_params['hash_field_value'] = $languages;
                $saveToCache    = $this->awsElasticCacheRedis->saveHashData($cache_params);
                $cache_miss     = true;
                $languages      = $this->awsElasticCacheRedis->getHashData($cache_params);
            }
        }

        $results['languages']   = $languages;
        $results['cache']       = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        return $results;
    }


    /**
     * Returns Artist Active Languages
     *
     * @param   string $artist_id
     *
     * @return  Response
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-02
     */
    public function languages($artist_id) {
        $ret = [];
        $error_messages = $results = [];
        $lang_ids       = [];
        $lang_default   = '';
        $cache_params   = [];
        $hash_name      = env_cache(Config::get('cache.hash_keys.artist_config') . $artist_id);
        $hash_field     = 'languages';
        $cache_miss     = false;

        $cache_params['hash_name']   =  $hash_name;
        $cache_params['hash_field']  =  (string) $hash_field;

        $languages = $this->awsElasticCacheRedis->getHashData($cache_params);
        if (empty($languages)) {
            $artist_conifg = \App\Models\Artistconfig::where('artist_id', $artist_id)->first(['languages', 'language_default']);

            if($artist_conifg) {
                $artist_conifg_arr  = $artist_conifg->toArray();
                if($artist_conifg_arr) {
                    $lang_ids       = isset($artist_conifg_arr['languages']) ? $artist_conifg_arr['languages'] : [];
                    $lang_default   = isset($artist_conifg_arr['language_default']) ? $artist_conifg_arr['language_default'] : '';
                    if($lang_default) {
                        $lang_ids[] = $lang_default;
                    }
                    $lang_ids = array_unique($lang_ids);
                }

                if($lang_ids) {
                    // Find all languages form Database
                    $languages_obj = \App\Models\Language::where('status', '=', 'active')->whereIn('_id', $lang_ids)->orderBy('name')->get();
                    if($languages_obj) {
                        $languages_arr = $languages_obj->toArray();
                        if($languages_arr) {
                            foreach ($languages_arr as $key => $language_arr) {
                                $language_data = array_only($language_arr, ['_id', 'name', 'code_3', 'code_2']);
                                if($language_data) {
                                    $language_data['is_default'] = false;
                                    if(isset($language_data['_id']) && $language_data['_id'] == $lang_default) {
                                        $language_data['is_default'] = true;
                                    }
                                    $languages[] = $language_data;
                                }
                            }
                        }

                    }
                }
            }

            if($languages) {
                $cache_params['hash_field_value'] = $languages;
                $saveToCache    = $this->awsElasticCacheRedis->saveHashData($cache_params);
                $cache_miss     = true;
                $languages      = $this->awsElasticCacheRedis->getHashData($cache_params);
            }
        }

        $results['list']    = $languages;
        $results['cache']   = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function getLanguageArray($artist_id) {

        $results = [];

        $languages_list = $this->languages($artist_id);

        if($languages_list) {
            $languages = (isset($languages_list['results']) && isset($languages_list['results']['list'])) ? $languages_list['results']['list'] : [];

            if($languages) {
                foreach ($languages as $key => $language) {
                    $results[$language['_id']] = $language['name'];
                }
            }
        }

        return $results;
    }


    /**
     * Returns Artist Active Languages
     *
     * @param   string $artist_id
     *
     * @return  Array Languages
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-12
     */
    public function getConfigLanguages($artist_id) {
        $ret = null;

        $response = $this->languages($artist_id);
        if($response){
            $ret = isset($response['results']) && isset($response['results']['list']) ? $response['results']['list'] : [];
        }

        return $ret;
    }


    public function getArtistCode2LanguageArray($artist_id) {

        $configured_language = $this->getConfigLanguages($artist_id);

        $languagearr = [];
        foreach ($configured_language as $key => $language) {
            $languagearr[] = $language['code_2'];
        }

        return $languagearr;
    }


    /**
     * Returns Artist Active Languages By Code 3
     *
     * @param   string $artist_id
     *
     * @return  Array Languages
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-21
     */
    public function getLanguageArrayByCode3($artist_id) {
        $ret = [];

        $response = $this->languages($artist_id);
        if($response){
            $languages = isset($response['results']) && isset($response['results']['list']) ? $response['results']['list'] : [];
            if($languages) {
                foreach ($languages as $key => $language) {
                    $ret[$language['code_3']] = $language['name'];
                }
            }
        }

        return $ret;
    }

    /**
     * Returns Artist Config Data
     *
     * @param   string $artist_id
     *
     * @return  array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-21
     */
    public function findArtistConfig($artist_id) {
        $ret = [];

        $response = $this->getArtistConfig($artist_id);
        if($response){
            $ret = isset($response['results']) && isset($response['results']['artistconfig']) ? $response['results']['artistconfig'] : [];
        }

        return $ret;
    }

    /**
     * Returns Artist specific default data required for email template
     *
     * @param   string $artist_id
     *
     * @return  array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-03
     */
    public function getEmailTemplateDefaultData($artist_id) {
        $ret        = [
            'celeb_name'                        => '',
            'celeb_photo'                       => '',
            'celeb_photo_thumb'                 => '',
            'celeb_android_app_download_link'   => '',
            'celeb_ios_app_download_link'       => '',
            'celeb_direct_app_download_link'    => '',
            'celeb_social_facebook_link'        => '',
            'celeb_social_twitter_link'         => '',
            'celeb_social_instagram_link'       => '',
            'celeb_recharge_wallet_link'        => '',
            'template_image_base_url'           => '',
        ];

        $artist     = [];
        $template_image_base_url    = '';
        $celeb_name                 = '';
        $celeb_name_for_recharge    = '';
        $celeb_photo                = '';
        $celeb_photo_thumb          = '';

        $artist_config = $this->findArtistConfig($artist_id);
        if($artist_config) {
            $artist = isset($artist_config['artist']) ? $artist_config['artist'] : [];

            if($artist) {
                $celeb_name = generate_fullname($artist);
                $celeb_name_for_recharge = strtolower(@$artist['first_name']) . '-' . strtolower(@$artist['last_name']);
            }

            $celeb_photo    = isset($artist_config['photo']) ? $artist_config['artist'] : [];
            if($celeb_photo) {
                $celeb_photo_thumb   = isset($celeb_photo['thumb']) ? $celeb_photo['thumb'] : '';
            }
            else {
                $celeb_photo['thumb'] = '';
            }

            $ret['celeb_name']          = $celeb_name;
            $ret['celeb_photo']         = $celeb_photo;
            $ret['celeb_photo_thumb']   = $celeb_photo_thumb;
            $ret['celeb_android_app_download_link'] = isset($artist_config['android_app_download_link']) ? $artist_config['android_app_download_link'] : '';
            $ret['celeb_ios_app_download_link']     = isset($artist_config['ios_app_download_link']) ? $artist_config['ios_app_download_link'] : '';
            $ret['celeb_direct_app_download_link']  = isset($artist_config['direct_app_download_link']) ? $artist_config['direct_app_download_link'] : '';
            $ret['celeb_social_facebook_link']      = isset($artist_config['fb_page_url']) ? $artist_config['fb_page_url'] : '';
            $ret['celeb_social_twitter_link']       = isset($artist_config['twitter_page_url']) ? $artist_config['twitter_page_url'] : '';
            $ret['celeb_social_instagram_link']     = isset($artist_config['instagram_page_url']) ? $artist_config['instagram_page_url'] : '';
            $ret['celeb_recharge_wallet_link']      = 'https://recharge.bollyfame.com/wallet-recharge/' . $celeb_name_for_recharge . '/' . $artist_id;
            $ret['template_image_base_url']         = Config::get('product.' . env('PRODUCT') . '.s3.base_urls.photo').'emails';
        }

        $ret = apply_cloudfront_url($ret);

        return $ret;
    }

    public function getAgencyDashboard()
    {
        $results = [];
        $results['artist_count'] = $this->repObj->getArtistQuery('')->count();
        $results['live_session_count'] = $this->repObj->getArtistQuery('')->sum('stats.sessions');
        $results['total_coins'] = $this->repObj->getArtistQuery('')->sum('stats.coins');
        return $results;
    }
}
