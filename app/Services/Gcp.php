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


use Google\Cloud\ServiceBuilder;
use Google\Cloud\Storage\StorageClient;


Class Gcp {

    protected $cloud;

    protected $storage;

    protected $bucket;

    protected $default_bucket   =   'arms-razrmedia';

    public function __construct()
    {
        $gcp_key_file_path      =  config_path()."/gcp_key.json";
        // Authenticate using a keyfile path
        $this->cloud    = new ServiceBuilder(['keyFilePath' => $gcp_key_file_path]);
        $this->storage  = new StorageClient(['keyFilePath' => $gcp_key_file_path]);
        $this->bucket   = $this->storage->bucket($this->default_bucket);
    }



    public function auth()
    {
        return $this->cloud;
    }



    public function buckets()
    {
        return $buckets = $this->storage->buckets();
    }


    public function createBucket()
    {

        # The name for the new bucket
        $bucketName = 'my-new-bucket'.time();

        # Creates the new bucket
        $bucket = $this->storage->createBucket($bucketName);

        return $bucket;
    }


    public function uploadLocalFile()
    {
        $source         =   public_path().'/1.jpg';
        $bucketName     =   $this->default_bucket;
//        $objectName     =   'test/'.time().'_1.jpg';
        $objectName     =   'test/test1/'.time().'_1.jpg';

        $file       =   fopen($source, 'r');
        $bucket     =   $this->storage->bucket($bucketName);
        $options    =   ['name' => $objectName, 'predefinedAcl' => 'publicRead'];
        $object     =   $bucket->upload($file, $options);

        return ['source' => $source, 'basename' => basename($source), 'bucketName' => $bucketName, 'objectName' => $objectName, ];
    }



    public function localFileUpload($params){

        $bucketName     =   $this->default_bucket;
        $source         =   $params['object_source_path'];
        $objectName     =   $params['object_upload_path'];

        $file           =   fopen($source, 'r');

        $bucket         =   $this->storage->bucket($bucketName);
        $options        =   ['name' => $objectName, 'predefinedAcl' => 'publicRead'];
        $object         =   $bucket->upload($file, $options);

        return ['source' => $source, 'basename' => basename($source), 'bucketName' => $bucketName, 'objectName' => $objectName, ];

    }








}