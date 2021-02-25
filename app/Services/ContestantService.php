<?php

namespace App\Services;

use Config;
use Carbon;
use Storage;

use App\Models\Contestant;

use App\Services\Image\Kraken;

use App\Repositories\Contracts\ContestantInterface;

use \App\Services\AwsCloudfront;
use App\Services\Cache\AwsElasticCacheRedis;
use \App\Services\BucketService;
use \App\Services\ContentService;
use \App\Services\ArtistService;
use \App\Services\Mailers\ContestantMailer;

class ContestantService
{
    protected $model;
    protected $repObj;
    protected $kraken;
    protected $awscloudfrontService;
    protected $awsElasticCacheRedis;
    protected $bucketservice;
    protected $contentservice;
    protected $artistservice;
    protected $mailer;

    public function __construct(Contestant $model,ContestantInterface $repObj, Kraken $kraken, AwsCloudfront $awscloudfrontService,
        AwsElasticCacheRedis $awsElasticCacheRedis, BucketService $bucketservice, ContentService $contentservice, ArtistService $artistservice, ContestantMailer $mailer)
    {
        $this->model    = $model;
        $this->repObj   = $repObj;
        $this->kraken   = $kraken;
        $this->awscloudfrontService = $awscloudfrontService;
        $this->awsElasticCacheRedis = $awsElasticCacheRedis;
        $this->bucketservice = $bucketservice;
        $this->contentservice = $contentservice;
        $this->artistservice = $artistservice;
        $this->mailer = $mailer;
    }

    public function index($request)
    {
        $requestData = $request->all();
        $results = $this->repObj->index($requestData);
        return $results;
    }

    public function find($id)
    {
        $results = $this->repObj->find($id);
        return $results;
    }

