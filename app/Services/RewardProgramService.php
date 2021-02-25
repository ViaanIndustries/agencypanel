<?php

namespace App\Services;

/**
 * ServiceName : RewardProgram.
 * Maintains a list of functions used for RewardProgram.
 *
 * @author      Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since       2019-08-27
 * @link        http://bollyfame.com
 * @copyright   2019 BOLLYFAME
 * @license     http://bollyfame.com/license
 */

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;
use DateTime;
use Carbon;

use App\Repositories\Contracts\RewardProgramInterface;
use App\Services\ArtistService;
use App\Services\Cache\AwsElasticCacheRedis;

class RewardProgramService {

    protected $repObj;
    protected $serviceArtist;
    protected $cache;

    private $cache_expire_time = 600; // 10 minutes in seconds

    public function __construct(RewardProgramInterface $repObj, ArtistService $serviceArtist, AwsElasticCacheRedis $cache) {
        $this->repObj       = $repObj;
        $this->serviceArtist= $serviceArtist;
        $this->cache        = $cache;
    }


    /**
     * Validates data
     *
     * @param   array   $data
     * @param   string  $id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-27
     */
    public function validateData($data, $id = '') {
        $ret = true;

        // @TODO Validate data as per rules
        //$rules = [];

        return $ret;
    }

    public function index($request) {
        $data       = $request->all();
        $results    = $this->repObj->index($data);

        return $results;
    }


    public function paginate() {
        $error_messages = $results = [];
        $results    = $this->repObj->paginateForApi();

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function list($request) {
        $error_messages = [];
        $results        = [];
        $data           = $request->all();
        $results        = $this->repObj->list($data);

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function activeLists() {
        $error_messages = $results = [];
        $results        = $this->repObj->activelists();

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function find($id) {
        $results = $this->repObj->find($id);
        return $results;
    }


    public function show($id) {
        $error_messages = $results = [];
        if(empty($error_messages)){
            $results['rewardprogram']    = $this->repObj->find($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function store($request) {
        $data           = $request->all();
        $error_messages = [];
        $results        = [];
        $rewardprogram  = null;

        $slug = (isset($data['name'])) ? str_slug($data['name']) : '';
        array_set($data, 'slug', $slug);

        $data = $this->sanitizedData($data);

        if(empty($error_messages)) {
            $results['rewardprogram'] = $this->repObj->store($data);

            $artist_id          =   (!empty($data) && isset($data['artist_id'])) ? $data['artist_id'] : '';
            $purge_result       =   $this->cache->purgeAllArtistRewardProgramListCache(['artist_id' => $artist_id]);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function update($request, $id) {
        $data           = $request->all();
        $error_messages = $results = [];
        $artist_id      = '';

        $record = $this->repObj->find($id);
        if($record) {
            $artist_id = $record->artist_id;
        }
        else {
            $error_messages[] = 'Record not found';
        }

        $this->validateData($data);

        $data = $this->sanitizedData($data);

        if(empty($error_messages)) {

            $slug = (isset($data['name'])) ? str_slug($data['name']) : '';
            array_set($data, 'slug', $slug);

            $results['rewardprogram']   = $this->repObj->update($data, $id);

            $purge_result = $this->cache->purgeAllArtistEventListCache(['artist_id' => $artist_id]);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function destroy($id) {
        $results = $this->repObj->forceDelete($id);
        return $results;
    }

    /**
     * Return Artist List
     *
     * @param   array   $artists
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function getArtistList() {
        $ret = [];
        $artists = $this->serviceArtist->artistList();

        if($artists && isset($artists['results'])) {

            $ret = $artists['results']->toArray();
        }

        return $ret;
    }

    /**
     * Return Status List
     *
     * @param   array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-27
     */
    public function getStatusList() {
        $ret = [];

        $ret = Config::get('app.status');

        return $ret;
    }


    /**
     * Return Platform List
     *
     * @param   array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-27
     */
    public function getPlatformList() {
        $ret = [];

        $ret = array_except(Config::get('app.platforms'), 'paytm');

        return $ret;
    }


    /**
     * Return Priority List
     *
     * @param   array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-27
     */
    public function getPriorityList() {
        $ret = [];

        $ret = $this->repObj->getPriorities();

        return $ret;
    }


    /**
     * Return Event List
     *
     * @param   array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-27
     */
    public function getEventList() {
        $ret = [];

        $ret = $this->repObj->getEvents();

        return $ret;
    }



    /**
     * Return sanitized data
     *
     * @param   array   $data
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function sanitizedData($data) {
        $ret = [];

        if(isset($data['start_at']) && empty($data['start_at'])) {
            unset($data['start_at']);
        }

        if(isset($data['end_at']) && empty($data['end_at'])) {
            unset($data['end_at']);
        }

        $ret = $data;

        return $ret;
    }


    /**
     * Return On Event Reward Program for artist
     *
     * @param   string   $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-27
     */
    public function getOnEventForArtist($event, $artist_id = '') {
        $ret    = [];

        $record = $this->repObj->findByEventAndArtist($event, $artist_id);
        if($record) {
            $ret = $record->toArray();
        }

        return $ret;
    }


    /**
     * Return On Registration Reward Program for artist
     *
     * @param   string   $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-27
     */
    public function getOnRegistrationForArtist($artist_id = '') {
        $ret    = [];
        $event  = 'on_registration';

        $record = $this->repObj->findByEventAndArtist($event, $artist_id);
        if($record) {
            $ret = $record->toArray();
        }

        return $ret;
    }

}
