<?php
namespace App\Services\Image;

use Config, File, Log;
use Illuminate\Support\Facades\Storage;

use Aws\S3\S3Client as S3;
use Aws\Credentials\Credentials as AWSCredentials;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;


class Kraken
{

    protected $test_apikeys = [
        "api_key"       => '8d7ebfafc60d0a387533d1991412ce6a',
        "api_secret"    => 'b20b3750ca14c983bb0b6e3751a8b338c1066e43'
    ];


    public function __construct(){

        $this->auth = array(
            "auth" => $this->test_apikeys
        );

    }

    public function url($opts = array())
    {
        $data = json_encode(array_merge($this->auth, $opts));
        $response = self::request($data, "https://api.kraken.io/v1/url");
        return $response;
    }

    public function upload($opts = array())
    {
        if (!isset($opts['file'])) {
            return array(
                "success" => false,
                "error" => "File parameter was not provided"
            );
        }

        if (preg_match("/\/\//i", $opts['file'])) {
            $opts['url'] = $opts['file'];
            unset($opts['file']);
            return $this->url($opts);
        }

        if (!file_exists($opts['file'])) {
            return array(
                "success" => false,
                "error" => "File `" . $opts['file'] . "` does not exist"
            );
        }

        if (class_exists('CURLFile')) {
            $file = new \CURLFile($opts['file']);
        } else {
            $file = '@' . $opts['file'];
        }

        unset($opts['file']);

        $data = array_merge(array(
            "file" => $file,
            "data" => json_encode(array_merge(
                $this->auth, $opts
            ))
        ));

        $response = self::request($data, "https://api.kraken.io/v1/upload");

        return $response;
    }

