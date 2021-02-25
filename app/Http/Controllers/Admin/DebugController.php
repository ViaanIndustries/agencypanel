<?php

namespace App\Http\Controllers\Admin;

use App\Services\Kraken;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;

use Input;
use Storage;
use Redirect;
use Config;
use Session;
use AWS, File;
use Twitter;
use Cache, Mail;


use App\Http\Controllers\Controller;
use App\Services\Gcp;
use App\Services\TwitterService;
use App\Services\TwitterServicePost;
use App\Services\InstagramUpload;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use App\Services\Notifications\PushNotification;


use Facebook\Facebook;
use Aws\ElasticTranscoder\ElasticTranscoderClient;

//use InstagramAPI\Instagram;

//use \InstagramAPI\Media\Photo\Media\Photo as InstaPhoto;

//use Aws\ElasticTranscoder\ElasticTranscoderClient;
use App\Services\ArtistService;

use App\Services\XmppRegister;
use GameNet\Jabber\RpcClient;
use Ejabberd\Client as XmppApiClient;
use App\Services\RedisDb;

use \GuzzleHttp\Exception\RequestException;
use \GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request as HttpRequest;

use \App\Services\Amazon\AwsElasticTranscoder;
use \App\Services\AwsMediaConvert;
use \App\Services\AwsCloudfront;
use App\Services\Cache\AwsElasticCacheRedis;
use App\Services\LanguageService;

class DebugController extends Controller
{

    protected $gcp;
    protected $twitterservice;
    protected $base_uri;
    protected $debug = false;
    protected $client;
    protected $pushnotification;
    protected $instagramupload;
    protected $redisdb;
    protected $kraken;
    protected $mediaconvertService;
    protected $elasticTranscoderService;
    protected $awscloudfrontService;
    protected $awsElasticCacheRedis;
    protected $serviceLanguage;

    public function __construct(
        Gcp $gcp,
        TwitterService $twitter,
        TwitterServicePost $twitterservicepost,
        PushNotification $pushnotification,
        InstagramUpload $instagramupload,
        RedisDb $redisdb,
        Kraken $kraken,
        AwsMediaConvert $mediaconvertService,
        AwsElasticTranscoder $elasticTranscoderService,
        AwsCloudfront $awscloudfrontService,
        AwsElasticCacheRedis $awsElasticCacheRedis,
        LanguageService $serviceLanguage
    )
    {
//        parent::__construct();
        $this->gcp = $gcp;
        $this->twitterservice = $twitter;
        $this->twitterservicepost = $twitterservicepost;
        $this->pushnotification = $pushnotification;
        $this->instagramupload = $instagramupload;
        $this->initClient();
        $this->redisdb = $redisdb;
        $this->kraken = $kraken;
        $this->mediaconvertService = $mediaconvertService;
        $this->elasticTranscoderService = $elasticTranscoderService;
        $this->awscloudfrontService = $awscloudfrontService;
        $this->awsElasticCacheRedis = $awsElasticCacheRedis;
        $this->serviceLanguage = $serviceLanguage;
    }




    public function createTmpFansForArtist($artistid = ''){

        $zareen_artist_id = "59858df7af21a2d01f54bde2";
//        $zareen_artist_id = $artistid;
        if(empty($artistid)){
            return $this->responseJson(['message' => 'required aritst id'], 200);
        }

        $artist = \App\Models\Cmsuser::where('_id', $artistid)->first();
        if(empty($artist)){
            return $this->responseJson(['message' => 'Provide Artist Id Does Not Exist'], 200);
        }

        $customers_ids = \App\Models\Customer::whereIn('artists', [trim($zareen_artist_id)])->where('picture', 'exists', true)->limit(50)->lists('_id');

        foreach ($customers_ids as $customers_id){
            $customer_artist = \App\Models\Customerartist::where('customer_id', $customers_id)->where('artist_id', $artistid)->first();
            if(empty($customer_artist)){
                $data = [
                    'customer_id' => $customers_id,
                    'artist_id' => $artistid,
                    'comment_channel_no' => 1
                ];
                $customer_artist_insert = new \App\Models\Customerartist($data);
                $save = $customer_artist_insert->save();
                if($save){
                    $customer = \App\Models\Customer::where('_id', $customers_id)->first();
                    if(!empty($customer)){
                        $customer->push('artists', trim(strtolower($artistid)), true);
                    }
                }

            }
        }// for each

        $artist_id      = $artistid;
        $purge_result   = $this->awsElasticCacheRedis->purgeArtistLeaderBoardsCache(['artist_id' => $artist_id]);
        return $this->responseJson(['message' => 'fans created for artist '], 200);
    }


