<?php

namespace App\Services;

/**
 * ServiceName : Cast.
 * Maintains a list of functions used for Cast.
 *
 * @author Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since 2019-05-13
 * @link http://bollyfame.com/
 * @copyright 2019 BOLLYFAME
 * @license http://bollyfame.com//license/
 */

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;

use App\Repositories\Contracts\CastInterface;
use App\Models\Cast;

use App\Services\Image\Kraken;
use App\Services\Cache\AwsElasticCacheRedis;

class CastService
{
    protected $repObj;
    protected $cast;
    protected $kraken;
    protected $cache;

    private $cache_expire_time = 600; // 10 minutes in seconds

    public function __construct(Cast $cast, CastInterface $repObj, Kraken $kraken, AwsElasticCacheRedis $cache)
    {
        $this->cast     = $cast;
        $this->repObj   = $repObj;
        $this->kraken   = $kraken;
        $this->cache    = $cache;
    }


    public function index($request)
    {
        $requestData= $request->all();
        $results    = $this->repObj->index($requestData);

        return $results;
    }


    public function paginate()
    {
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

    public function activeLists()
    {
        $error_messages = $results = [];
        $results = $this->repObj->activelists();

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function find($id)
    {
        $results = $this->repObj->find($id);
        return $results;
    }


    public function show($id)
    {
        $error_messages = $results = [];
        if(empty($error_messages)){
            $results['cast']    = $this->repObj->find($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function store($request)
    {
        $data           = $request->all();
        $error_messages = $results = [];

        if ($request->hasFile('photo')) {
            $parmas     = ['file' => $request->file('photo'), 'type' => 'casts'];
            $photo      = $this->kraken->uploadToAws($parmas);
            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                array_set($data, 'photo', $photo['results']);
            }
        }

        if(empty($error_messages)){
            $results['cast']    = $this->repObj->store($data);

            // Purge Cast Related Cache
            try {
                $purge_cache = $this->cache->purgeCastCache();
            }
            catch (\Exception $e) {
                $purge_cache_error = $e->getMessage();
                \Log::info('Purge Cache - Search Cast : Fail ', $error_messages);
            }
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function update($request, $id)
    {
        $data           = $request->all();
        $error_messages = $results = [];

        if ($request->hasFile('photo')) {
            $parmas     =   ['file' => $request->file('photo'), 'type' => 'casts'];
            $photo      =   $this->kraken->uploadToAws($parmas);
            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                array_set($data, 'photo', $photo['results']);
            }
        }

        if(empty($error_messages)){
            $results['cast']   = $this->repObj->update($data, $id);

            // Purge Cast Related Cache
            try {
                $purge_cache = $this->cache->purgeCastCache();
            }
            catch (\Exception $e) {
                $purge_cache_error = $e->getMessage();
                \Log::info('Purge Cache - Search Cast : Fail ', $error_messages);
            }
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function destroy($id)
    {
        $results = $this->repObj->destroy($id);
        return $results;
    }

    /**
     * Return Cast List search by name
     *
     * @param   string      $arist_id
     * @param   array       $request
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function search($request) {
        $error_messages = [];
        $results        = [];
        $return_fields  = ['first_name', 'last_name'];
        $data           = $request->all();

        $name       = isset($data['name']) ? strtolower(trim($data['name'])) : '';
        $sort_by    = isset($data['sort_by']) ? trim($data['sort_by']) : 'name';
        $page       = (isset($data['page']) && $data['page'] != '') ? trim($data['page']) : '1';

        $cache_params   = [];
        $hash_name      = env_cache_key(Config::get('cache.hash_keys.cast_search'));
        $hash_field     = intval($page) . ':' . $name;
        $cache_miss     = false;

        $cache_params['hash_name']   = $hash_name;
        $cache_params['hash_field']  = (string) $hash_field;
        $cache_params['expire_time'] = $this->cache_expire_time;

        $results  = $this->cache->getHashData($cache_params);
        if(!$results) {
            $results = $this->repObj->search($data, $return_fields);
            if($results) {
                $results = apply_cloudfront_url($results);
                $cache_params['hash_field_value'] = $results;
                $save_to_cache  = $this->cache->saveHashData($cache_params);
                $cache_miss     = true;
                $results        = $this->cache->getHashData($cache_params);
            }
        }

        $results['cache']   = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        return ['error_messages' => $error_messages, 'results' => $results];
    }
}
