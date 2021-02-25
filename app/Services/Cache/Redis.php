<?php

namespace App\Services\Cache;

use Carbon\Carbon;
use Config, Log, Hash, File;
use GuzzleHttp\PrepareBodyMiddleware;
use Predis;
use Predis\Connection\Aggregate\RedisCluster;
use Predis\Client as PredisClient;

abstract Class Redis
{

    protected $redisClient;
    protected $env;
    protected $expire_time;
    protected $customer_profile_expire_time;

    public function __construct(){

        $this->expire_time                      =   600; //in seconds
        $this->customer_profile_expire_time     =   (43200 * 60); // 30 days in seconds
        $this->content_expire_time              =   600; //in seconds
        $this->env                              =   env('APP_ENV', 'production');
        $this->get_from_db                      =   false;

        $this->awsElasticCacheCluster();
    }


    public function gcpCustomCacheCluster(){

        if ($this->env == 'production') {
            $parameters = Config::get('cache.production_parameters');

        } else {
            $parameters = Config::get('cache.staging_parameters');
        }

        $options = [
            'cluster' => 'redis',
            'parameters' => []
        ];

        $this->redisClient = new PredisClient($parameters, $options);

    }

    public function awsElasticCacheCluster(){

        // Put your AWS ElastiCache Configuration Endpoint here.
        if ($this->env == 'production') {
            $configuration_endpoint  = 'armsprodrediscluster.bfvxjo.clustercfg.aps1.cache.amazonaws.com:6379';
        } else {
            $configuration_endpoint  = 'armsprodrediscluster.bfvxjo.clustercfg.aps1.cache.amazonaws.com:6379';
        }

        $parameters  = [$configuration_endpoint];

        // Tell client to use 'cluster' mode.
        $options  = ['cluster' => 'redis'];

        // Create your redis client
        $this->redisClient = new PredisClient($parameters, $options);
    }


    public function PredisConnection(){
        return $this->redisClient;
    }


    public function welcome(){

        return ["rediscacheWelcome" => 'rediscacheWelcome'];

    }



}