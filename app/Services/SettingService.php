<?php 

namespace App\Services;

use Input;
use Redirect;
use Config;
use Session;
use App\Repositories\Contracts\SettingInterface;
use App\Models\Setting as Setting;
use App\Services\Notifications\PushNotification;

class SettingService
{
    protected $repObj;
    protected $setting;
    protected $pushnotification;
    public function __construct(Setting $setting, SettingInterface $repObj,PushNotification $pushnotification)
    {
        $this->setting = $setting;
        $this->repObj  = $repObj;
        $this->pushnotification=$pushnotification;
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
    
    public function showListing($postData)
    {
        
        $env                =     (isset($postData['env']) && $postData['env'] != '')  ? $postData['env'] : 'test';
        //echo $postData['env'] ;exit;
        $artist_ids         =   $fcm_ids =$customer_idArr= $emails= $customer_ids=$fcm_device_tokens=[];
        $fcm_artist_topics  =   [];
        if(isset($env)){
            $type='sync_fcm_ids_'.$env;
            array_set($data,'type',$type);
            array_set($data, 'env', $env);
        }
        array_set($data, 'name', 'sync customer fcm id');
        if(isset($env) && $env=='test' ){
            $topic_name='fmc_default_topic_id_test';
        }
        else{
            $topic_name='fmc_default_topic_id';
        }
        // echo $topic_name;exit;
        $fcm_artist_data= \App\Models\Artistconfig::select('artist_id',$topic_name)->get();
        $customer_ids=$postData['customer_id'];
        
        foreach ($fcm_artist_data as $fcm_artist) {
            $artist_id            = $fcm_artist->artist_id;
            $fcm_artist_topic     = $fcm_artist->$topic_name ;
            
            
            $artist_ids[]                   =   $artist_id;
            $fcm_artist_topics[]            =   $fcm_artist_topic;
        }
        
        
        
        if(count($artist_ids)>0){
            
            array_set($data, 'artists_ids', $artist_ids);
        }
        if(count($fcm_device_tokens)>0){
            
            array_set($data, 'fcm_ids', $fcm_device_tokens);
        }
        
        if(count($fcm_artist_topics)>0){
            
            array_set($data, 'topics', $fcm_artist_topics);
        }
        
        
        array_set($data, 'env', $env);
        
        // echo $type; exit;
        $settingObjExists = \App\Models\Setting::with('customers')->where('type',$type)->first();
        //print_r($settingObjExists);exit;
        if(!isset($settingObjExists)){
            
            // print_pretty($data);exit;
            $settingObj         = new \App\Models\Setting($data);
            $settingObj->save();
        }else{
            $settingObj   =   $settingObjExists->update($data);
            $settingObj   =   $settingObjExists;
        }
        // $this->syncCustomers($postData , $setting);
        return $settingObj;
    }
    public function show($id)
    {
        $error_messages     =   $results = [];
        if(empty($error_messages)){
            $results['role']    =   $this->repObj->find($id);
        }
        
        return ['error_messages' => $error_messages, 'results' => $results];
    }
    
    public function update($postData)
    {
        
        $env                =     (isset($postData['env']) && $postData['env'] != '')  ? $postData['env'] : 'test';
        //echo $postData['env'] ;exit;
        $artist_ids         =  $customer_idArr=$fcm_ids = $emails= $customer_ids=$fcm_device_tokens=[];
        $fcm_artist_topics  =   [];
        if(isset($env)){
            $type='sync_fcm_ids_'.$env;
            array_set($data,'type',$type);
            array_set($data, 'env', $env);
        }
        array_set($data, 'name', 'sync customer fcm id');
        if(isset($env) && $env=='test' ){
            $topic_name='fmc_default_topic_id_test';
        }
        else{
            $topic_name='fmc_default_topic_id';
        }
        // echo $topic_name;exit;
        $fcm_artist_data= \App\Models\Artistconfig::select('artist_id',$topic_name)->get();
        $customer_ids=$postData['customer_id'];
        
        foreach ($fcm_artist_data as $fcm_artist) {
            $artist_id            = $fcm_artist->artist_id;
            $fcm_artist_topic     = $fcm_artist->$topic_name ;
            if(count($customer_ids)>0){
                foreach ($customer_ids as $customer_id) {
                    //  $customer_id='5a2e5b856d90120304241322';
                    //  $artist_id='59858df7af21a2d01f54bde2';
                    $customer       =\App\Models\Customer::where('_id',$customer_id)->first();
                    $customer_data  = \App\Models\Customerdeviceinfo::where('customer_id',$customer_id)->where('artist_id','=',$artist_id)->orderBy('created_at', 'desc')->first();
                    
                    $email        =    (isset($customer) && $customer->email != '')  ? $customer->email : '';
                    $fcm_device_token        =    (isset($customer_data) && $customer_data->fcm_device_token != '')  ? $customer_data->fcm_device_token : '';
                    
                    
                    if($fcm_device_token!='' && isset($artist_id) && isset($fcm_artist_topic) )
                    {
                        $params = [
                            'device_token' => $fcm_device_token,
                            'topic_id'     => $fcm_artist_topic,
                            'artist_id'    => $artist_id
                        ];
                        $response = $this->pushnotification->subscribeUserToTopic($params);
                    }
                    
                    if (!in_array($email, $emails))
                    {
                        $emails[]                       =   $email;
                    }
                    if (!in_array($fcm_device_token, $fcm_device_tokens))
                    {
                        $fcm_device_tokens[]            =   $fcm_device_token ;
                        
                    }
                    if (!in_array($customer_id, $customer_idArr))
                    {
                        $customer_idArr[]               = $customer_id;
                    }
                    
                    
                }
            }
            
            $artist_ids[]                   =   $artist_id;
            $fcm_artist_topics[]            =   $fcm_artist_topic;
        }
        
        
        
        if(count($artist_ids)>0){
            
            array_set($data, 'artists_ids', $artist_ids);
        }
        
        
        if(count($fcm_artist_topics)>0){
            
            array_set($data, 'topics', $fcm_artist_topics);
        }
        
        
        
        
        array_set($data, 'env', $env);
        
        // echo $type; exit;
        $settingObjExists   =   \App\Models\Setting::where('type',$type)->first();
        // $existing_ids       =   $settingObjExists['customer_id'];
        // $customer_idArr     =   array_merge($existing_ids, $customer_idArr);
        // $existing_fcm_ids   =   $settingObjExists['fcm_ids'];
        // $fcm_device_tokens  =   array_merge($existing_fcm_ids, $fcm_device_tokens);
        // $existing_emails    =   $settingObjExists['customer_emails'];
        // $emails             =   array_merge($existing_emails, $emails);
        if(count($customer_idArr)>0){
            
            array_set($data, 'customer_id', $customer_idArr);
        }
        
        if(count($fcm_device_tokens)>0){
            
            array_set($data, 'fcm_ids', $fcm_device_tokens);
        }
        if(count($emails)>0){
            
            array_set($data, 'customer_emails', $emails);
        }
        
        $settingObj         =   $settingObjExists->update($data);
        $settingObj         =   $settingObjExists;
        
        
        return $settingObj;
        
        return ['error_messages' => $error_messages, 'results' => $results];
    }
    
    
    
    
    
    public function destroy($id)
    {
        $results = $this->repObj->destroy($id);
        return $results;
    }
    
    
}