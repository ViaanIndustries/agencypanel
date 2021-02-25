<?php

namespace App\Services;

use Input;
use Redirect;
use Config;
use Session;
use App\Repositories\Contracts\CaptureInterface;
use App\Models\Capture as Capture;
use Carbon\Carbon;

use App\Services\ArtistService;

class CaptureService
{
    protected $repObj;
    protected $role;
    protected $artistService;

    public function __construct(Capture $capture, CaptureInterface $repObj, ArtistService $artistService)
    {
        $this->capture = $capture;
        $this->repObj  = $repObj;
        $this->artistService = $artistService;
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

        if(empty($error_messages)){

            array_set($data,'status','pending');

            $catpure               =   $this->repObj->store($data);


            $results['catpure']    =   $catpure;


            if(!empty($catpure)){

                $catpure_id         =   (!empty($catpure['_id']) && $catpure['_id'] != '') ? $catpure['_id'] : "";
                $customer_name      =   (!empty($data['name']) && $data['name'] != '') ? $data['name'] : "";
                $customer_email     =   (!empty($data['email']) && $data['email'] != '') ? $data['email'] : "";
                $artist_id          =   (!empty($data['artist_id']) && $data['artist_id'] != '') ? $data['artist_id'] : "";
                $capture_type       =   (!empty($data['capture_type']) && $data['capture_type'] != '') ? $data['capture_type'] : "";
                $description        =   (!empty($data['description']) && $data['description'] != '') ? $data['description'] : "";
                $v                  =   (!empty($data['v']) && $data['v'] != '') ? $data['v'] : "";
                $platform           =   (!empty($data['platform']) && $data['platform'] != '') ? $data['platform'] : "";
                $transaction_id     =   (!empty($data['order_id']) && $data['order_id'] != '') ? $data['order_id'] : "";

                /*
                // Old Code For sending email
                $celeb_direct_app_download_link = '';
                $celeb_ios_app_download_link = '';
                $celeb_android_app_download_link = '';
                $celeb_recharge_wallet_link = '';

                $artist_config_info                 =   \App\Models\Artistconfig::with( 'artist')->where('artist_id', $artist_id)->first();
                $celeb_android_app_download_link    =   ($artist_config_info && !empty($artist_config_info['android_app_download_link'])) ? trim($artist_config_info['android_app_download_link']) : '';
                $celeb_ios_app_download_link        =   ($artist_config_info && !empty($artist_config_info['ios_app_download_link'])) ? trim($artist_config_info['ios_app_download_link']) : '';
                $celeb_direct_app_download_link     =   ($artist_config_info && !empty($artist_config_info['direct_app_download_link'])) ? trim($artist_config_info['direct_app_download_link']) : '';
                $artistname                         =   strtolower(@$artist_config_info['artist']['first_name']).''.strtolower(@$artist_config_info['artist']['last_name']);
                $celeb_recharge_wallet_link         =   "https://recharge.bollyfame.com/wallet-recharge/$artistname/$artist_id";

                $celebname                          =   Ucfirst(@$artist_config_info['artist']['first_name']) . ' ' . Ucfirst(@$artist_config_info['artist']['last_name']);


//                if(!empty($catpure_id) && $catpure_id != ''){
//                    $subject_line                       =   "Customer has opened a new Support case Capture id : $catpure_id for $celebname";
//                }else{
//                    $subject_line                       =   "Customer has opened a new Support case for $celebname";
//                }

                if(!empty($description) && $description != ''){
                    $subject_line                       =   str_limit($description, 100);
                }else{
                    $subject_line                       =   "Customer has opened a new Support case for $celebname";
                }


                $payload = Array(
                    'celeb_name' => $celebname,
                    'celeb_photo' => @$artist_config_info['artist']['photo'],

                    'celeb_android_app_download_link' => $celeb_android_app_download_link,
                    'celeb_ios_app_download_link' => $celeb_ios_app_download_link,
                    'celeb_direct_app_download_link' => $celeb_direct_app_download_link,
                    'celeb_recharge_wallet_link' => $celeb_recharge_wallet_link,

                    'customer_name' => $customer_name,
                    'customer_email' => $customer_email,

                    'capture_id' => $catpure_id,
                    'transaction_id' => $transaction_id,
                    'capture_type' => $capture_type,
                    'description' => $description,
                    'platform' => $platform,

                    'v' => $v,

                    'capture_date' => Carbon::parse($catpure['created_at'])->format('M j\\, Y h:i A'),

                    'email_header_template' => 'emails.common.header',
                    'email_body_template' => 'emails.customer.customercapture',
                    'email_footer_template' => 'emails.common.footer',
                    'email_subject' => $subject_line,
                    'user_email' =>  'info@bollyfame.com',
                    'user_name' =>  $customer_name,
                    'bcc_emailids' => []
                );
                */

                // New Code for Sending Email
                $celebname      = '';
                $subject_line   = 'Customer has opened a new Support case';
                $user_email     = '';

                $app_bcc_ids    = Config::get('product.' . env('PRODUCT') . '.mail.from');
                if($app_bcc_ids) {
                    $user_email = isset($app_bcc_ids['address']) ? $app_bcc_ids['address'] : 'info@bollyfame.com';
                }

                $payload = [];
                // Get Email Default Template Data
                if($artist_id) {
                    $payload = $this->artistService->getEmailTemplateDefaultData($artist_id);
                    if($payload) {
                        $celebname = isset($payload['celeb_name']) ? $payload['celeb_name'] : '';
                    }
                }

                if(!empty($description) && $description != ''){
                    $subject_line   = str_limit($description, 100);
                }
                else{
                    $subject_line   = 'Customer has opened a new Support case for ' . $celebname;
                }

                // Generate Email Template specific data
                //$payload['']  = '';
                $payload['customer_email']  = $customer_email;
                $payload['customer_name']   = $customer_name;

                $payload['capture_id']      = $catpure_id;
                $payload['transaction_id']  = $transaction_id;
                $payload['capture_type']    = $capture_type;
                $payload['description']     = $description;
                $payload['platform']        = $platform;
                $payload['v']               = $v;
                $payload['capture_date']    = Carbon::parse($catpure['created_at'])->format('M j\\, Y h:i A');


                $payload['email_header_template']   = 'emails.' . env('PRODUCT') . '.common.header';
                $payload['email_body_template']     = 'emails.' . env('PRODUCT') . '.customer.capture';
                $payload['email_footer_template']   = 'emails.' . env('PRODUCT') . '.common.footer';
                $payload['email_subject']           = $subject_line;
                $payload['user_email']              = $user_email;
                $payload['user_name']               = $customer_name;

                // Set Send Form data
                if($customer_email) {
                    $payload['reply_to']['address'] = trim($customer_email);

                    if($customer_name) {
                        $payload['reply_to']['name'] = trim($customer_name);
                    }
                }

                $jobData = [
                    'label'     => 'CustomerOpenTicket',
                    'type'      => 'process_email',
                    'payload'   => $payload,
                    'status'    => 'scheduled',
                    'delay'     => 0,
                    'retries'   => 0
                ];

                $recodset = new \App\Models\Job($jobData);
                $recodset->save();

            }
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function update($request, $id)
    {
        $data               =   array_except($request->all(),['_method','_token']);
        $error_messages     =   $results = [];
//        $slug               =   str_slug($data['title']);
//        array_set($data, 'slug', $slug);

        if(empty($error_messages)){
            $results['page']   = $this->repObj->update($data, $id);
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function destroy($id)
    {
        $results = $this->repObj->destroy($id);
        return $results;
    }


}