    public function register($request)
    {
        $error_messages = array();
        $status_code    = 201;
        $data           = array_except($request->all(), ['password_confirmation']);
        $contestant_id  = null;
        $contestant_name= '';

        $email          = trim(strtolower($data['email']));
        $data['email']  = $email;
        $identity       = (isset($data['identity']) && $data['identity'] != '') ? trim($data['identity']) : 'email';

        if(isset($data['first_name'])) {
            $data['first_name'] = trim($data['first_name']);
        }
        if(isset($data['last_name'])) {
            $data['last_name'] = trim($data['last_name']);
        }
        if (empty($data['first_name']) && empty($data['last_name'])) {
            $data['first_name'] = explode("@", $data['email'])[0];
        }

        $contestant_name = $data['first_name'] . ' ' .  $data['last_name'];

        $contestant = \App\Models\Contestant::where('email', '=', $email)->first();

        if ($identity == 'email') {
            if (!empty($contestant)) {
                $error_messages[] = 'Contestant email already register';
                $status_code = 202;
            }
        }


        if (empty($error_messages)) {

            $data['status']                 = 'active';
            $data['approval_status']        = 'pending';
            $data['email_verified']         = 'false';
            $data['email_otp']              = rand(100000, 999999);
            $data['email_otp_generated_at'] = Carbon::now();
            $data['mobile_verified']        = 'false';

            // Contestant Profile Photo
            if ($request->hasFile('photo')) {
                $parmas = ['file' => $request->file('photo'), 'type' => 'contestant'];
                $photo  = $this->kraken->uploadToAws($parmas);
                if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                    array_set($data, 'photo', $photo['results']);
                    array_set($data, 'picture', $photo['results']['cover']);
                }
            }

            if($contestant) {
                $data = array_except($data, ['password_confirmation', 'platform']);
            }

            $data = array_except($data, ['password_confirmation', 'contents']);
            $contestant         = new \App\Models\Contestant($data);
            $contestant_saved   = $contestant->save();


            if($contestant_saved) {
                $contestant = \App\Models\Contestant::where('email', '=', $email)->first();
                if($contestant) {
                    $contestant_id = $contestant['_id'];
                }

                if($contestant_id) {
                    // Save Contestant Paid Content /// Can be Photos or videos
                    if ($request->hasFile('contents')) {
                        $contents   = [];
                        $contestant_contents = $request->file('contents');
                        foreach ($contestant_contents as $key => $content) {
                            $content_data = [];
                            $content_data['contestant_id']   = $contestant_id;

                            $paid_content_name = trim($contestant_name . ' photo');
                            // Find Content Type and save content accordingly
                            $content_data['name']           = $paid_content_name;
                            $content_data['caption']        = $paid_content_name;
                            $content_data['slug']           = str_slug($paid_content_name);
                            $content_data['content_type']   = 'paid_content';
                            $content_data['type']           = 'photo';
                            $content_data['ordering']       = ($key + 1);
                            $content_data['status']         = 'active';
                            $content_data['platforms']      = ['android', 'ios', 'web'];

                            switch ($content_data['type']) {
                                case 'photo':
                                    $content_parmas = ['file' => $content, 'type' => 'contestantcontent'];
                                    $content_photo  = $this->kraken->uploadToAws($content_parmas);
                                    if(!empty($content_photo) && !empty($content_photo['success']) && $content_photo['success'] === true && !empty($content_photo['results'])){
                                        array_set($content_data, 'photo', $content_photo['results']);
                                        array_set($content_data, 'picture', $content_photo['results']['cover']);
                                    }
                                    break;

                                case 'video':
                                    //upload to local drive
                                    $folder_path    = 'uploads/contestants/video/';
                                    $obj_path       = public_path($folder_path);
                                    $obj_extension  = $content->getClientOriginalExtension();
                                    $imageName      = time() . '_' . str_slug($content->getRealPath()) . '.' . $obj_extension;
                                    $fullpath       = $obj_path . $imageName;
                                    $content->move($obj_path, $imageName);
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
                                    break;
                                default:
                                    # code...
                                    break;
                            }

                            $contestant_content = new \App\Models\ContestantContent($content_data);
                            $contestant_media   = $contestant_content->save();
                            if($contestant_media) {
                                if(isset($contestant_content->_id)) {
                                    $contents[] = $contestant_content->_id;
                                }
                            }
                        }

                        if($contents) {
                            $contestant['contents'] = $contents;
                        }
                    }

                    // Save Contestant KYC Docs /// Can be Photos or Docs
                    if ($request->hasFile('kyc_docs')) {
                        $kyc_docs = [];
                        $contestant_kyc_docs = $request->file('kyc_docs');
                        foreach ($contestant_kyc_docs as $key => $kyc_doc) {
                            $content_data = [];
                            $content_data['contestant_id']   = $contestant_id;
                            $kyc_doc_name   = $contestant_name . ' kyc doc ' . ($key + 1);

                            // Find Content Type and save content accordingly
                            $content_data['name']           = $kyc_doc->getClientOriginalName();
                            $content_data['content_type']   = 'kyc_doc';
                            $content_data['type']           = 'doc';
                            $content_data['ordering']       = ($key + 1);
                            $content_data['status']         = 'active';

                            //upload to local drive
                            $folder_path    = 'uploads/contestants/doc/';
                            $obj_path       = public_path($folder_path);
                            $obj_extension  = $kyc_doc->getClientOriginalExtension();
                            $imageName      = time() . '_' . str_slug($kyc_doc->getRealPath()) . '.' . $obj_extension;
                            $fullpath       = $obj_path . $imageName;

                            $kyc_doc->move($obj_path, $imageName);
                            chmod($fullpath, 0777);

                            //upload to aws
                            $object_source_path = $fullpath;
                            $object_upload_path = 'contestantcontent/kyc/'.$imageName;
                            $response = null;
                            $s3 = Storage::createS3Driver(Config::get('product.' . env('PRODUCT') . '.s3.images'));
                            if (env('APP_ENV', 'stg') != 'local') {
                                try {
                                    $response = $s3->put($object_upload_path, file_get_contents($object_source_path), 'public');
                                }
                                catch (Exception $e) {
                                    $test_errors = [];
                                    $test_errors[] = $e->getMessage();
                                    \Log::info( __METHOD__ . ' PUT TO S3 KYC DOCS $response Exception ==>> ', $test_errors);
                                }
                            }

                            if($response) {
                                $content_data['doc_url'] = Config::get('product.' . env('PRODUCT') . '.s3.base_urls.photo') . $object_upload_path;
                            }

                            @unlink($fullpath);

                            $contestant_content = new \App\Models\ContestantContent($content_data);
                            $contestant_doc     = $contestant_content->save();

                            if($contestant_doc) {
                                if(isset($contestant_content->_id)) {
                                    $kyc_docs[] = $contestant_content->_id;
                                }
                            }
                        } // $contestant_kyc_docs

                        if($kyc_docs) {
                            $contestant['kyc_docs'] = $kyc_docs;
                        }
                    }

                    $contestant->update();
                }
            }

            $results['contestant']    = apply_cloudfront_url($contestant);

            $email_registration_data = array(
                'customer_email' => $email,
                'customer_name' => !empty($customer['first_name']) ? $customer['first_name'] : explode("@", $data['email'])[0],
                'celeb_name' => 'Hotshot',
            );

            // Sent Email registration mail
            //$send_mail = $this->mailer->sendRegistrationMail($email_registration_data);

        }

