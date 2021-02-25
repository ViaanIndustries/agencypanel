<?php


namespace App\Services;

use Carbon\Carbon;
use Config, Log, Hash, File;

use Predis;
use Predis\Connection\Aggregate\RedisCluster;
use Predis\Client as PredisClient;
Class AwsElasticCacheRedis
{

    protected $redisClient;
    protected $env;
    protected $expire_time;
    protected $customer_profile_expire_time;

    public function __construct() {
        $this->expire_time                      =   600; //10 min in seconds
        $this->customer_profile_expire_time     =   (43200 * 60); // 30 days in seconds
        $this->content_expire_time              =   600; //in seconds
        $this->env                              =   env('APP_ENV', 'local');
        $this->get_from_db                      =   false;

        $this->awsElasticCacheCluster();
    }

     public function PredisConnection(){
        return $this->redisClient;
    }

   public function awsElasticCacheCluster() {

        // Put your AWS ElastiCache Configuration Endpoint here.
        $configuration_endpoint  = Config::get('product.'. env('PRODUCT') .'.cache.aws_elastic_cache_cluster_endpoint');

        $parameters  = [$configuration_endpoint];

        // Tell client to use 'cluster' mode.
        $options  = ['cluster' => 'redis'];

        // Create your redis client
        $this->redisClient = new PredisClient($parameters, $options);
   }

    public function saveHashData($params = [])
    {

        $hash_name          =   (!empty($params['hash_name']) && $params['hash_name'] != '') ? trim($params['hash_name']) : '';
        $hash_field         =   (!empty($params['hash_field']) && $params['hash_field'] != '') ? trim($params['hash_field']) : '';
//      $hash_field_value   =   (!empty($params['hash_field_value']) && $params['hash_field_value'] != '') ? serialize($params['hash_field_value']) : [];
        $hash_field_value   =   (!empty($params['hash_field_value']) && $params['hash_field_value'] != '') ? serialize($params['hash_field_value']) : serialize([]);
        $expire_time        =   (!empty($params['expire_time']) && $params['expire_time'] != '') ? intval($params['expire_time']) : $this->expire_time;

        try{
            if ($hash_name != '' && $hash_field != '') {
//    

                $this->redisClient->hmset($hash_name, $hash_field, $hash_field_value);
                $this->redisClient->expire($hash_name, $expire_time);

            } else {
       
            }

        } catch (\Exception $e) {
            $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
        
        }
    }


    public function getHashData($params = [])
    {

        $hash_value             =   [];
        $hash_name              =   (!empty($params['hash_name']) && $params['hash_name'] != '') ? trim($params['hash_name']) : '';
        $hash_field             =   (!empty($params['hash_field']) && $params['hash_field'] != '') ? (string)trim($params['hash_field']) : '';

//        \Log::info('getHashData  ', ['hash_name' => $hash_name,  'hash_field' => $hash_field]);

        try{
            if($this->redisClient->exists($hash_name) && $this->redisClient->hexists($hash_name, $hash_field)){

                /*
                $hash_result    =   $this->redisClient->hmget($hash_name, $hash_field);
                var_dump(($hash_result));echo"<br><br><br><br>";
                var_dump(unserialize(head($hash_result)));echo"<br><br><br><br>";
                var_dump(unserialize($hash_result));echo"<br><br><br><br>";
                exit;
                */

                $hash_result    =   $this->redisClient->hmget($hash_name, $hash_field);
                if(!empty($hash_result)){
                    $hash_value              =  unserialize(head($hash_result));
                }

//                \Log::info('getHashData hash_result ===>  '. json_encode($hash_result));
            }
        } catch (\Exception $e) {
            $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
     
        }

        return $hash_value;
    }

}
