<?php

namespace App\Services;

/**
 * ServiceName : Language.
 * Maintains a list of functions used for Language.
 *
 * @author      Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since       2019-06-25
 * @link        http://bollyfame.com
 * @copyright   2019 BOLLYFAME
 * @license     http://bollyfame.com/license
 */

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;

use App\Repositories\Contracts\LanguageInterface;
use App\Services\Cache\AwsElasticCacheRedis;


class LanguageService {

    private   $cache_expire_time = (43200 * 60); // 30 days in seconds

    protected $repObj;
    protected $awsElasticCacheRedis;

    public function __construct(LanguageInterface $repObj, AwsElasticCacheRedis $awsElasticCacheRedis) {
        $this->repObj   = $repObj;
        $this->awsElasticCacheRedis   = $awsElasticCacheRedis;
    }


    public function index($request) {
        $data       = $request->all();
        $results    = $this->repObj->index($data);

        return $results;
    }


    public function paginate() {
        $error_messages = $results = [];
        $results = $this->repObj->paginateForApi();

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function list($request) {
        $error_messages = [];
        $results        = [];
        $data       = $request->all();
        $results    = $this->repObj->list($data);

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function activeLists() {
        $error_messages = $results = [];
        $results = $this->repObj->activelists();

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function find($id) {
        $results = $this->repObj->find($id);
        return $results;
    }


    public function show($id) {
        $error_messages = $results = [];
        if(empty($error_messages)){
            $results['language']    = $this->repObj->find($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function store($request) {
        $data           = $request->all();
        $error_messages = $results = [];
        $language       = null;

        if(empty($error_messages)){
            $results['language'] = $this->repObj->store($data);

            // PURGE CACHE
            $purge_result = $this->awsElasticCacheRedis->purgeLanguageListCache();
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function update($request, $id) {
        $data           = $request->all();
        $error_messages = $results = [];

        if(empty($error_messages)){
            $results['language']   = $this->repObj->update($data, $id);
            // PURGE CACHE
            $purge_result = $this->awsElasticCacheRedis->purgeLanguageListCache();
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function destroy($id) {
        $results = $this->repObj->forceDelete($id);
        return $results;
    }

    /**
     * Returns Languages Labels
     *
     * @param   string  $by
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-02
     */
    public function getLabelsBy($by = 'code_3') {
        $ret = [];
        $error_messages = $results = [];
        $lang_ids       = [];
        $cache_params   = [];
        $hash_name      = env_cache(Config::get('cache.hash_keys.language_list'));
        $hash_field     = $by;
        $cache_miss     = false;

        $cache_params['hash_name']  = $hash_name;
        $cache_params['hash_field'] = (string) $hash_field;
        $cache_params['expire_time']= $this->cache_expire_time;


        $languages = $this->awsElasticCacheRedis->getHashData($cache_params);
        if (empty($languages)) {
            $languages_obj = $this->repObj->labelsBy($by);
            if($languages_obj) {
                $languages = $languages_obj->toArray();
            }

            if($languages) {
                $cache_params['hash_field_value'] = $languages;
                $saveToCache    = $this->awsElasticCacheRedis->saveHashData($cache_params);
                $cache_miss     = true;
                $languages      = $this->awsElasticCacheRedis->getHashData($cache_params);
            }
        }

        $results['list']    = $languages;
        $results['cache']   = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Returns Languages Labels
     *
     * @param   string  $by
     *
     * @return  array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-02
     */
    public function getLabelsArrayBy($by = 'code_3') {
        $ret = [];

        $response = $this->getLabelsBy($by);
        if($response) {
            $ret = isset($response['results']) && isset($response['results']['list']) ? $response['results']['list'] : [];
        }

        return $ret;
    }


    /**
     * Return Language Detail By
     *
     * @param   string  $key_value
     * @param   string  $by
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-12
     */
    public function findActiveBy($key_value, $by = 'code_3') {
        $ret = [];
        $error_messages = $results = [];
        $cache_params   = [];
        $hash_name      = env_cache(Config::get('cache.hash_keys.language_list'));
        $hash_field     = $by . ':' . $key_value;
        $cache_miss     = false;

        $cache_params['hash_name']  = $hash_name;
        $cache_params['hash_field'] = (string) $hash_field;
        $cache_params['expire_time']= $this->cache_expire_time;

        $languages = $this->awsElasticCacheRedis->getHashData($cache_params);
        if (empty($languages)) {
            $languages_obj = $this->repObj->findActiveBy($key_value, $by);
            if($languages_obj) {
                $languages = $languages_obj->toArray();
            }

            if($languages) {
                $cache_params['hash_field_value'] = $languages;
                $saveToCache    = $this->awsElasticCacheRedis->saveHashData($cache_params);
                $cache_miss     = true;
                $languages      = $this->awsElasticCacheRedis->getHashData($cache_params);
            }
        }

        $results['language']    = $languages;
        $results['cache']       = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Returns Language
     *
     * @param   string  $key_value
     * @param   string  $by
     *
     * @return  array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-02
     */
    public function getActiveBy($key_value, $by = 'code_3') {
        $ret = [];

        $response = $this->findActiveBy($key_value, $by);
        if($response) {
            $ret = isset($response['results']) && isset($response['results']['language']) ? $response['results']['language'] : [];
        }

        return $ret;
    }
}