    public function unSpentConis(Request $request){


        $data               =   $request->all();
        $requestData        =   $data;

        $order_status       =   (isset($requestData['order_status']) && $requestData['order_status'] != '') ? $requestData['order_status'] : 'successful';
        $artist_id          =   (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '598aa3d2af21a2355d686de2';
        $customer_name      =   (isset($requestData['customer_name']) && $requestData['customer_name'] != '') ? $requestData['customer_name'] : '';
        $user_type          =   (isset($requestData['user_type']) && $requestData['user_type'] != '') ? $requestData['user_type'] : 'genuine';
        $package_id         =   (isset($requestData['package_id']) && $requestData['package_id'] != '') ? $requestData['package_id'] : '';
        $platform           =   (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $vendor             =   (isset($requestData['vendor']) && $requestData['vendor'] != '') ? $requestData['vendor'] : '';
        $vendor_order_id    =   (isset($requestData['vendor_order_id']) && $requestData['vendor_order_id'] != '') ? $requestData['vendor_order_id'] : '';
        $order_id           =   (isset($requestData['order_id']) && $requestData['order_id'] != '') ? $requestData['order_id'] : '';
        $created_at         =   (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '01/01/2019';
        $created_at_end     =   (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '03/04/2019';


        $query = \App\Models\Order::where('order_status', $order_status);


        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        if ($package_id != '') {
            $query->where('package_id', $package_id);
        }

        if ($vendor_order_id != '') {
            $query->where('vendor_order_id', $vendor_order_id);
        }
        if ($order_id != '') {
            $query->where('_id', $order_id);
        }

        if ($created_at != '') {
            $query->where('created_at', '>', mongodb_start_date($created_at));
        }

        if ($created_at_end != '') {
            $query->where('created_at', '<', mongodb_end_date($created_at_end));
        }

        if ($platform != '') {
            $query->where('platform', $platform);
        }
        if ($vendor != '') {
            $query->where('vendor', $vendor);
        }

        if ($user_type != 'genuine') {
            $query->NotGenuineCustomers($customer_name);
        } else {
            $query->GenuineCustomers($customer_name);
        }

        $customerids            =     $query->lists('customer_id')->toArray();
        $uniquecustomerids      =     (!empty($customerids)) ? array_unique($customerids) : [];
        $unspent_coins          =     \App\Models\Customer::whereIn('_id', $uniquecustomerids)->sum('coins');
        $responses              =     [
            'requested_params'      =>  $requestData,
            'total'                 => [
                'customers' => count($customerids),
                'unique_customers' => count($uniquecustomerids),
                'unspent_coins' => $unspent_coins
            ]
        ];

        return $this->responseJson($responses, 200);
    }


    public function exportUnSpentConis(Request $request){


        $requestData        =   [];
        ini_set('memory_limit', '2000M');
        set_time_limit(30000);



        \Excel::create('unspent_coins_report', function ($excel) {

            $excel->setTitle('Export Customer List');
            $excel->setCreator('Sanjay Sahu')->setCompany('BOLLYFAME Media Pvt Ltd');
            $excel->setDescription('A demonstration to change the file properties');


            $excel->sheet('Customer List', function ($sheet) {


                $requestData        =   [];
                $order_status       =   (isset($requestData['order_status']) && $requestData['order_status'] != '') ? $requestData['order_status'] : 'successful';
                $artist_id          =   (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '598aa3d2af21a2355d686de2';
                $customer_name      =   (isset($requestData['customer_name']) && $requestData['customer_name'] != '') ? $requestData['customer_name'] : '';
                $user_type          =   (isset($requestData['user_type']) && $requestData['user_type'] != '') ? $requestData['user_type'] : 'genuine';
                $package_id         =   (isset($requestData['package_id']) && $requestData['package_id'] != '') ? $requestData['package_id'] : '';
                $platform           =   (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
                $vendor             =   (isset($requestData['vendor']) && $requestData['vendor'] != '') ? $requestData['vendor'] : '';
                $vendor_order_id    =   (isset($requestData['vendor_order_id']) && $requestData['vendor_order_id'] != '') ? $requestData['vendor_order_id'] : '';
                $order_id           =   (isset($requestData['order_id']) && $requestData['order_id'] != '') ? $requestData['order_id'] : '';
                $created_at         =   (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '01/01/2019';
                $created_at_end     =   (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '03/04/2019';


                $query = \App\Models\Order::where('order_status', $order_status);


                if ($artist_id != '') {
                    $query->where('artist_id', $artist_id);
                }

                if ($package_id != '') {
                    $query->where('package_id', $package_id);
                }

                if ($vendor_order_id != '') {
                    $query->where('vendor_order_id', $vendor_order_id);
                }
                if ($order_id != '') {
                    $query->where('_id', $order_id);
                }

                if ($created_at != '') {
                    $query->where('created_at', '>', mongodb_start_date($created_at));
                }

                if ($created_at_end != '') {
                    $query->where('created_at', '<', mongodb_end_date($created_at_end));
                }

                if ($platform != '') {
                    $query->where('platform', $platform);
                }
                if ($vendor != '') {
                    $query->where('vendor', $vendor);
                }

                if ($user_type != 'genuine') {
                    $query->NotGenuineCustomers($customer_name);
                } else {
                    $query->GenuineCustomers($customer_name);
                }

                $customerids            =     $query->lists('customer_id')->toArray();
                $uniquecustomerids      =     (!empty($customerids)) ? array_unique($customerids) : [];



                if (!empty($uniquecustomerids)) {

                    $selected_fields    =   ['_id','first_name','last_name','email','mobile','coins','gender'];
                    $customers          =   \App\Models\Customer::whereIn('_id', $uniquecustomerids)->get()->toArray();
                    $execlRowDataArr    =   [];

                    foreach ($customers as $key => $value) {

                        $customer_id                                =   (isset($value['_id']) && $value['_id'] != "") ? $value['_id'] : "-";
                        $customer_first_name                        =   (isset($value['first_name']) && $value['first_name'] != "") ? $value['first_name'] : "-";
                        $customer_last_name                         =   (isset($value['last_name']) && $value['last_name'] != "") ? $value['last_name'] : "-";
                        $customer_email                             =   (isset($value['email']) && $value['email'] != "") ? $value['email'] : "-";
                        $customer_mobile                            =   (isset($value['mobile']) && $value['mobile'] != "") ? $value['mobile'] : "-";
                        $coins                                      =   (isset($value['coins']) && $value['coins'] != "") ? $value['coins'] : "-";
                        $gender                                     =   (isset($value['gender']) && $value['gender'] != "") ? $value['gender'] : "-";

                        $execlRowData = [
                            'CUSTOMER ID'           => $customer_id,
                            'CUSTOMER FIRST NAME'   => $customer_first_name,
                            'CUSTOMER LAST NAME'    => $customer_last_name,
                            'CUSTOMER EMAIL'        => $customer_email,
                            'CUSTOMER MOBILE'       => $customer_mobile,
                            'CUSTOMER COINS'        => $coins,

                        ];
                        // echo "<pre>"; print_r($execlRowData); exit();
                        array_push($execlRowDataArr, $execlRowData);
                    }//
                    $sheet->fromArray($execlRowDataArr);
                }

            });// Register Genie List




        })->download('xlsx');

        echo "export Data";

    }

    public function updatepackage()
    {


        $artistConfig = \App\Models\Gift::whereIn('status', ['inactive','active'])->update(['platforms' => ['ios', 'android']]);



    }

    function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }


    public function awsS3fileExist()
    {

        $objectpath     =   "watermark/watermark.png";
        $s3             =   Storage::disk('s3_armsvideos');
        $exists         =   $s3->has($objectpath);

        var_dump($exists);



        $objectpath     =   "watermark/watermark1.png";
        $s3             =   Storage::disk('s3_armsvideos');
        $exists         =   $s3->has($objectpath);


        var_dump($exists);


    }


    public function awsCfContentFlush()
    {


//        $keys = ['/api/1.0/buckets/lists?artist_id=5a91386b9353ad33ab15b0d2&platform=ios&v=1', '/api/1.0/buckets/*'];
//        $keys = ['/api/1.0/buckets/lists?artist_id=5a91386b9353ad33ab15b0d2&platform=ios&v=1', '/api/1.0/buckets/*'];
//        $result =   $this->awscloudfrontService->invalidate($keys, $debug=true);
//
//        dd($result);

        $invalidate_result = $this->awscloudfrontService->invalidateContents();

        return $invalidate_result;


    }


    public function trascodeMediaConvertStatus($jobid = '')
    {

        return $create_job_result = $this->mediaconvertService->getJobStatus($jobid);

    }


    public function checkTrascodeMediaConvertStatus($artistid = '')
    {

        if ($artistid != '') {

            $contents = \App\Models\Content::where('artist_id', trim($artistid))
                ->where('mediaconvert_data', 'exists', true)
                ->where('mediaconvert_data.job_status', 'submitted')
                ->get(['_id', 'vod_job_data', 'mediaconvert_data']);


            $contents = (!empty($contents)) ? $contents->toArray() : [];

            foreach ($contents as $key => $val) {

                $content_id = $val['_id'];
                $job_id = (isset($val['mediaconvert_data']) && isset($val['mediaconvert_data']['job_id']) && $val['mediaconvert_data']['job_id'] != '') ? trim($val['mediaconvert_data']['job_id']) : '';
                $job_status = (isset($val['mediaconvert_data']) && isset($val['mediaconvert_data']['job_status']) && $val['mediaconvert_data']['job_status'] != '') ? trim($val['mediaconvert_data']['job_status']) : '';
                $filename_withext = (isset($val['mediaconvert_data']) && isset($val['mediaconvert_data']['filename_withext']) && $val['mediaconvert_data']['filename_withext'] != '') ? trim($val['mediaconvert_data']['filename_withext']) : '';


                if ($job_id != '') {

                    $create_job_result = $this->mediaconvertService->getJobStatus($job_id);

                    if (isset($create_job_result['results']) && isset($create_job_result['results']['job_status']) && $create_job_result['results']['job_status'] != '') {

                        $job_data = $create_job_result['results']['job_data'];
                        $job_status = strtolower($create_job_result['results']['job_status']);

                        if ($job_status == 'complete' || $job_status == 'error') {


                            $contentObj = \App\Models\Content::where('_id', $content_id)->first();

                            $update_data = [];


                            if ($job_status == 'complete') {
                                $video_url = "https://s3-ap-south-1.amazonaws.com/armsvideos/mediaconvert/$content_id/$filename_withext.m3u8";
                                $viedoObject = (isset($contentObj['video'])) ? $contentObj['video'] : [];
                                $viedoObject['url'] = $video_url;

                                $mediaconvertDataObject = (isset($contentObj['mediaconvert_data'])) ? $contentObj['mediaconvert_data'] : [];
                                $mediaconvertDataObject['job_status'] = $job_status;

                                $update_data = ['video' => $viedoObject, 'mediaconvert_data' => $mediaconvertDataObject];

                            }

                            if ($job_status == 'error') {
                                $mediaconvertDataObject = (isset($contentObj['mediaconvert_data'])) ? $contentObj['mediaconvert_data'] : [];
                                $mediaconvertDataObject['job_status'] = $job_status;
                                $mediaconvertDataObject['ErrorCode'] = (isset($job_data['ErrorCode'])) ? $job_data['ErrorCode'] : '';
                                $mediaconvertDataObject['ErrorMessage'] = (isset($job_data['ErrorMessage'])) ? $job_data['ErrorMessage'] : '';
                                $update_data = ['mediaconvert_data' => $mediaconvertDataObject];
                            }

//                            print_pretty($update_data);
                            $contentObj->update($update_data);

                        }
                    }

                }//$job_id
            }//foreach
        }

        echo "check all videos";

    }


    public function trascodeMediaConvert($artistid = '')
    {

        if ($artistid != '') {

            try {

                $contents = \App\Models\Content::where('artist_id', trim($artistid))
                    ->where('mediaconvert_data', 'exists', false)
                    ->where('video_status', 'completed')
                    ->get(['_id', 'vod_job_data']);


                // Transcode only error video
//                $contents = \App\Models\Content::where('artist_id', trim($artistid))
//                    ->where('mediaconvert_data', 'exists', true)
//                    ->where('mediaconvert_data.ErrorCode' , 1404)
//                    ->get(['_id','vod_job_data']);


                $contents = (!empty($contents)) ? $contents->toArray() : [];

                foreach ($contents as $key => $val) {

                    $content_id = $val['_id'];
                    $object_name = (isset($val['vod_job_data']) && isset($val['vod_job_data']['object_name']) && $val['vod_job_data']['object_name'] != '') ? trim($val['vod_job_data']['object_name']) : '';

                    if ($object_name != '' && $content_id != '') {

                        $s3_input_path = "s3://armsrawvideos/" . $object_name;
                        $s3_output_path = "s3://armsvideos/mediaconvert/$content_id/";


                        if ($s3_input_path != '' && $s3_output_path != '') {

                            $params = [
                                's3_input_path' => $s3_input_path,
                                's3_output_path' => $s3_output_path
                            ];

                            $create_job_result = $this->mediaconvertService->createHlsVodJob($params);

                            if (isset($create_job_result['results']) && isset($create_job_result['results']['job_id']) && $create_job_result['results']['job_id'] != '') {
                                $job_data = $create_job_result['results']['job_data'];
                                $job_id = $create_job_result['results']['job_id'];
                                $job_status = $create_job_result['results']['job_status'];
                                $contentObj = \App\Models\Content::where('_id', $content_id)->first();

                                $mediaconvert_data = [
                                    'job_id' => $job_id,
                                    'job_data' => $job_data,
                                    'job_status' => $job_status,
                                    'filename_withext' => pathinfo($object_name, PATHINFO_FILENAME),
                                ];

                                $contentObj->update(['mediaconvert_data' => $mediaconvert_data]);
                            }

                        }
                    }
                }


            } catch (Exception $e) {

                $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                Log::info('vod:createhlsjob  : Fail ', $message);

            }

        }

        echo "submitt all videos";
    }



    public function trascodeMediaConvertContent($contentid = '')
    {

        if ($contentid != '') {

            try {

                $contents = \App\Models\Content::where('_id', trim($contentid))
                    ->where('video_status', 'completed')
                    ->get(['_id', 'vod_job_data']);


                // Transcode only error video
//                $contents = \App\Models\Content::where('artist_id', trim($artistid))
//                    ->where('mediaconvert_data', 'exists', true)
//                    ->where('mediaconvert_data.ErrorCode' , 1404)
//                    ->get(['_id','vod_job_data']);


                $contents = (!empty($contents)) ? $contents->toArray() : [];

                foreach ($contents as $key => $val) {

                    $content_id = $val['_id'];
                    $object_name = (isset($val['vod_job_data']) && isset($val['vod_job_data']['object_name']) && $val['vod_job_data']['object_name'] != '') ? trim($val['vod_job_data']['object_name']) : '';

                    if ($object_name != '' && $content_id != '') {

                        $s3_input_path = "s3://armsrawvideos/" . $object_name;
                        $s3_output_path = "s3://armsvideos/mediaconvert/$content_id/";


                        if ($s3_input_path != '' && $s3_output_path != '') {

                            $params = [
                                's3_input_path' => $s3_input_path,
                                's3_output_path' => $s3_output_path
                            ];

                            $create_job_result = $this->mediaconvertService->createHlsVodJob($params);

                            if (isset($create_job_result['results']) && isset($create_job_result['results']['job_id']) && $create_job_result['results']['job_id'] != '') {
                                $job_data = $create_job_result['results']['job_data'];
                                $job_id = $create_job_result['results']['job_id'];
                                $job_status = $create_job_result['results']['job_status'];
                                $contentObj = \App\Models\Content::where('_id', $content_id)->first();

                                $mediaconvert_data = [
                                    'job_id' => $job_id,
                                    'job_data' => $job_data,
                                    'job_status' => $job_status,
                                    'filename_withext' => pathinfo($object_name, PATHINFO_FILENAME),
                                ];

                                $contentObj->update(['mediaconvert_data' => $mediaconvert_data]);
                            }

                        }
                    }
                }


            } catch (Exception $e) {

                $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                Log::info('vod:createhlsjob  : Fail ', $message);

            }

        }

        echo "submitt video";
    }



    public function checkTrascodeMediaConvertStatusContent($contentid = '')
    {

        if ($contentid != '') {

            $contents = \App\Models\Content::where('_id', trim($contentid))
                ->where('mediaconvert_data', 'exists', true)
                ->where('mediaconvert_data.job_status', 'submitted')
                ->get(['_id', 'vod_job_data', 'mediaconvert_data']);


            $contents = (!empty($contents)) ? $contents->toArray() : [];

            foreach ($contents as $key => $val) {

                $content_id = $val['_id'];
                $job_id = (isset($val['mediaconvert_data']) && isset($val['mediaconvert_data']['job_id']) && $val['mediaconvert_data']['job_id'] != '') ? trim($val['mediaconvert_data']['job_id']) : '';
                $job_status = (isset($val['mediaconvert_data']) && isset($val['mediaconvert_data']['job_status']) && $val['mediaconvert_data']['job_status'] != '') ? trim($val['mediaconvert_data']['job_status']) : '';
                $filename_withext = (isset($val['mediaconvert_data']) && isset($val['mediaconvert_data']['filename_withext']) && $val['mediaconvert_data']['filename_withext'] != '') ? trim($val['mediaconvert_data']['filename_withext']) : '';


                if ($job_id != '') {

                    $create_job_result = $this->mediaconvertService->getJobStatus($job_id);

                    if (isset($create_job_result['results']) && isset($create_job_result['results']['job_status']) && $create_job_result['results']['job_status'] != '') {

                        $job_data = $create_job_result['results']['job_data'];
                        $job_status = strtolower($create_job_result['results']['job_status']);

                        if ($job_status == 'complete' || $job_status == 'error') {


                            $contentObj = \App\Models\Content::where('_id', $content_id)->first();

                            $update_data = [];


                            if ($job_status == 'complete') {
                                $video_url = "https://s3-ap-south-1.amazonaws.com/armsvideos/mediaconvert/$content_id/$filename_withext.m3u8";
                                $viedoObject = (isset($contentObj['video'])) ? $contentObj['video'] : [];
                                $viedoObject['url'] = $video_url;

                                $mediaconvertDataObject = (isset($contentObj['mediaconvert_data'])) ? $contentObj['mediaconvert_data'] : [];
                                $mediaconvertDataObject['job_status'] = $job_status;

                                $update_data = ['video' => $viedoObject, 'mediaconvert_data' => $mediaconvertDataObject];

                            }

                            if ($job_status == 'error') {
                                $mediaconvertDataObject = (isset($contentObj['mediaconvert_data'])) ? $contentObj['mediaconvert_data'] : [];
                                $mediaconvertDataObject['job_status'] = $job_status;
                                $mediaconvertDataObject['ErrorCode'] = (isset($job_data['ErrorCode'])) ? $job_data['ErrorCode'] : '';
                                $mediaconvertDataObject['ErrorMessage'] = (isset($job_data['ErrorMessage'])) ? $job_data['ErrorMessage'] : '';
                                $update_data = ['mediaconvert_data' => $mediaconvertDataObject];
                            }

//                            print_pretty($update_data);
                            $contentObj->update($update_data);

                        }
                    }

                }//$job_id
            }//foreach
        }

        echo "check videos";

    }


    public function testAwsCfResponse()
    {
        $HttpClient = new \GuzzleHttp\Client([
            'debug' => true,
            'headers' => [
                'Content-Type' => 'application/json',
                'ApiKey' => 'eynpaQcc7nihvcYuZOuU0TeP7tlNC6o5'
            ]
        ]);

        $response = $HttpClient->request('GET', 'https://d1hjd9k51c42ro.cloudfront.net/api/1.0/buckets/lists?artist_id=598aa3d2af21a2355d686de2&platform=android');

        echo "<br><br>";
        echo "##################### HEADERS START #####################" . "<Br>";

        // Get all of the response headers.
        foreach ($response->getHeaders() as $name => $values) {
            echo $name . ': ' . implode(', ', $values) . "<Br>";
        }

        echo "##################### HEADERS END #####################";
    }


    public function storeAwsCfResponseContentList()
    {
        ini_set('max_execution_time', 3600); //3600 seconds = 60 minutes

        $HttpClient = new \GuzzleHttp\Client([
            'debug' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'ApiKey' => 'eynpaQcc7nihvcYuZOuU0TeP7tlNC6o5'
            ]
        ]);

        $redisClient = $this->redisdb->PredisConnection();

        $redistag_name = "redis_client";
        $redistag_key = env_cache_tag_key($redistag_name);

        $key_exists = $redisClient->exists($redistag_key);

        if ($key_exists) {
            $redisClient->del([$redistag_key]);
        }

        for ($i = 1; $i <= 100; $i++) {
            $response = $HttpClient->request('GET', 'https://d1hjd9k51c42ro.cloudfront.net/api/1.0/contents/lists?artist_id=598aa3d2af21a2355d686de2&platform=android&bucket_id=5b640b47112ea024404db852&visiblity=customer&page=1');
            $response = $response->getHeaders();

            $request_headers = [
//                'Content-Type' => isset($response['Content-Type'][0]) ? $response['Content-Type'][0] : '-',
//                'Content-Length' => isset($response['Content-Length'][0]) ? $response['Content-Length'][0] : '-',
//                'Connection' => isset($response['Connection'][0]) ? $response['Connection'][0] : '-',
//                'Server' => isset($response['Server'][0]) ? $response['Server'][0] : '-',
//                'Cache-Control' => isset($response['Cache-Control'][0]) ? $response['Cache-Control'][0] : '-',
//                'Access-Control-Allow-Origin' => isset($response['Access-Control-Allow-Origin'][0]) ? $response['Access-Control-Allow-Origin'][0] : '-',
//                'Access-Control-Allow-Headers' => isset($response['Access-Control-Allow-Headers'][0]) ? $response['Access-Control-Allow-Headers'][0] : '-',
//                'Access-Control-Allow-Methods' => isset($response['Access-Control-Allow-Methods'][0]) ? $response['Access-Control-Allow-Methods'][0] : '-',
//                'Via' => isset($response['Via'][0]) ? $response['Via'][0] : '-',
//                'Alt-Svc' => isset($response['Alt-Svc'][0]) ? $response['Alt-Svc'][0] : '-',
//                'Vary' => isset($response['Vary'][0]) ? $response['Vary'][0] : '-',
                'Age' => isset($response['Age'][0]) ? $response['Age'][0] : '-',
                'X-Cache' => isset($response['X-Cache'][0]) ? $response['X-Cache'][0] : '-',
                'X-Amz-Cf-Id' => isset($response['X-Amz-Cf-Id'][0]) ? $response['X-Amz-Cf-Id'][0] : '-',
            ];

            $requestArr = [
//                'request_url' => 'https://d1hjd9k51c42ro.cloudfront.net',
//                'request_query_string' => 'artist_id=598aa3d2af21a2355d686de2&platform=android&bucket_id=5b640b47112ea024404db852&visiblity=customer&page=1',
                'request_date' => Carbon::now()->toDateTimeString(),
                'request_headers' => $request_headers,
                'activity' => 'Content Listing'
            ];

            $append_list_to_buttom = $redisClient->lpush($redistag_key, json_encode($requestArr)); //Append item to the buttom
            $expire = $redisClient->expire($redistag_key, 3600); //1 Hr expire time
            sleep(1); //1 sec
        }

        return "Stored Successfully";
    }


    public function fetchAwsCfResponseContentList()
    {
        $redisClient = $this->redisdb->PredisConnection();

        $redistag_name = "redis_client";
        $redistag_key = env_cache_tag_key($redistag_name);

        $fetch_redis_data = $redisClient->lrange($redistag_key, 0, -1); // lrange(keyname, limit, offset)

        $all_data = [];

        foreach ($fetch_redis_data as $key => $val) {
            $all_data[$key] = json_decode($val);
        }
        return $all_data;
    }


    public function getTagKeyData($env_cachetag, $cachetag_key)
    {

        $results = Cache::tags($env_cachetag)->get($cachetag_key);
        return $results;
    }

    public function firebaseTest()
    {

        $viewdata = [];

        return view('admin.debug.firebase', $viewdata);
    }


    public function xmppapi()
    {

        echo "<br> <br> testing xmppapi <br> <br>";

        $client = XmppApiClient([
            'port' => 5222,
            'host' => '130.211.252.111',
            'apiEndPoint' => 'api'
        ]);

        var_dump($client);

    }


    public function xmpp()
    {

        echo "<br> <br> testing xmpp <br> <br>";

        $address = 'tcp://130.211.252.111:5222';
        $adminUsername = 'admin';
        $adminPassword = 'password';

        $options = new XmppOptions($address);
//        $options->setUsername($adminUsername)->setPassword($adminPassword)->setTo('localhost')
        $options->setUsername($adminUsername)
            ->setTo('localhost')
            ->setPassword($adminPassword)
            ->setContextOptions(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);

        $client = new XmppClient($options);

        $client->connect();


        $name = 'abcxyz';
        $password = 'abcxyz';
        $email = 'abcxyz@localhost.com';


//        $register = new XmppRegister($name, $password);
//        $registerStatus = $client->send($register);

        $register = new XmppRegister($name, $password, $email);
        $registerStatus = $client->send($register);

//        var_dump($register);
        var_dump($registerStatus);


        // fetch roster list; users and their groups
        $client->send(new XmppRoster);
        // set status to online
        $client->send(new XmppPresence);

        // send a message to another user
        $message = new XmppMessage;
        $message->setMessage('how r u')->setTo('test@localhost');
        $client->send($message);


        $client->disconnect();


    }


    public function xmpp1()
    {

        $rpc = new \GameNet\Jabber\RpcClient([
            'server' => 'http://130.211.252.111:5222',
            'host' => 'localhost',
            'debug' => false,
        ]);

        //Create 2 new users with name `Ivan` and `Petr` with password `someStrongPassword`
        $rpc->createUser('ivan', 'ivan');
        $rpc->createUser('peter', 'peter');


    }

    public function sendemail()
    {

        echo "<br>sending email<br>";


        $email_template = 'emails.emailotp';
        $message_data = ['user_email' => 'sanjay.id7@gmail.com'];
        $template_data = ['otp' => '111111111'];
        $send_email = Mail::send($email_template, $template_data, function ($message) use ($message_data) {
            $message->to($message_data['user_email'], $message_data['user_email'])->subject('Your verification otp code');
        });


        $email_template = 'emails.emailotp';
        $message_data = ['user_email' => 'sanjay@razrcorp.com'];
        $template_data = ['otp' => '111111111'];
        $send_email = Mail::send($email_template, $template_data, function ($message) use ($message_data) {
            $message->to($message_data['user_email'], $message_data['user_email'])->subject('Your verification otp code');
        });

        echo "<br>Sent<br>";


    }


    public function vodHlsJobCreate()
    {

        $params     = [
            's3_input_file' => 'test_p_1.mp4'
        ];
        $responseData = $this->elasticTranscoderService->createHlsVodJob($params);

        print_pretty($responseData);exit;

        # Create the client for Elastic Transcoder.
        $ElasticTranscoder = ElasticTranscoderClient::factory([
            'credentials' => ['key' => env('AWS_ACCESS_KEY_ID'), 'secret' => env('AWS_SECRET_ACCESS_KEY')],
            'region' => 'ap-south-1',
            'version' => '2012-09-25',
        ]);
//        $requestData    =   $request->all();
//        $inputs  = [
//            '1542566162_tmpphp3uto1z.mp4',
//            '636778807780772992.mp4',
//            'app_used_camera.mp4',
//            'test_p_0.mp4',
//            'test_p_1.mp4',
//            'test_p_2.mp4',
//            'test_p_3.mp4',
//            'VID-20181121-WA0013.mp4',
//            'VID20181123181318.mp4',
//
//        ];

        $inputs  = [
            'test_p_1.mp4'
        ];

        foreach ($inputs as $val){

//            $object_name                    =   "test_p_1.mp4";
            $object_name                    =   $val;
            $object_name_without_extension  =   pathinfo($object_name, PATHINFO_FILENAME);
            $input_key                      =   "estranscodeinput/$object_name";

            $time                           =   time();
            $output_key_prefix              =   "estranscodeoutput/$object_name_without_extension/$time/";


            # HLS Presets that will be used to create an adaptive bitrate playlist.
            $hls_64k_audio_preset_id    = '1351620000001-200071';
            $hls_0400k_preset_id        = '1351620000001-200050';
            $hls_0600k_preset_id        = '1351620000001-200040';
            $hls_1000k_preset_id        = '1351620000001-200030';
            $hls_1500k_preset_id        = '1351620000001-200020';
            $hls_2000k_preset_id        = '1351620000001-200010';
            $hls_3000k_preset_id        = '1528886755093-m12cfg';

            $hls_presets = array(
                'hlsAudio' => $hls_64k_audio_preset_id,
                'hls0400k' => $hls_0400k_preset_id,
                'hls0600k' => $hls_0600k_preset_id,
                'hls1000k' => $hls_1000k_preset_id,
                'hls1500k' => $hls_1500k_preset_id,
                'hls2000k' => $hls_2000k_preset_id,
                'hls3000k' => $hls_3000k_preset_id,
            );

            # HLS Segment duration that will be targeted.
            $segment_duration = '2';

            #All outputs will have this prefix prepended to their output key.
//        $output_key_prefix = 'elastic-transcoder-samples/output/hls/';

            # Setup the job input using the provided input key.
            $input = array('Key' => $input_key);

            #Setup the job outputs using the HLS presets.
//        $output_key = hash('sha256', utf8_encode($input_key));
            $output_key = $object_name_without_extension;

            # Specify the outputs based on the hls presets array spefified.
            $outputs = array();
            foreach ($hls_presets as $prefix => $preset_id) {

                $output = array('Key' => "$prefix/$output_key", 'PresetId' => $preset_id, 'SegmentDuration' => $segment_duration);

                array_push($outputs, $output);
            }

            # Setup master playlist which can be used to play using adaptive bitrate.
            $playlist = array(
                'Name' => 'hls_playlist_' . $output_key,
                'Format' => 'HLSv3',
                'OutputKeys' => array_map(function($x) { return $x['Key']; }, $outputs)
            );

            $pipeline_id = '1506604398556-9kot7i'; //for testvideo   pipeline
            //$pipeline_id = '1509097697904-qekkft'; //for armsvideos pipeline

            # Create the job.
            $create_job_request = array(
                'PipelineId' => $pipeline_id,
                'Input' => $input,
                'Outputs' => $outputs,
                'OutputKeyPrefix' => $output_key_prefix,
                'Playlists' => array($playlist)
            );
//        print_pretty($create_job_request);

            $create_job_result = $ElasticTranscoder->createJob($create_job_request)->toArray();
            $job = $create_job_result['Job'];
            $job_id         =   $job['Id'];
            $job_status     =   trim(strtolower($job['Status']));
//            print_pretty($job);

            $base_output_url        =   "https://s3-ap-south-1.amazonaws.com/tests-output/";
            $output_playlist_url    =   $base_output_url.$output_key_prefix."hls_playlist_".$output_key.".m3u8";

            echo "<br><br>";
            echo "#################################################################";
            echo "<br>OUTPUT PLAYLIST URL :  $output_playlist_url";
            echo "<br>JOB ID :  $job_id";
            echo "<br>JOB STATUS :  $job_status";


        }//foreach


    }


    public function vodJobCreate()
    {


        $ElasticTranscoder = ElasticTranscoderClient::factory([
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY')
            ],
            'region' => 'ap-south-1',
            'version' => '2012-09-25',
        ]);

//        $listPipelines = $ElasticTranscoder->listPipelines();
////      $listPresets    = $ElasticTranscoder->listPresets();
////      print_pretty($ElasticTranscoder);
//        print_pretty($listPipelines);
////      print_pretty($listPresets);
//        exit;


        $job = $ElasticTranscoder->createJob([
            'PipelineId' => '1506604398556-9kot7i',
            'Input' => [
                'Key' => 'bunny.mp4',
                'FrameRate' => 'auto',
                'Resolution' => 'auto',
                'AspectRatio' => 'auto',
                'Interlaced' => 'auto',
                'Container' => 'auto',
            ],
            'Outputs' => [
                [
                    'Key' => 'myOutput.mp4',
                    'PresetId' => '1351620000001-000050'
                ]
            ],
            'OutputKeyPrefix' => 'outputfoldername/'    //output folder name
        ]);

        // get the job data as array
        $jobData = $job->get('Job');

        // you can save the job ID somewhere, so you can check the status from time to time.
        $jobId = $jobData['Id'];
        $jobStatus = $jobData['Status'];


        echo "<br>    JOB ID - " . $jobId . "JOB Status - " . $jobStatus;


    }


    public function vodJobStatus($jobId)
    {

        $responseData = $this->elasticTranscoderService->getJobStatus($jobId);

        print_pretty($responseData);exit;

        $ElasticTranscoder = ElasticTranscoderClient::factory([
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY')
            ],
            'region' => 'ap-south-1',
            'version' => '2012-09-25',
        ]);

        $jobStatusRequest = $ElasticTranscoder->readJob(array('Id' => trim($jobId)));
        $jobData = $jobStatusRequest->get('Job');
//        print_pretty($jobStatusRequest);exit;
        print_pretty($jobStatusRequest);

        if ($jobData['Status'] !== 'progressing' && $jobData['Status'] !== 'submitted') {
            echo $jobData['Status'];
        }

    }



    public function getListOfPresets()
    {
        return $responseData = $this->elasticTranscoderService->getListOfPresets();
    }

    public function getPreset($presetid)
    {
        return $responseData = $this->elasticTranscoderService->getPreset($presetid);
    }


    public function initClient($debug = false)
    {
        $base_uri = 'https://graph.facebook.com/';
        $debug = ($debug) ? $debug : $this->debug;
        $this->client = new Client(['debug' => $debug, 'base_uri' => $base_uri]);
    }


    public function gcpAuth()
    {
        $storage = $this->gcp->auth();
        print_pretty($storage);
        exit;
    }


    public function gcpBuckets()
    {

        $gcp_buckets = [];
        $buckets = $this->gcp->buckets();
        foreach ($buckets as $bucket) {
            array_push($gcp_buckets, $bucket->name());
        }

        return $gcp_buckets;
    }


    public function gcpCreateBucket()
    {
        $bucket = $this->gcp->createBucket();
        print_pretty($bucket);
    }


    public function gcpUploadFile()
    {
        $file = $this->gcp->uploadLocalFile();
        print_pretty($file);
    }


    public function twitterUserTimeline()
    {
//        $settings = [
//            'oauth_access_token'        => "186795815-k9U1K5wpZVKpUomACqlJorKEMVYyMiIJ39xZJ1H3",
//            'oauth_access_token_secret' => "GMZC38T43ytP00w6w2vuZz9YkcdOjDEAR3DplS8yWIV9O",
//            'consumer_key'              => "AvfwGaLV2JQZTj15f8NgHLAUI",
//            'consumer_secret'           => "UJC62R8R0Adhqmky8WvrtOstXy64eMF3wnbkiZEj69ug42wxLu"
//        ];
//
//        $url                = "https://api.twitter.com/1.1/statuses/user_timeline.json";
//        $getfield           = '?screen_name=ipoonampandey';
//        $requestMethod      = 'GET';


        $settings = [
            'oauth_access_token' => "464798137-w0uJfIqyIPRNF0q3SlFFIEDlGj2eGCuamdc9ZBnj",
            'oauth_access_token_secret' => "TWkMLDbU8UfLRVtBiasIHSnOVtldF7LxGyIcnlnbDJ5dX",
            'consumer_key' => "WtvBR0ilt2ED3dDmSVFMBPjur",
            'consumer_secret' => "ZyNVGFoenseU6SN0Q2NO63I7J6TX8tFTTjnb7GN5EhBMi4zui3"
        ];

        $url = "https://api.twitter.com/1.1/statuses/user_timeline.json";
        $getfield = '?screen_name=zareen_khan';
        $requestMethod = 'GET';

        $twitter = $this->twitterservice->init($settings);
        $items = json_decode($this->twitterservice->setGetfield($getfield)->buildOauth($url, $requestMethod)->performRequest());

//        print_pretty($items);exit;

        return $items;

    }


    public function facebookPosts()
    {


//        ['name' => 'Poonam Pandey',  'artist_id' => '598aa3d2af21a2355d686de2', 'user_name' => 'iPoonampandey11', 'bucket_id' => '598c08dfaf21a23dcb3986e2','api_key'=>'1418058401546557','api_secret'=>'fa6092bc98fa9e72307423ef598a3bb1'],


        //Poonam Pandey
//        $api_key            =   '1418058401546557';
//        $api_secret         =   'fa6092bc98fa9e72307423ef598a3bb1';
//        $user_name          =   'iPoonampandey11';
//        $limit              =   25;

        //Poonam Pandey
        $api_key = '1979415768983520';
        $api_secret = '81ae2458197f5ea47e919b9bd072b10d';
        $user_name = 'ZareenKhanOfficial';
        $limit = 25;


        $url = "$user_name?fields=id,name,picture,posts.limit($limit){message,id,created_time,updated_time,full_picture,picture,type,attachments,likes.summary(true),comments.summary(true)}&access_token=$api_key|$api_secret";
        $response = $this->client->get($url)->getBody()->getContents();
        $responseBody = json_decode($response, true);

        return $responseBody;
    }


    public function sendNotificationToDevice()
    {


        $device_token_lax = 'cIDQW0QsjoM:APA91bG1y4Nwo6p25_1u2e5Vpg0s-jA_17Jrtnu1LA62dOgAp_qG_Otbt6u4EsuchBQ5G2tmUdpPlW5ezeHOndnS-UoYEimSdeZqzzxWNniGIOzTa4fA4hQSDpVfw8wVlbwhbLxVBCyQ';
        $device_token_san = 'f1T0c2uCew4:APA91bGoZY7GhgEhwbgvs_UvAZesW_RS7eDj38oEsDwGF8qOkIj2S-NSOC-xmGmPMIIEtTghSfmSlYmNgTkJNdfrp8WE1_S2zuasju8L80-5u0ihIJKWBWRTImWqzOyNt3C4FuKO6G7G';
        $params = [
            'device_token' => $device_token_lax,
            'title' => 'Notification To Device Title',
            'body' => 'Notification To Device Body Goes here ....',
        ];

        $response = $this->pushnotification->sendNotificationToDevice($params);
//        $responseBody       =   json_decode($response, true);
        return $response;

    }


    public function subscribeUserToTopic()
    {

        $device_token_lax = 'cIDQW0QsjoM:APA91bG1y4Nwo6p25_1u2e5Vpg0s-jA_17Jrtnu1LA62dOgAp_qG_Otbt6u4EsuchBQ5G2tmUdpPlW5ezeHOndnS-UoYEimSdeZqzzxWNniGIOzTa4fA4hQSDpVfw8wVlbwhbLxVBCyQ';
        $device_token_san = 'f1T0c2uCew4:APA91bGoZY7GhgEhwbgvs_UvAZesW_RS7eDj38oEsDwGF8qOkIj2S-NSOC-xmGmPMIIEtTghSfmSlYmNgTkJNdfrp8WE1_S2zuasju8L80-5u0ihIJKWBWRTImWqzOyNt3C4FuKO6G7G';

        $device_token_lax = 'c8FAmw9ANYI:APA91bGaxwkQSNLrHVSnbxEVzMdHCdM69-clmdLz-czCh8ofYPv129fmy7U2TzIvhBHn24hqxdeu4D3VPQY8T1yaIokElo8-X9HyX3syKcsxtG-JshHocBf7QpOh96B8Ff59Rs9jOdgC';
        $topic_id = 'karankundrratest';
        $params = [
            'artist_id' => '5a3373be9353ad4b0b0c2242',
            'device_token' => $device_token_lax,
            'topic_id' => $topic_id
        ];

        $response = $this->pushnotification->subscribeUserToTopic($params);
//        $responseBody       =   json_decode($response, true);
        return $response;
    }


    public function sendNotificationToTopic()
    {


        $topic_id = 'zareenkhan';
        $params = [
            'topic_id' => $topic_id,
            'title' => 'Notification Title Using Topic',
            'body' => 'Notification Body Using Topic',
        ];


        $response = $this->pushnotification->sendNotificationToTopic($params);
//        $responseBody       =   json_decode($response, true);
        return $response;
    }


    public function subscribeZareenKhanUserToTopic($test = true)
    {

        ini_set('memory_limit', '500M');
        set_time_limit(3000);

        if ($test == 'true') {
            $testcustomers = Config::get('app.test_customers');
            $topic_id = 'zareenkhantest';
            $customers = \App\Models\Customer::whereIn('email', $testcustomers)->get();
            print_pretty($testcustomers);
        } else {
            $topic_id = 'zareenkhan';
            $customers = \App\Models\Customer::where('fcm_id', 'exists', true)->whereNotIn('fcm_id', ['fcm_id', ''])->get();
        }

//      var_dump($test);return $topic_id;

        foreach ($customers as $customer) {
            $customer_id = (isset($customer['_id']) && $customer['_id'] != '') ? trim($customer['_id']) : "";
            $artist_id = '59858df7af21a2d01f54bde2';
            $topic_id = $topic_id;

            //Subscribe To Artist Topic
            $customerDeviceinfo = \App\Models\Customerdeviceinfo::where('customer_id', '=', $customer_id)->where('artist_id', '=', $artist_id)->first();
            if ($customerDeviceinfo && $customer_id != '' && $artist_id != '') {
                $fcm_device_token = (isset($customerDeviceinfo['fcm_id']) && $customerDeviceinfo['fcm_id'] != '') ? trim($customerDeviceinfo['fcm_id']) : "";
                if ($fcm_device_token != "" && $topic_id != "") {
                    $params = [
                        'device_token' => $fcm_device_token,
                        'topic_id' => $topic_id
                    ];
                    $response = $this->pushnotification->subscribeUserToTopic($params);
                }
            }//if customerDeviceinfo
        }//foreach

        echo "done";
        exit;

    }

    public function crossdomain()
    {
        $viewdata = [];
        return view('admin.debug.crossdomain', $viewdata);
    }


    public function phpinfo()
    {
        return phpinfo();
    }

    public function shareOnSocialMedia($debug = false)
    {

        $username = 'Razrtestaccount';
        $password = 'laxman@1234';
        $debug = true;
        $truncatedDebug = false;
//////////////////////
/////// MEDIA ////////
        $photoFilename = '';
        $captionText = '';
//////////////////////
//$ig = new \InstagramAPI\Instagram($debug, $truncatedDebug);


        try {
            // $ig->login($username, $password);

            // dd($ig);
        } catch (\Exception $e) {
            // echo 'Something went wrong: '.$e->getMessage()."\n";
            exit(0);
        }

        try {

            //  $videoFilename=public_path('small.mp4');
            //  $video = new \InstagramAPI\Media\Video\InstagramVideo($videoFilename);
            //  $this->instagramupload->UploadVideo($videoFilename, '',"Test Upload Photo From PHP");
            //  $ig->timeline->uploadVideo($video->getFile(), ['caption' => $captionText]);

            $url = 'http://hdwallpapers4us.com/wp-content/uploads/2018/01/Nature-HD-Wallpapers-Android.jpg';
            $img = public_path('demo.jpg');
            file_put_contents($img, file_get_contents($url));
            // $fn= public_path('demo.jpg');
            // $info = getimagesize($fn);

            // /* Calculate aspect ratio by dividing height by width */
            // $aspectRatio = $info[1] / $info[0];
            // if($aspectRatio >= 0.800 and $aspectRatio <= 0.910)
            // {
            //      //$ig->timeline->uploadPhoto($fn, ['caption' => $captionText]);
            // }
            // else{

            // $width = 450;
            // $height = 450 * 0.850;

            // $src = imagecreatefromstring(file_get_contents($fn));
            // $dst = imagecreatetruecolor($width,$height);
            // imagecopyresampled($dst,$src,0,0,0,0,$width,$height,$info[0],$info[1]);
            // imagedestroy($src);
            // imagejpeg($dst,public_path('result.jpg')); // adjust format as needed
            // imagedestroy($dst);
            // $photoFilename=public_path('result.jpg');

            // $ig->timeline->uploadPhoto($photoFilename, ['caption' => $captionText]);
            //}

            $this->instagramupload->login('Razrtestaccount', 'laxman@1234');
            $this->instagramupload->UploadPhoto($img, "Test Upload Photo From PHP");


            //dd($photo);


        } catch (\Exception $e) {
            echo 'Something went wrong: ' . $e->getMessage() . "\n";
        }
        $facebook = new \Facebook\Facebook();


    }

    public function demo()
    {
        $client_id = '366975773677138';
        $client_secret = '6c1bc0e9f8c4338caf2b7bc57bf8f1dc';
        $page_access_token = 'EAACEdEose0cBACJo7p5ZBLJVmlZCeqCc1SntxDXwdVL40W8WrivNfSIA3Po6ec9oeR5WtZAfROCIRmSyTfAstcIZCZBPZAEVe7u00mvpIdTa3EdkkXxgMFkxlHKkaPcip5637OoRauRVD49W0aEfdlZCZCvkX5PHMk8maN52tL1ZASJmQPnorzvxZBkdbxyfzbNgUZD';

        $facebook = new \Facebook\Facebook();
        $helper = $facebook->getRedirectLoginHelper();
        $permissions = ['manage_pages, read_stream']; // Optional permissions
        $loginUrl = $helper->getLoginUrl('http://arms.local/shareonsocialmediapost', $permissions);
        echo $loginUrl;
        // $response  = $facebook->get(
        //     '/me/accounts',
        //     $page_access_token
        //  );


        //dd($response);

    }

    public function fbpost()
    {
        $helper = $fb->getRedirectLoginHelper();

        try {
            $accessToken = $helper->getAccessToken();
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            // When Graph returns an error
            echo 'Graph returned an error: ' . $e->getMessage();
            exit;
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            // When validation fails or other local issues
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            exit;
        }

//$cb->setUseCurl(false);
        $file = public_path('small.mp4');
        $size_bytes = filesize($file);
        $fp = fopen($file, 'r');

        $cb->statuses_update();
        $result = $cb->media_upload([
            'command' => 'INIT',
            'media_type' => 'video/mp4',
            'total_bytes' => $size_bytes
        ]);
        //dd($result);
        $media_id = $result->media_id_string;

        $segment_id = 0;

        while (!feof($fp)) {
            $chunk = fread($fp, 1048576); // 1MB per chunk for this sample

            $reply = $cb->media_upload([
                'command' => 'APPEND',
                'media_id' => $media_id,
                'segment_index' => $segment_id,
                'media' => $chunk
            ]);

            $segment_id++;
        }

        fclose($fp);

        // FINALIZE the upload

        $reply = $cb->media_upload([
            'command' => 'FINALIZE',
            'media_id' => $media_id
        ]);

        var_dump($reply);

        if ($reply->httpstatus < 200 || $reply->httpstatus > 299) {
            die();
        }

        // if you have a field `processing_info` in the reply,
        // use the STATUS command to check if the video has finished processing.


        // Now use the media_id in a Tweet
        $reply = $cb->statuses_update([
            'status' => 'Twitter now accepts video uploads.',
            'media_ids' => $media_id
        ]);
    }

    public function purchaseReport()
    {


        $viewtData = [];
        $artist_role_ids = \App\Models\Role::where('slug', 'artist')->lists('_id');
        $artist_role_ids = ($artist_role_ids) ? $artist_role_ids->toArray() : [];
        $artists = \App\Models\Cmsuser::whereIn('roles', $artist_role_ids)->get(['_id', 'first_name', 'last_name', 'email']);

        $aritstWisePurchase = [];


        $datetime_start = date("d-m-Y H:i:s", strtotime('-3 days', time()));
        $datetime_end = date("d-m-Y H:i:s", time());


        foreach ($artists as $artist) {

            $artist_id = $artist['_id'];
            $artistData = [
                'first_name' => (isset($artist['first_name'])) ? $artist['first_name'] : "",
                'last_name' => (isset($artist['last_name'])) ? $artist['last_name'] : "",
            ];
            $packages = \App\Models\Order::where('created_at', '>=', new \DateTime(date("d-m-Y H:i:s", strtotime($datetime_start))))
                ->where('created_at', '<=', new \DateTime(date("d-m-Y H:i:s", strtotime($datetime_end))))
                ->whereIn('artist_id', [$artist_id])->lists('package_id');
            $package_ids = ($packages) ? $packages->toArray() : [];

            $total_purchase = \App\Models\Package::whereIn('_id', $package_ids)->sum('price');
            $artistData['total_purchase'] = $total_purchase;
            array_push($aritstWisePurchase, $artistData);
        }

        $viewtData['aritstwise_purchase'] = $aritstWisePurchase;

        print_pretty($viewtData);
        exit;


        return $viewtData;

    }


    public function predisSimpleReplication()
    {


//        $parameters = array(
//            'tcp://127.0.0.1:6379?database=15&alias=master',
//            'tcp://127.0.0.1:6380?database=15&alias=slave',
//        );
//        $options = array('replication' => true);
//        $client = new Predis\Client($parameters, $options);

//armsusersredis

        $secretpassword = "vaKk49isQV4M";


        $parameters = array(
            'tcp://104.155.211.248:6379?alias=slave',
            'tcp://104.155.204.121:6379?alias=master',
            'tcp://35.229.156.174:6379?alias=slave',
        );

        $options = array('replication' => true, 'parameters' => ['password' => $secretpassword]);
        $client = new \Predis\Client($parameters, $options);

        try {
            $client = new \Predis\Client($parameters, $options);
            echo "Successfully connected to Redis";
        } catch (\Exception $e) {
            echo "Couldn't connected to Redis";
            echo $e->getMessage();
        }

        $isConnected = ($client->isConnected()) ? "true" : "false";
        echo "<br>isConnected $isConnected.", PHP_EOL;


        // Read operation.
        $exists = $client->exists('sentinelkey') ? 'yes' : 'no';
        $current = $client->getConnection()->getCurrent()->getParameters();
        echo "<br>Does 'foo' exist on {$current->alias}? $exists.", PHP_EOL;

        // Write operation.
        $client->set('sentinelkey', 'sentinelvsl');
        $current = $client->getConnection()->getCurrent()->getParameters();
        echo "<br>Now 'sentinelkey' has been set to 'sentinelvsl' on {$current->alias}!", PHP_EOL;

        // Read operation.
        $bar = $client->get('sentinelkey');
        $current = $client->getConnection()->getCurrent()->getParameters();
        echo "<br>We fetched 'sentinelkey' from {$current->alias} and its value is '$bar'.", PHP_EOL;

//        print_pretty();var_dump($client);print_pretty();
    }


    public function predisSentinelReplication()
    {


//        $parameters = array(
//            'tcp://127.0.0.1:6379?database=15&alias=master',
//            'tcp://127.0.0.1:6380?database=15&alias=slave',
//        );
//        $options = array('replication' => true);
//        $client = new Predis\Client($parameters, $options);

//armsusersredis

        $secretpassword = "vaKk49isQV4M";

//        $sentinels = array(
//            'tcp://104.155.211.248:6379?timeout=0.100',
//            'tcp://104.155.204.121:6379?timeout=0.100',
//            'tcp://35.229.156.174:6379?timeout=0.100',
//        );
//        $sentinels = array(
//            'tcp://104.155.211.248:26379',
//            'tcp://104.155.204.121:26379',
//            'tcp://35.229.156.174:26379',
//        );

        $sentinels = array(
            'tcp://104.155.211.248:26379?timeout=0.100',
            'tcp://104.155.204.121:26379?timeout=0.100',
            'tcp://35.229.156.174:26379?timeout=0.100',
        );
//        $options    = [
//            'replication' => 'sentinel',
//            'service' => 'armsusersredis',
//            'parameters' => ['password' => $secretpassword]
//        ];

        $options = [
            'replication' => 'sentinel',
            'service' => 'armsusersredis',
            'parameters' => []
        ];

        try {
            $client = new \Predis\Client($sentinels, $options);
            echo "Successfully connected to Redis";
        } catch (\Exception $e) {
            echo "Couldn't connected to Redis";
            echo $e->getMessage();
        }

        $isConnected = ($client->isConnected()) ? "true" : "false";
        echo "<br>isConnected $isConnected.", PHP_EOL;


        // Read operation.
        $exists = $client->exists('foo') ? 'yes' : 'no';
        $current = $client->getConnection()->getCurrent()->getParameters();
        echo "Does 'foo' exist on {$current->alias}? $exists.", PHP_EOL;

        // Write operation.
        $client->set('foo', 'barval');
        $current = $client->getConnection()->getCurrent()->getParameters();
        echo "Now 'foo' has been set to 'bar' on {$current->alias}!", PHP_EOL;

        // Read operation.
        $bar = $client->get('foo');
        $current = $client->getConnection()->getCurrent()->getParameters();
        echo "We fetched 'foo' from {$current->alias} and its value is '$bar'.", PHP_EOL;

        /* OUTPUT:
        Does 'foo' exist on slave-127.0.0.1:6381? yes.
        Now 'foo' has been set to 'bar' on master!
        We fetched 'foo' from master and its value is 'bar'.
        */

        var_dump($client);


    }




    public function awsRediscluster()
    {

        try{
            // Put your AWS ElastiCache Configuration Endpoint here.
            $servers  = ['armsdevrediscluster.bfvxjo.clustercfg.aps1.cache.amazonaws.com:6379'];
            // Tell client to use 'cluster' mode.
            $options  = ['cluster' => 'redis'];
            // Create your redis client
            $redis = new \Predis\Client($servers, $options);

            // Do something you want:
            // Set the expiration for 7 seconds
            $redis->set("tm", "I have data for 7s.");
            $redis->expire("tm", 7);
            $ttl = $redis->ttl("tm"); // will be 7 seconds

            // Print out value of the key 'tm'
            var_dump(array("msg"=>"Successfully connected to Redis Cluster.", "val"=>$redis->get("tm"))) ;

            echo $redis->get("name");
            echo $redis->get("a");

        }
        catch(Exception $ex){
            echo ('Error: ' . $ex->getMessage() ); // output error message.
        }


    }

    public function laravelAwsRediscluster()
    {

        Cache::put('bar', 'baz', 10);
        $value = Cache::get('bar');
        var_dump($value);

        Cache::put('name', 'jay', 10);
        $value = Cache::get('name');
        var_dump($value);




    }


    public function predisCluster()
    {
        $secretpassword = "vaKk49isQV4M";


        $parameters = array(
            'tcp://104.198.165.71:7011',
            'tcp://35.184.206.245:7012',
            'tcp://35.184.138.5:7012',
            'tcp://35.226.146.21:7001',
            'tcp://35.232.129.141:7002',
            'tcp://35.194.5.124:7003',
        );
//        $options    = ['cluster' => 'redis','parameters' => ['password' => $secretpassword]];
        $options = ['cluster' => 'redis', 'parameters' => []];


        $client = new \Predis\Client($parameters, $options);

        try {
            $client = new \Predis\Client($parameters, $options);
            echo "Successfully connected to Redis";
        } catch (\Exception $e) {
            echo "Couldn't connected to Redis";
            echo $e->getMessage();
        }


//        var_export($client->keys('*'));
//        echo PHP_EOL;


        // Plain old SET and GET example...
        $client->set('library', 'predis');
        echo PHP_EOL;
        $response = $client->get('library');
        var_export($response);
        echo PHP_EOL;
        $response = $client->get('a');
        var_export($response);
        echo PHP_EOL;
        $response = $client->get('b');
        var_export($response);
        echo PHP_EOL;
        $response = $client->get('c');
        var_export($response);
        echo PHP_EOL;
        $response = $client->get('name');
        var_export($response);
        echo PHP_EOL;

//        $response = $client->keys('*'); var_export($response); echo PHP_EOL;


        print_pretty();
        var_dump($client);
        print_pretty();


//        // Read operation.
//        $exists = $client->exists('clusterkey') ? 'yes' : 'no';
////        $current = $client->getConnection()->getCurrent()->getParameters();
////        echo "<br>Does 'foo' exist on {$current->alias}? $exists.", PHP_EOL;
//
//        // Write operation.
//        $client->set('clusterkey', 'clusterval');
////        $current = $client->getConnection()->getCurrent()->getParameters();
////        echo "<br>Now 'sentinelkey' has been set to 'sentinelvsl' on {$current->alias}!", PHP_EOL;
//
//        // Read operation.
//        echo $bar = $client->get('clusterkey');
////        $current = $client->getConnection()->getCurrent()->getParameters();
////        echo "<br>We fetched 'sentinelkey' from {$current->alias} and its value is '$bar'.", PHP_EOL;
//
////        print_pretty();var_dump($client);print_pretty();

    }

    public function uploadKrakenToGCP()
    {
//        $kraken = new Kraken("c8b87d673cfc9c5ef00c69b7054a9c58", "7fdcee1b1a0b19814df82a870d32fdc370269869");


        $file = 'https://www.gettyimages.ie/gi-resources/images/Homepage/Hero/UK/CMS_Creative_164657191_Kingfisher.jpg'; //For Image URL

//        $file = public_path('uploads/contents/c/1526454437_tmpphppigic2.jpg'); //For Upload image file

        $params = array(
            "url" => $file,    //For Image URL
//            "file" => $file,      //For Upload image file
            "wait" => true,
            "resize" => [
//                [
//                    "id" => 1,
//                    "width" => 350,
//                    "height" => 350,
//                    "strategy" => "exact"
//                ],
                [
                    "id" => 2,
                    "height" => 350,
                    "strategy" => "portrait"
                ],
//                [
//                    "id" => 3,
//                    "width" => 350,
//                    "strategy" => "landscape"
//                ],
//                [
//                    "id" => 4,
//                    "width" => 350,
//                    "height" => 350,
//                    "strategy" => "auto"
//                ],
//                [
//                    "id" => 5,
//                    "width" => 700,
//                    "height" => 300,
//                    "strategy" => "fit"
//                ],
//                [
//                    "id" => 6,
//                    "width" => 700,
//                    "height" => 300,
//                    "strategy" => "crop",
//                    "scale" => 50
//                ]
            ],
            "convert" => array(
                "format" => "png",
                "background" => "#ff0000"
            ),
            "lossy" => true
        );

        $data = $this->kraken->url($params);  //For Image URL
//        $data = $kraken->upload($params);  //For Upload image file
        print_b($data);
//        $data['results'] = [
//            [
//                'file_name' => 'CMS_Creative_164657191_Kingfisher.jpg',
//                'original_size' => 221569,
//                'kraked_size' => 6597,
//                'saved_bytes' => 214972,
//                'kraked_url' => 'https://dl.kraken.io/api/ab/3e/fb/883420ec9e350e3ba2e507b337/CMS_Creative_164657191_Kingfisher.png',
//                'original_width' => 1140,
//                'original_height' => 550,
//                'kraked_width' => 100,
//                'kraked_height' => 75,
//                'success' => 1,
//            ]
//        ];


        foreach ($data['results'] as $key => $val) {
            $imageUrl = $val['kraked_url'];

            header('Content-Type: application/octet-stream');
            header("Content-Transfer-Encoding: Binary");
            readfile($imageUrl);
            sleep(2);
        }
//        exit;

//        $data = [
//            'file_name' => 'CMS_Creative_164657191_Kingfisher.jpg',
//            'original_size' => 221569,
//            'kraked_size' => 6597,
//            'saved_bytes' => 214972,
//            'kraked_url' => 'https://dl.kraken.io/api/ab/3e/fb/883420ec9e350e3ba2e507b337/CMS_Creative_164657191_Kingfisher.png',
//            'original_width' => 1140,
//            'original_height' => 550,
//            'kraked_width' => 100,
//            'kraked_height' => 75,
//            'success' => 1,
//        ];


//        @$rawImage = file_get_contents($imageUrl);
//
//        if ($rawImage) {
//            file_put_contents("images" . 'dummy1.png', $rawImage);
//            echo "Image saved";
//        }
//        die;

    }


    public function status()
    {
        $data = array('auth' => array(
            'api_key' => '1c260523d876c9000c5091c807bb6348',
            'api_secret' => '1e565d437d59396d07f761b14dff8af048c2cc88'
        ));

        $response = self::request(json_encode($data), "https://api.kraken.io/user_status");
        print_b($response);
        return $response;
    }

    private function request($data, $url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_FAILONERROR, 0);
        $response = json_decode(curl_exec($curl), true);
        $error = curl_errno($curl);
        curl_close($curl);
        if ($error > 0) {
            throw new \RuntimeException(sprintf('cURL returned with the following error code: "%s"', $error));
        }
        return $response;
    }



    /**
     * Debug Function testing something
     *
     * @return Response
     */
    public function something() {
        echo __METHOD__ . "<br /> <pre>";
        echo rand(1000, 9999) . "<br />";;
        $ret = null;

        echo '$config ' . "<br />";
        $config = Config::get('database');
        print_pretty($config);

        $roles = \App\Models\Role::get();
        print_pretty($roles);

        /*
        // Covert Mongo numberLong to Data Time
        $date_json  = '{ "date" : { "$date" : { "$numberLong" : "1445990400000" } } }';
        $date_bson  = \MongoDB\BSON\fromJSON($date_json);
        $mongo_date = \MongoDB\BSON\toPHP($date_bson);
        if($mongo_date) {
            echo 'DATE : ' . "<br />";
            print_r($mongo_date->date->toDateTime());
        }

        $data = [
            'first_name' => '  amit',
            'last_name' => '  PATIL',
        ];
        dd(generate_fullname($data));

        $duration  = ' 1::1 ';
        $multiplication_matrix = [
            3600000,     // Hour to millisecond
            60000,      // Minute to millisecond
            1000,       // Second to millisecond
        ];

        $duration = trim($duration);
        $duration_arr = explode(':', $duration);
        print_r($duration_arr);
        if($duration_arr) {

            for ($i=count($duration_arr); $i > 0 ; $i--) {
                $arr_index = $i - 1;
                $val = intval($duration_arr[$arr_index]);

                echo  $i . ' : ' . $val . "<br />";

                if($val) {
                    $ret = $ret + ($val * $multiplication_matrix[$arr_index]);
                    echo '$ret :' . $ret . "<br />";
                }

            }
        }
        */
        echo '$ret :' . $ret . "<br />";
        echo "<br /> ------- END ------- <br />";
    }

    public function testVideoTranscodeSubtitle() {
        echo __METHOD__ . "<br /> <pre>";
        $langs = $this->serviceLanguage->findActiveBy('eng', 'code_3');
        dd($langs);
        /*
        $captions = [];
        $captions[] = [
            'language'          => 'eng',
            'language_label'    => 'English',
            'object_name'       => '1562765026_tmpphpidcswo.vtt',
            'object_path'       => '5d25e6df479af1562765023/subtitles/eng_1562765026_tmpphpidcswo.vtt',
            'object_extension'  => 'vtt',
        ];

        $captions[] = [
            'language'          => 'hin',
            'language_label'    => 'Hindi',
            'object_name'       => '1562765026_tmpphp02hrso.vtt',
            'object_path'       => '5d25e6df479af1562765023/subtitles/hin_1562765026_tmpphp02hrso.vtt',
            'object_extension'  => 'vtt',
        ];

        //1562765023_tmpphpkult0e.mp4
        $params     = [
            //'s3_input_file' => '1562665112_tmpphpgbcsks.mp4',
            's3_input_file' => '1562765023_tmpphpkult0e.mp4',
            'unique_id'     => uniqid().rand(10000, 99999),
            'captions'      => $captions,
        ];


        $responseData = $this->elasticTranscoderService->createHlsVodJob($params);
        echo "<br />END <br />";
        dd($responseData);
        */
    }





public function signedurl(Request $request)
{

echo "signedurl";


}


public function signedUrlPhoto(Request $request)
{



}


public function signedUrlVideo(Request $request)
{



}


public function signedCookiesPhoto(Request $request)
{



}



public function signedCookiesVideo(Request $request)
{



}



}
