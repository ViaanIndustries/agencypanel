<?php

namespace App\Services;

use Session;
use Redirect;
use Input;
use Crypt;
use Hash;
use Validator;
use Config;
use Request;
use Storage;


use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

Class Aws {


    protected $key;

    protected $secret;

    protected $region;

    protected $raw_video_bucket   =   'arms-razrmedia';


    function upload_magic_video ($params = array())
    {

        $id                             =   isset($params['id']) ? $params['id'] : "";
        $destination_path               =   isset($params['destination_path']) ? $params['destination_path'] : "/";
        $file_object                    =   $params['file'];
        $source_file_path               =   $file_object->getRealPath();
        $file_realname                  =   $file_object->getClientOriginalName();
        $file_name                      =   $id . "_" . time();
        $file_ext                       =   $file_object->getClientOriginalExtension();

//         echo "<br>source_file_path: $source_file_path
//               <br>destination_path: $destination_path
//               <br>file_name: $file_name
//               <br>file_realname: $file_realname
//               <br>file_ext: $file_ext";
//         exit;

        //**********************************************************************************
        //*********************  MANAGES ORGINAL START HERE *********************

        // Set Amazon s3 credentials
//        $s3             =   S3Client::factory([
//            'key'    => env('AWS_ACCESS_KEY_ID'),
//            'secret' => env('AWS_SECRET_ACCESS_KEY'),
//                'region' => 'ap-southeast-1',
//            'version' => '2006-03-01',
//        ]
//
//        );
//        $response       =   $s3->putObject([
//                'Bucket'        =>     'armsrawvideos',
//                'Key'           =>     $source_file_path,
//                'SourceFile'    =>     $destination_path,
//                'ACL'           =>     'public-read'
//        ]);

        $s3             =   Storage::disk('s3_armsrawvideos');
        $response       =   $s3->put($destination_path, file_get_contents($source_file_path), 'public');
        Log::info('uploadFileToS3 : '.json_encode($response). '  DestLoc : '.$destination_path);
//        return $response;

        print_pretty($response);exit;



    }

    public function uploadFileToS3($params)
    {

        $srcLoc         =   $params['source_file_path']."/".$params['source_file_name'];
        $destLoc        =   $params['destination_path'].$params['destination_file_name'];
        $s3             =   AWS::get('s3');

        $response       = $client->putObject(array(
            'Bucket'     => 'mybucket',
            'Key'        => 'data.txt',
            'SourceFile' => '/path/to/data.txt',
            'ACL'        => 'public-read'
        ));

        return $response;


    }












}