        $results['status_code'] = $status_code;

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function update($request, $id)
    {
        $data           = $request->all();
        $error_messages = $results = [];

        // Contestant Profile Photo
        if ($request->hasFile('photo')) {
            $parmas = ['file' => $request->file('photo'), 'type' => 'contestant'];
            $photo  = $this->kraken->uploadToAws($parmas);
            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                array_set($data, 'photo', $photo['results']);
                array_set($data, 'picture', $photo['results']['cover']);
            }
        }

        if(empty($error_messages)){
            $results['contestant']   = $this->repObj->update($data, $id);

            if ($request->hasFile('contents')) {
                $uploaded_contests = $this->uploadPaidContents($request, $id);
            }

            if ($request->hasFile('kyc_docs')) {
                $uploaded_kyc_docs = $this->uploadKycDouments($request, $id);
            }
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function activate($id)
    {
        $error_messages = $results = [];
        $artist_id      = null;
        $contestant_id  = null;
        $bucket_id      = null;
        $contestant_approved = true;

        // Find Contestant Details
        $modelContestant = $this->find($id);
        $hotshot_artist_id = Config::get('app.HOME_PAGE_ARTIST_ID');

        if($modelContestant) {
            $contestant     = $modelContestant->toArray();
            $contestant_id  = $contestant['_id'];

            // First create CMS User of Contestant
            $cmsuser_data = [];
            $cmsuser_data['first_name'] = $contestant['first_name'];
            $cmsuser_data['last_name']  = $contestant['last_name'];
            $cmsuser_data['email']      = $contestant['email'];
            $cmsuser_data['roles']      = ['59857f03af21a2d02523fbe2']; // Role : Artist
            $cmsuser_data['status']     = 'active';
            $cmsuser_data['recharge_web_status']    = 'inactive';
            $cmsuser_data['about_us']   = isset($contestant['about_us']) ? $contestant['about_us'] : '';
            $cmsuser_data['mobile']     = isset($contestant['mobile']) ? $contestant['mobile'] : '' ;
            $cmsuser_data['photo']      = isset($contestant['photo']) ? $contestant['photo'] : '' ;
            $cmsuser_data['picture']    = isset($contestant['picture']) ? $contestant['picture'] : '';
            $cmsuser_data['is_contestant']  = 'true';
            $cmsuser_data['stats']      = [
                'likes' => 0,
                'comments' => 0,
                'shares' => 0,
                'childrens' => 0,
                'hot_likes' => 0,
                'cold_likes' => 0,
            ];

            $modelCmsuser   = new \App\Models\Cmsuser($cmsuser_data);
            $cmsuser_saved  = $modelCmsuser->save();

            if($cmsuser_saved) {
                $artist_id          = $modelCmsuser->_id;
                $results['artist']  = $modelCmsuser;

                // Create Artist Config for created Contestant Artist
                // First get hotshot artist config
                $hotshot_artist_config = $this->artistservice->findArtistConfig($hotshot_artist_id);
                if($hotshot_artist_config) {
                    $contestant_artist_config = [];
                    $contestant_artist_config['artist_id'] = $artist_id;
                    if(isset($hotshot_artist_config['artist_languages'])) {
                        foreach ($hotshot_artist_config['artist_languages'] as $key => $language) {
                            if(isset($language['is_default']) && $language['is_default'] == true) {
                                $contestant_artist_config['language_default'] = isset($language['_id']) ? $language['_id'] : '';
                            }
                            else {
                                if(isset($language['_id'])) {
                                    $contestant_artist_config['languages'][] = $language['_id'];
                                }
                            }
                        }
                    }


                    if($contestant_artist_config) {
                        $modelArtistConfig = new \App\Models\Artistconfig($contestant_artist_config);
                        $config_saved  = $modelArtistConfig->save();
                    }
                }

                // Create Contest Bucket for Contestant Artist
                $bucket_data = [];
                $bucket_data['artist_id']       = $artist_id;
                $bucket_data['level']           = intval(1);
                $bucket_data['code']            = 'contest-paid-photo';
                $bucket_data['name']            = 'Contest Paid Photos';
                $bucket_data['caption']         = 'Contest Paid Photos';
                $bucket_data['ordering']        = intval(1);
                $bucket_data['content_types']   = ['photos'];
                $bucket_data['platforms']       = ['android', 'ios', 'web'];
                $bucket_data['visiblity']       = ['producer', 'customer'];
                $bucket_data['status']          = 'active';
                $bucket_data['slug']            = 'contest-paid-photos';
                $bucket_data['type']            = 'photo';
                //$modelBucket    = new \App\Models\Bucket($bucket_data);
                //$bucket_saved   = $modelBucket->save();


                $modelBucket = null;
                $bucket_saved = $this->bucketservice->storeArtistBucket($bucket_data, $hotshot_artist_id);
                if($bucket_saved) {
                    $modelBucket = (isset($bucket_saved['results']) && isset($bucket_saved['results']['bucket']) ) ? $bucket_saved['results']['bucket'] : null;
                }

                if($modelBucket) {
                    $bucket_id  = $modelBucket->_id;
                    // Update Contestant Paid Bucket ID in Artist Object
                    $modelCmsuser->contest_paid_content_bucket_id = $bucket_id;
                    $modelCmsuser->update();

                    $results['bucket']  = $modelBucket;

                    // Add all Contestant Contest into Context Bucket
                    $contestant_contents = \App\Models\ContestantContent::where('contestant_id', $contestant_id)->where('content_type', 'paid_content')->get()->toArray();

                    if($contestant_contents) {
                        foreach ($contestant_contents as $key => $contestant_content) {
                            $content_data = [];
                            $content_data['artist_id']  = $artist_id;
                            $content_data['bucket_id']  = $bucket_id;
                            $content_data['level']      = isset($contestant_content['level']) ? intval($contestant_content['level']) : 1;
                            $content_data['type']       = isset($contestant_content['type']) ? $contestant_content['type'] : '';
                            $content_data['ordering']   = intval($contestant_content['ordering']);
                            $content_data['status']     = 'active';
                            $content_data['name']       = isset($contestant_content['name']) ? $contestant_content['name'] : '';
                            $content_data['slug']       = isset($contestant_content['slug']) ? $contestant_content['slug'] : '';
                            $content_data['caption']    = isset($contestant_content['caption']) ? $contestant_content['caption'] : '';
                            $content_data['platforms']  = isset($contestant_content['platforms']) ? $contestant_content['platforms'] : [];

                            switch ($content_data['type']) {
                                case 'photo':
                                    $content_data['photo']      = isset($contestant_content['photo']) ? $contestant_content['photo'] : [];
                                    $content_data['picture']    = isset($contestant_content['picture']) ? $contestant_content['picture'] : '';
                                    break;

                                default:
                                    # code...
                                    break;
                            }

                            //$modelContent   = new \App\Models\Content($content_data);
                            //$content_saved  = $modelContent->save();
                            $modelContent = $this->contentservice->storeContestantContent($content_data, $hotshot_artist_id);

                            if(!$modelContent) {
                                $error_messages[] = 'Something went wrong while saving contestant content.';
                                $status_code = 202;
                                unset($results['artist']);
                                unset($results['bucket']);
                                unset($results['contents']);
                                $contestant_approved = false;
                                break;
                            }
                            else {
                                $results['contents'][]  = $modelContent;
                            }
                        }
                    }
                }
                else {
                    $contestant_approved = false;
                }
            }
            else {
                $contestant_approved = false;
            }

            if($contestant_approved) {
                $modelContestant->status            = 'active';
                $modelContestant->approval_status   = 'approved';
                $modelContestant->approved_at       = Carbon::now();
                $modelContestant->artist_id         = $artist_id;
                $modelContestant->update();

                $email_registration_approval_data = array(
                    'customer_email' => $contestant['email'],
                    'customer_name' => !empty($contestant['first_name']) ? $contestant['first_name'] : explode("@", $contestant['email'])[0],
                    'celeb_name' => 'Hotshot',
                );

                // Sent Email registration approval mail
                //$send_mail = $this->mailer->sendApprovalMail($email_registration_approval_data);
            }
        } else {
            $contestant_approved = false;
            $error_messages[] = 'Contestant not found in database.';
            $status_code = 202;
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }

    /**
     * Returns Contestant Artist Detail
     *
     * @param   string $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-24
     */
    public function artistDetail($artist_id, $request, $language = null)
    {
        $error_messages = $results = [];

        $cache_params   = [];
        $hash_name      = env_cache(Config::get('cache.hash_keys.contestant_detail').$artist_id);
        $hash_field     = $artist_id;
        $cache_miss     = false;

        $cache_params['hash_name']   =  $hash_name;
        $cache_params['hash_field']  =  (string) $hash_field;

        $artist = $this->awsElasticCacheRedis->getHashData($cache_params);
        if (empty($artist)) {
            // Find Contestant Artist Info form Database
            $responses  = $this->repObj->artistInfo($artist_id, false, $language);
            $items      = ($responses) ? apply_cloudfront_url($responses) : [];
            $cache_params['hash_field_value'] = $items;
            $saveToCache    = $this->awsElasticCacheRedis->saveHashData($cache_params);
            $cache_miss     = true;
            $artist         = $this->awsElasticCacheRedis->getHashData($cache_params);
        }

        $results['contestant']  = isset($artist['contestant']) ? $artist['contestant'] : [];
        $results['cache']       = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    /**
     * Returns Contestant Artist Detail
     *
     * @param   string $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-24
     */
    public function artistInfo($artist_id, $request, $language = null)
    {
        $error_messages = $results = [];

        $cache_params    = [];
        $hash_name      = env_cache(Config::get('cache.hash_keys.contestant_info').$artist_id);
        $hash_field     = $artist_id;
        $cache_miss     = false;

        $cache_params['hash_name']   =  $hash_name;
        $cache_params['hash_field']  =  (string) $hash_field;

        $artist = $this->awsElasticCacheRedis->getHashData($cache_params);
        if (empty($artist)) {
            // Find Contestant Artist Info form Database
            $responses  = $this->repObj->artistInfo($artist_id, true, $language);
            $items      = ($responses) ? apply_cloudfront_url($responses) : [];
            $cache_params['hash_field_value'] = $items;
            $saveToCache    = $this->awsElasticCacheRedis->saveHashData($cache_params);
            $cache_miss     = true;
            $artist         = $this->awsElasticCacheRedis->getHashData($cache_params);
        }

        $results['contestant']          = isset($artist['contestant']) ? $artist['contestant'] : [];
        $results['other_contentants']   = isset($artist['other_contentants']) ? $artist['other_contentants'] : [];
        $results['cache']               = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    /**
     * Returns Contestant Artists by
     *
     * @param   string $keyword
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-27
     */
    public function artistAutoSearch($keyword, $limit = 10)
    {
        $error_messages = $results = [];

        $keyword = strtolower($keyword);

        $cache_params   = [];
        $hash_name      = env_cache(Config::get('cache.hash_keys.contestant_artist_autosearch'));
        $hash_field     = ($keyword) ? $keyword : 'all_contestant_artists';
        $cache_miss     = false;

        $cache_params['hash_name']   =  $hash_name;
        $cache_params['hash_field']  =  (string) $hash_field;
        $cache_params['expire_time'] = 60; // 1 MIN IN SECONDS


        $artists = $this->awsElasticCacheRedis->getHashData($cache_params);
        if (empty($artists)) {
            // Find Contestant Artist Info form Database
            $responses  = $this->repObj->artistAutoSearch($keyword, $limit);
            $items      = ($responses) ? apply_cloudfront_url($responses) : [];
            $cache_params['hash_field_value'] = $items;
            $saveToCache    = $this->awsElasticCacheRedis->saveHashData($cache_params);
            $cache_miss     = true;
            $artists        = $this->awsElasticCacheRedis->getHashData($cache_params);
        }

        $results['contestants']  = isset($artists) ? $artists : [];
        $results['cache']       = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        $results['contestants']  = isset($artists) ? $artists : [];

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    /**
     * Returns Contestant Artists Sorted By
     *
     * @param   string $sort_by (name/stat.hot/stat.cold)
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-27
     */
    public function artistSortBy($sort_by = 'name', $request)
    {
        $error_messages = $results = [];

        $requestData    = $request->all();

        $platform       = (isset($requestData['platform']) && $requestData['platform'] != '') ? trim($requestData['platform']) : "android";
        $page           = (isset($requestData['page']) && $requestData['page'] != '') ? trim($requestData['page']) : 1;

        $cache_params   = [];
        $hash_name      = env_cache(Config::get('cache.hash_keys.contestant_artist_sortby')) . $sort_by . ':' . $platform;
        $hash_field     = $page;
        $cache_miss     = false;

        $cache_params['hash_name']   =  $hash_name;
        $cache_params['hash_field']  =  (string) $hash_field;

        $results = $this->awsElasticCacheRedis->getHashData($cache_params);
        if (empty($results)) {
            // Find Contestant Artist Info form Database
            $responses  = $this->repObj->artistSortBy($sort_by, $request);
            $items      = ($responses) ? apply_cloudfront_url($responses) : [];
            $cache_params['hash_field_value'] = $items;
            $saveToCache    = $this->awsElasticCacheRedis->saveHashData($cache_params);
            $cache_miss     = true;
            $results        = $this->awsElasticCacheRedis->getHashData($cache_params);
        }

        $results['cache']       = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    /**
     * Returns Contestant Artist Other Contestant Artits Detail
     *
     * @param   string $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-15
     */
    public function otherArtists($artist_id, $request, $language = null) {
        $results            = [];
        $error_messages     = [];

        $request_data   = $request->all();
        $visiblity      = (isset($request_data['visiblity']) && $request_data['visiblity'] != '') ? $request_data['visiblity'] : 'customer';
        $platform       = (isset($request_data['platform']) && $request_data['platform'] != '') ? trim($request_data['platform']) : "android";
        $page           = (isset($request_data['page']) && $request_data['page'] != '') ? trim($request_data['page']) : 1;

        $cache_params   = [];
        $hash_name      = env_cache(Config::get('cache.hash_keys.contestant_artist_other') . $artist_id . ':' . $platform . ':' . $visiblity);
        $hash_field     = $page;
        $cache_miss     = false;

        $cache_params['hash_name']  = $hash_name;
        $cache_params['hash_field'] = (string) $hash_field;

        $results = $this->awsElasticCacheRedis->getHashData($cache_params);
        $results = null;
        if(empty($results)){
            $responses                          = $this->repObj->otherContentants($artist_id, $request, $language);
            $items                              = ($responses) ? apply_cloudfront_url($responses) : [];
            $cache_params['hash_field_value']   = $items;
            $saveToCache                        = $this->awsElasticCacheRedis->saveHashData($cache_params);
            $cache_miss                         = true;
            $results                            = $this->awsElasticCacheRedis->getHashData($cache_params);
        }

        $results['cache']   = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Updata Contestant Artist Detail
     *
     * @param   request $request
     * @param   string  $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-01
     */
    public function updateArtist($request, $artist_id)
    {
        $data           = $request->all();
        $error_messages = $results = [];
        $update_attrs   = [
            'first_name',
            'last_name',
            'email',
            'mobile',
            'about_us',
            'status',
            'photo',
        ];

        // Contestant Profile Photo
        if ($request->hasFile('photo')) {
            $parmas = ['file' => $request->file('photo'), 'type' => 'contestant'];
            $photo  = $this->kraken->uploadToAws($parmas);
            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                array_set($data, 'photo', $photo['results']);
                array_set($data, 'picture', $photo['results']['cover']);
            }
        }

        $data = array_only($data, $update_attrs);
        if(empty($error_messages)){
            // Find Contestant By Artist Id
            $contestant = $this->model->where(['artist_id' => $artist_id])->first();
            if($contestant && $contestant->_id) {
                $results['contestant']   = $this->repObj->update($data, $contestant->_id);
            }
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    /**
     * Updata Contestant Paid Contents
     *
     * @param   request $request
     * @param   string  $contestant_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-01
     */
    public function uploadPaidContents($request, $id) {
        $ret = null;

        // Get Contestant Detail
        $contestant = $this->model->find($id);

        if($contestant) {
            $contestant_name = $contestant['first_name'] . ' ' .  $contestant['last_name'];

            // Save Contestant Paid Content /// Can be Photos or videos
            if ($request->hasFile('contents')) {
                $contents   = [];
                $contestant_contents = $request->file('contents');
                foreach ($contestant_contents as $key => $content) {
                    $content_data = [];
                    $content_data['contestant_id']   = $id;

                    $paid_content_name = trim($contestant_name . ' photo');
                    // Find Content Type and save content accordingly
                    $content_data['name']           = $paid_content_name;
                    $content_data['caption']        = $paid_content_name;
                    $content_data['slug']           = str_slug($paid_content_name);
                    $content_data['content_type']   = 'paid_content';
                    $content_data['type']           = 'photo';
                    $content_data['ordering']       = ($key + 1);
                    $content_data['status']         = 'active';
                    $content_data['platforms']      = ['android', 'ios', 'web'];
                    $content_data['meta']['title']      = $paid_content_name;
                    $content_data['meta']['description']= $paid_content_name;
                    $content_data['meta']['keywords']   = $paid_content_name;

                    switch ($content_data['type']) {
                        case 'photo':
                            $content_parmas = ['file' => $content, 'type' => 'contestantcontent'];
                            $content_photo  = $this->kraken->uploadToAws($content_parmas);
                            if(!empty($content_photo) && !empty($content_photo['success']) && $content_photo['success'] === true && !empty($content_photo['results'])){
                                array_set($content_data, 'photo', $content_photo['results']);
                                array_set($content_data, 'picture', $content_photo['results']['cover']);
                            }
                            break;

                        case 'video':
                            //upload to local drive
                            $folder_path    = 'uploads/contestants/video/';
                            $obj_path       = public_path($folder_path);
                            $obj_extension  = $content->getClientOriginalExtension();
                            $imageName      = time() . '_' . str_slug($content->getRealPath()) . '.' . $obj_extension;
                            $fullpath       = $obj_path . $imageName;
                            $content->move($obj_path, $imageName);
                            chmod($fullpath, 0777);

                            //upload to aws
                            $object_source_path = $fullpath;
                            $object_upload_path = $imageName;
                            $s3 = Storage::createS3Driver(Config::get('product.' . env('PRODUCT') . '.s3.rawvideos'));
                            if (env('APP_ENV', 'stg') != 'local') {
                                $response = $s3->put($object_upload_path, file_get_contents($object_source_path), 'public');
                            }

                            $vod_job_data = ['status' => 'submitted', 'object_name' => $imageName, 'object_path' => $object_upload_path, 'object_extension' => $obj_extension, 'bucket' => Config::get('product.' . env('PRODUCT') . '.s3.rawvideos.bucket')];
                            array_set($data, 'vod_job_data', $vod_job_data);
                            array_set($data, 'video_status', 'uploaded');

                            @unlink($fullpath);
                            break;
                        default:
                            # code...
                            break;
                    }

                    $contestant_content = new \App\Models\ContestantContent($content_data);
                    $contestant_media   = $contestant_content->save();
                    if($contestant_media) {
                        if(isset($contestant_content->_id)) {
                            $contents[] = $contestant_content->_id;
                        }
                    }
                }

                if($contents) {
                    $ret = $contents;
                }
            }
        }

        return $ret;
    }


    /**
     * Updata Contestant KYC Docments
     *
     * @param   request $request
     * @param   string  $contestant_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-01
     */
    public function uploadKycDouments($request, $id) {
        $ret = null;

        // Get Contestant Detail
        $contestant = $this->model->find($id);

        if($contestant) {
            $contestant_name = $contestant['first_name'] . ' ' .  $contestant['last_name'];

            // Save Contestant KYC Docs /// Can be Photos or Docs
            if ($request->hasFile('kyc_docs')) {
                $kyc_docs = [];
                $contestant_kyc_docs = $request->file('kyc_docs');
                foreach ($contestant_kyc_docs as $key => $kyc_doc) {
                    $content_data = [];
                    $content_data['contestant_id']   = $id;
                    $kyc_doc_name   = $contestant_name . ' kyc doc';

                    // Find Content Type and save content accordingly
                    $content_data['name']           = $kyc_doc->getClientOriginalName();
                    $content_data['content_type']   = 'kyc_doc';
                    $content_data['type']           = 'doc';
                    $content_data['ordering']       = ($key + 1);
                    $content_data['status']         = 'active';

                    //upload to local drive
                    $folder_path    = 'uploads/contestants/doc/';
                    $obj_path       = public_path($folder_path);
                    $obj_extension  = $kyc_doc->getClientOriginalExtension();
                    $imageName      = time() . '_' . str_slug($kyc_doc->getRealPath()) . '.' . $obj_extension;
                    $fullpath       = $obj_path . $imageName;

                    $kyc_doc->move($obj_path, $imageName);
                    chmod($fullpath, 0777);

                    //upload to aws
                    $object_source_path = $fullpath;
                    $object_upload_path = 'contestantcontent/kyc/'.$imageName;
                    $response = null;
                    $s3 = Storage::createS3Driver(Config::get('product.' . env('PRODUCT') . '.s3.images'));
                    if (env('APP_ENV', 'stg') != 'local') {
                        try {
                            $response = $s3->put($object_upload_path, file_get_contents($object_source_path), 'public');
                        }
                        catch (Exception $e) {
                            $test_errors = [];
                            $test_errors[] = $e->getMessage();
                            \Log::info( __METHOD__ . ' PUT TO S3 KYC DOCS $response Exception ==>> ', $test_errors);
                        }
                    }

                    if($response) {
                        $content_data['doc_url'] = Config::get('product.' . env('PRODUCT') . '.s3.base_urls.photo') . $object_upload_path;
                    }

                    @unlink($fullpath);

                    $contestant_content = new \App\Models\ContestantContent($content_data);
                    $contestant_doc     = $contestant_content->save();

                    if($contestant_doc) {
                        if(isset($contestant_content->_id)) {
                            $kyc_docs[] = $contestant_content->_id;
                        }
                    }
                } // $contestant_kyc_docs

                if($kyc_docs) {
                    $ret = $kyc_docs;
                }
            }
        }

        return $ret;
    }

}
