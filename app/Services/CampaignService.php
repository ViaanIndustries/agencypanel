<?php

namespace App\Services;

use Input, Redirect, Config, Session, Hash;
use App\Repositories\Contracts\CampaignInterface;
use App\Repositories\Contracts\CmsuserInterface;
use Carbon\Carbon;
use App\Services\Gcp;
use App\Services\Notifications\PushNotification;



class CampaignService
{
    protected $repObj;
    protected $gcp;
    protected $pushnotification;


    public function __construct(CampaignInterface $repObj, Gcp $gcp, PushNotification $pushnotification)
    {
        $this->repObj               =   $repObj;
        $this->gcp                  =   $gcp;
        $this->pushnotification     =   $pushnotification;
    }


    public function index()
    {
        $results = $this->repObj->paginate();
        return $results;
    }

    public function find($id)
    {
        $results = $this->repObj->find($id);
        return $results;
    }


    public function sndCustomNotificationToCustomerByArtist($request)
    {
        $data               =   $request->all();

        $error_messages     =   $results = [];


        $cover_url          =   "";
        $icon_url           =   "";
        $artist_id          =   $data['artist_id'];

        if($request->hasFile('photo')) {

            //upload to local drive
            $upload         =   $request->file('photo');

            $folder_path    =   'uploads/';
            $img_path       =   public_path($folder_path);
            $imageName      =   time() .'_'. str_slug($upload->getRealPath()). '.' . $upload->getClientOriginalExtension();
            $fullpath       =   $img_path . $imageName;
            $upload->move($img_path, $imageName);
            chmod($fullpath, 0777);

            //upload to gcp
            $artist_id              =   $data['artist_id'];
            $object_source_path     =   $fullpath;
            $object_upload_path     =   $artist_id.'/notifications/cover/'.$imageName;
            $params                 =   ['object_source_path' => $object_source_path, 'object_upload_path' => $object_upload_path];
            $uploadToGcp            =   $this->gcp->localFileUpload($params);
            $cover_url              =   Config::get('gcp.base_url').Config::get('gcp.default_bucket_path').$object_upload_path;

            @unlink($fullpath);

            array_set($data, 'cover_url', $cover_url);
        }


        if($request->hasFile('icon')) {

            //upload to local drive
            $upload         =   $request->file('icon');

            $folder_path    =   'uploads/';
            $img_path       =   public_path($folder_path);
            $imageName      =   time() .'_'. str_slug($upload->getRealPath()). '.' . $upload->getClientOriginalExtension();
            $fullpath       =   $img_path . $imageName;
            $upload->move($img_path, $imageName);
            chmod($fullpath, 0777);

            //upload to gcp
            $artist_id              =   $data['artist_id'];
            $object_source_path     =   $fullpath;
            $object_upload_path     =   $artist_id.'/notifications/icon/'.$imageName;
            $params                 =   ['object_source_path' => $object_source_path, 'object_upload_path' => $object_upload_path];
            $uploadToGcp            =   $this->gcp->localFileUpload($params);
            $icon_url               =   Config::get('gcp.base_url').Config::get('gcp.default_bucket_path').$object_upload_path;
            $photo                  =   ['cover' => $cover_url, 'icon' => ''];

            @unlink($fullpath);
            array_set($data, 'icon_url', $icon_url);
        }

        $photo                  =   ['cover' => $cover_url, 'icon' => $icon_url];

        array_set($data, 'photo', $photo);

//        print_pretty($data);exit;

        $artist = \App\Models\Cmsuser::with('artistconfig')->where('_id', '=', $artist_id)->first();




        if(empty($error_messages) && $artist){
            $test                   =   (isset($data['test']) && $data['test'] != '') ? (string) $data['test'] : "true";
            if(env('APP_ENV', 'stg') == 'stg'){
                $test   =  "true";
            }

            $content_id             =   (isset($data['content_id']) && $data['content_id'] != '') ? trim($data['content_id']) : "";
            $deeplink               =   (isset($data['deeplink']) && $data['deeplink'] != '') ? trim($data['deeplink']) : "";
            $test_topic_id          =   (isset($artist['artistconfig']['fmc_default_topic_id_test']) && $artist['artistconfig']['fmc_default_topic_id_test'] != '') ? trim($artist['artistconfig']['fmc_default_topic_id_test']) : "";
            $production_topic_id    =   (isset($artist['artistconfig']['fmc_default_topic_id']) && $artist['artistconfig']['fmc_default_topic_id'] != '') ? trim($artist['artistconfig']['fmc_default_topic_id']) : "";

            $artistname     =   $artist->first_name.' '.$artist->last_name;

            $name           =   'Custom Notification Send by - '.$artistname;

            $topic_id       =   ($test == 'true') ? $test_topic_id : $production_topic_id;

            $stats          =   \Config::get('app.campaign_stats');

            $payload = [
                'title' => trim($data['title']),
                'body' => trim($data['body']),
                'photo' => $data['photo'],
            ];

            if($content_id != ''){
                $payload['content_id']   =   trim($content_id);
            }
    

            $campaignData = [
                'artist_id' => $artist_id,
                'topic_id'  => $topic_id,
                'name'      =>  ucwords($name),
                'label'     => 'artist-custom-notification',
                'type'      => 'notification',
                'payload'   => $payload,
                'stats'     => $stats,
                'test'      => $test,
                'status'    => 'created',
            ];
            $campaign       =   $this->repObj->sndCustomNotificationToCustomerByArtist($campaignData);


            $notificationParams             =   [
                'artist_id'     =>  $artist_id,
                'deeplink'      =>  $deeplink,
                'topic_id'      =>  $topic_id,
                'title'         =>  trim($payload['title']),
                'body'          =>  trim($payload['body']),
                'icon_url'      =>  $icon_url,
                'cover_url'     =>  $cover_url,
            ];

            if($content_id != ''){
                $notificationParams['content_id']   =   trim($content_id);
            }
            $sendNotification           =   $this->pushnotification->sendNotificationToTopic($notificationParams);

        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }





}