    public function status()
    {
        $data = array('auth' => array(
            'api_key' => $this->auth['auth']['api_key'],
            'api_secret' => $this->auth['auth']['api_secret']
        ));
        $response = self::request(json_encode($data), "https://api.kraken.io/user_status");
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





    public function uploadToAws($params = array())
    {

        $results                            =   [];
        $error_messages                     =   [];
        $type                               =   (!empty($params['type'])) ? $params['type'] : "";
        $img_resize                         =   Config::get('kraken.type.'.$type.'.resize', []);
        $img_url                            =   (!empty($params['url'])) ? $params['url'] : "";
        $img_name                           =   time();


        if (!empty($params['file'])) {
            $img_object                         =   $params['file'];
            $img_path                           =   $img_object->getRealPath();
            $img_realname                       =   $img_object->getClientOriginalName();
            $img_ext                            =   $img_object->getClientOriginalExtension();
            $convert_format                     =   (!empty($img_ext)) ? strtolower($img_ext) : 'jpg';

        }elseif($img_url != ""){

            $img_url                            =   $img_url;
            $imageName                          =   basename($img_url);
            $img_path                           =   public_path('uploads/contents/'.$imageName);
            $convert_format                     =   strtolower(pathinfo($img_path, PATHINFO_EXTENSION));
            @copy($img_url, $img_path);
            @chmod($img_path, 0777);
            Log::info("uploadKrakenImageToAWS img_url - ". $img_url);

        }

        $resize_with_storage_path_arr        =   [];

        foreach ($img_resize as $resize){
            $resize                 =   $resize;
            $name                   =   trim(strtolower($resize["id"]."-$img_name.$convert_format"));
            $resize['storage_path'] =   trim(strtolower($type."/".$name));
            array_push($resize_with_storage_path_arr, $resize);
        }

        $upload_params = array(
            "file"      => $img_path,
            "resize"    => $resize_with_storage_path_arr,
            "wait"      => true,
            "lossy"     => true,
            "auto_orient" => true,
            "convert"   => ["format" => $convert_format],
            "s3_store"  => [
                "key"       => Config::get('product.' . env('PRODUCT') . '.s3.key'),
                "secret"    => Config::get('product.' . env('PRODUCT') . '.s3.secret'),
                "bucket"    => Config::get('product.' . env('PRODUCT') . '.s3.bucket'),
                "region"    => Config::get('product.' . env('PRODUCT') . '.s3.region'),
                "acl"       => Config::get('product.' . env('PRODUCT') . '.s3.acl'),
                "headers"   => [
                    "Cache-Control" => "max-age=2592000000",
                    //"Expires" => "2026-04-04T12:06:11+00:00"
                ],
            ]
        );

        try {

            Log::info(__METHOD__ . '$upload_params :'. json_encode($upload_params));

            $data = $this->upload($upload_params);

     

            if ($data && isset($data['success']) && $data['success'] == 1) {

                if($img_url != ""){
                    @unlink($img_path);
                }

                $kraken_results    =   $data["results"];
                if(!empty($kraken_results)){
                    foreach ($kraken_results as $key => $kraken_result){
                        $results[$key]              = (!empty($kraken_results[$key]['kraked_url'])) ? $kraken_results[$key]['kraked_url'] : null;
                        $results[$key."_width"]     = (!empty($kraken_results[$key]['kraked_width'])) ? $kraken_results[$key]['kraked_width'] : null;
                        $results[$key."_height"]     = (!empty($kraken_results[$key]['kraked_height'])) ? $kraken_results[$key]['kraked_height'] : null;
                    }
                }
                $success           =   true;
            }else{
                $success            =   false;
                $error_messages     =   isset($data["message"]) ? $data["message"] : '';
                Log::info("uploadKrakenImageToS3  array - ".$error_messages);
            }

            }catch (Exception $e) {
                $success            =   false;
                $error_messages     =   $e->getMessage();
                Log::info("uploadKrakenImageToS3  array - ".$error_messages);
            }


        $response = [
            'success' => $success, 'results' => $results, 'error_messages' => $error_messages, 'upload_params' => $upload_params
        ];
 
        return $response;

    }


    public function imgUpload($params = array())
    {


    }


    public function urlUpload($params = array())
    {

    }





    public function uploadPhotoToAws($params = array())
    {
 
        $results                            =   [];
        $error_messages                     =   [];
        $type                               =   (!empty($params['type'])) ? $params['type'] : "";
        $img_resize                         =   Config::get('kraken.type.'.$type.'.resize', []);
        $img_url                            =   (!empty($params['url'])) ? $params['url'] : "";
        $img_name                           =   time();
        $success = true;

        if (!empty($params['file'])) {
            $img_object                         =   $params['file'];
            $img_path                           =   $img_object->getRealPath();
            $img_realname                       =   $img_object->getClientOriginalName();
            $img_ext                            =   $img_object->getClientOriginalExtension();
            $convert_format                     =   (!empty($img_ext)) ? strtolower($img_ext) : 'jpg';

        }
        // $resize_with_storage_path_arr        =   [];

        // foreach ($img_resize as $resize){
        //     $resize                 =   $resize;
            $name                   =   trim(strtolower('image'."-$img_name.$convert_format"));
            $storage_path =   trim(strtolower($type."/".$name));
        //     array_push($resize_with_storage_path_arr, $resize);
        // }
         try {
            $aws_key = Config::get('product.' . env('PRODUCT') . '.s3.key');
          $aws_secret = Config::get('product.' . env('PRODUCT') . '.s3.secret');
          $aws_bucket = Config::get('product.' . env('PRODUCT') . '.s3.bucket');
          $credentials = new AWSCredentials($aws_key, $aws_secret);
          $s3_options = [
            'region' =>  Config::get('product.' . env('PRODUCT') . '.s3.region'),
            'version' => 'latest',
            'credentials' => $credentials
          ];
          $s3 = new S3($s3_options);
          $object_array = [
            'Bucket' => $aws_bucket,
            'Key' => $storage_path,
            'Body' =>file_get_contents($img_object),
            'ACL' =>  Config::get('product.' . env('PRODUCT') . '.s3.acl'),
           // 'SourceFile' => $file_content	
          ];
         

            $results = $s3->putObject($object_array);
   
        }catch (Exception $e) {
            $success            =   false;
            $error_messages     =   $e->getMessage();
            Log::info("uploadKrakenImageToS3  array - ".$error_messages);
        }


        $response = [
            'success' => $success, 'results' => $results, 'error_messages' => $error_messages
        ];
 
        return $response;

    }









}
