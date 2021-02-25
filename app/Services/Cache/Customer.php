<?php

namespace App\Services\Cache;

use Carbon\Carbon;
use Config, Log, Hash, File, Cache;
use GuzzleHttp\PrepareBodyMiddleware;

use App\Repositories\Contracts\BucketInterface;
use App\Services\Cache\AwsElasticCacheRedis;
use App\Services\Jwtauth;

Class Customer extends  AwsElasticCacheRedis {

    protected $buckcetrepObj;
    protected $cacheClient;

    public function __construct(
        BucketInterface $buckcetrepObj,
        AwsElasticCacheRedis $cacheClient
    ){
        $this->buckcetrepObj                =   $buckcetrepObj;
        $this->cacheClient                  =   $cacheClient->redisClient;
        $this->bucketlists_key              =   'b:lists';
        $this->bucketdetail_key             =   'b:detail';
        $this->bucketlists_limit            =   10;

        //600 in seconds // (43200 * 60); // 30 days in seconds
        $this->default_expire_time          =   600;

    }



    public function login($params = array()){


    }




    public function register($params = array()){


    }







}