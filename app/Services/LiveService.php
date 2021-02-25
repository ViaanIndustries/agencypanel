<?php

namespace App\Services;

/**
 * ServiceName : Live.
 * Maintains a list of functions used for Live.
 *
 * @author      Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since       2019-06-28
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

use App\Repositories\Contracts\LiveInterface;
use App\Services\ArtistService;
use App\Services\PassbookService;
use App\Services\AccountService;

use App\Services\Image\Kraken;
use App\Services\Cache\AwsElasticCacheRedis;
use App\Models\UpcomingEvents;

class LiveService {

    protected $repObj;
    protected $serviceArtist;
    protected $servicePassbook;
    protected $serviceAccount;
    protected $kraken;
    protected $cache;

    private $cache_expire_time = 600; // 10 minutes in seconds

    public function __construct(LiveInterface $repObj, ArtistService $serviceArtist, Kraken $kraken, PassbookService $servicePassbook, AccountService $serviceAccount, AwsElasticCacheRedis $cache) {
        $this->repObj       = $repObj;
        $this->serviceArtist= $serviceArtist;
        $this->kraken       = $kraken;
        $this->servicePassbook  = $servicePassbook;
        $this->serviceAccount   = $serviceAccount;
        $this->cache            = $cache;
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
            $results['live']    = $this->repObj->find($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function store($request) {
        $data           = $request->all();
        $error_messages = $results = [];
        $live           = null;
        $schedule_at    = null;
        $schedule_end_at= null;

        // Default Stats objects
        $data['stats']  = [
            'views' => 0,
            'likes' => 0,
            'comments' => 0,
            'duration' => '00:00:00',
            'gifts' => 0,
            'coin_spent' => 0,
        ];

        $slug = (isset($data['name'])) ? str_slug($data['name']) : '';
        array_set($data, 'slug', $slug);

        if(isset($data['schedule_end_at']) && $data['schedule_end_at']) {
            $schedule_end_at = new DateTime($data['schedule_end_at']);
            if(isset($data['schedule_at']) && $data['schedule_at']) {
                $schedule_at = new DateTime($data['schedule_at']);
            }

            if($schedule_end_at < $schedule_at) {
                $error_messages[] = 'Scheduled End at datetime cannot be less than Scheduled at datetime';
            }
        }

        if ($request->hasFile('photo')) {
            $parmas     =   ['file' => $request->file('photo'), 'type' => 'lives'];
            $photo      =   $this->kraken->uploadToAws($parmas);
            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                array_set($data, 'photo', $photo['results']);
            }
        }

        $data = $this->sanitizedData($data);

        if(empty($error_messages)) {
            $results['live'] = $this->repObj->store($data);

            $artist_id          =   (!empty($data) && isset($data['artist_id'])) ? $data['artist_id'] : '';
            $purge_result       =   $this->cache->purgeAllArtistEventListCache(['artist_id' => $artist_id]);
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

            if ($request->hasFile('photo')) {
                $parmas     =   ['file' => $request->file('photo'), 'type' => 'lives'];
                $photo      =   $this->kraken->uploadToAws($parmas);
                if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                    array_set($data, 'photo', $photo['results']);
                }
            }

            $results['live']   = $this->repObj->update($data, $id);

            $purge_result      = $this->cache->purgeAllArtistEventListCache(['artist_id' => $artist_id]);
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
     * Return Live Event List By Artist
     *
     * @param   string      $arist_id
     * @param   array       $request
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function listArtistEventsBy($artist_id, $request) {
        $error_messages = [];
        $results        = [];
        $data           = $request->all();
        $sort_by        = isset($data['sort_by']) ? trim($data['sort_by']) : 'upcoming';
        $page           = (isset($data['page']) && $data['page'] != '') ? trim($data['page']) : '1';
        $platform       = (isset($data['platform']) && $data['platform'] != '') ? strtolower(trim($data['platform']))  : 'android';

        $cache_hash_key = Config::get('cache.hash_keys.artist_live_upcoming');
        if(strtolower($sort_by)   == 'past') {
            $cache_hash_key = Config::get('cache.hash_keys.artist_live_past');
        }

        $cache_params   = [];
        $hash_name      = env_cache_key( $cache_hash_key. $artist_id . ':' . $platform);
        $hash_field     = intval($page);
        $cache_miss     = false;

        $cache_params['hash_name']   =  $hash_name;
        $cache_params['hash_field']  =  (string) $hash_field;
        $cache_params['expire_time'] =  $this->cache_expire_time;


        $results  = $this->cache->getHashData($cache_params);
        if(!$results) {
            $results = $this->repObj->listArtistEventsBy($artist_id, $data);
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


    /**
     * Return Live Event List By Artist
     *
     * @param   string      $arist_id
     * @param   array       $request
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function purchase($request) {
        $error_messages = [];
        $results        = [];
        $data           = $request->all();

        $data['entity'] = 'lives';

        try  {
            // Find Logged in user Customer ID
            $customer_id   = $this->serviceAccount->getCustomerId();

            if($customer_id) {
                $request['customer_id'] = $customer_id;

                // Find Live detail
                $live_obj   = $this->repObj->find($data['entity_id']);
                if($live_obj) {
                   $request['coins'] = isset($live_obj->coins) ? $live_obj->coins : 0;

                    // Purchase Live Event Only When Live Event Coins is greater than zero
                    if($request['coins']) {
                        // Paid Live Event
                        $purchase    = $this->servicePassbook->purchaselive($request);

                        if($purchase) {
                            $results = isset($purchase['results']) ? apply_cloudfront_url($purchase['results']) : [];
                            $error_messages = isset($purchase['error_messages']) ? $purchase['error_messages'] : '';
                        }
                    }
                    else {
                        $customer_coins = null;
                        // Get Customer Info
                        // $customer =  \App\Models\Customer::where('_id', $customer_id)->first();
                        // if($customer) {
                        //     $customer_coins = isset($customer->coins) ? $customer->coins : 0;
                        // }

                        // Free Live Event
                        // In case Live Event Coins are zero mean that this live event is Free Live Event
                        // Create dummy purchase response
                        $results['purchase'] = [
                            // '_id'               => '',
                            // 'entity'            => 'lives',
                            // 'entity_id'         => '',
                            // 'customer_id'       => $customer_id,
                            // 'artist_id'         => '',
                            // 'platform'          => '',
                            // 'platform_version'  => '',
                            // 'xp'                => 0,
                            // 'coins'             => 0,
                            // 'total_coins'       => 0,
                            // 'quantity'          => 1,
                            // 'amount'            => 0,
                            'coins_before_txn'  => $customer_coins,
                            'coins_after_txn'   => $customer_coins,
                            // 'txn_type'          => 'paid',
                            // 'status'            => 'success',
                            // 'txn_meta_info'     => [],
                            // 'reference_id'      => 'NOT_EXIST',
                            // 'passbook_applied'  => 'true',
                            // 'updated_at'        => '',
                            // 'created_at'        => '',
                        ];
                    }
                }
                else {
                    $error_messages[] = 'Event not find';
                }
            }
            else {
                $error_messages[] = 'Customer not found';
            }
        }
        catch (\Exception $e) {
            $error_messages[] = $e->getMessage();
        }

        if($error_messages) {
            $results['status_code'] = 200;
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    /**
     * Validates data
     *
     * @param   array   $data
     * @param   string  $id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-23
     */
    public function validateData($data, $id = '') {
        $ret = true;

        // @TODO Validate data as per rules
        //$rules = [];

        return $ret;
    }


    /**
     * Start Live Event
     *
     * @param  \Illuminate\Http\Request
     * @param   string      $id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function start($request, $id) {
        $error_messages = [];
        $results        = [];
        $data           = $request->all();
        $update_data    = [];

        $record = $this->repObj->find($id);
        if(!$record) {
            $error_messages[] = 'Live Event not found';
        }

        if(empty($error_messages)) {
            if($record && !isset($record->start_at)) {
                $update_data['start_at']  = Carbon::now()->toDateTimeString();
            }

            if(isset($data['stats'])) {
                if(isset($data['stats']['views']) ) {
                    $update_data['stats']['views'] = $data['stats']['views'];
                }

                if(isset($data['stats']['likes']) ) {
                    $update_data['stats']['likes'] = $data['stats']['likes'];
                }

                if(isset($data['stats']['comments']) ) {
                    $update_data['stats']['comments'] = $data['stats']['comments'];
                }
            }

            $results['live']   = $this->repObj->update($update_data, $id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * End Live Event
     *
     * @param  \Illuminate\Http\Request
     * @param   string      $id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function end($request, $id) {
        $error_messages = [];
        $results        = [];
        $data           = $request->all();
        $update_data    = [];

        $record = $this->repObj->find($id);
        if(!$record) {
            $error_messages[] = 'Live Event not found';
        }

        if(empty($error_messages)) {
            $update_data['end_at']  = Carbon::now()->toDateTimeString();

            if(isset($data['stats'])) {
                if(isset($data['stats']['views']) ) {
                    $update_data['stats']['views'] = $data['stats']['views'];
                }

                if(isset($data['stats']['likes']) ) {
                    $update_data['stats']['likes'] = $data['stats']['likes'];
                }

                if(isset($data['stats']['comments']) ) {
                    $update_data['stats']['comments'] = $data['stats']['comments'];
                }
            }

            $results['live']   = $this->repObj->update($update_data, $id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function stats($request)
    {
        $requestData = $request->all();

        $results = $this->repObj->stats($requestData);
        return $results;
    }


	  public function LiveEventStore($request) {
        $data           = $request->all();
        $error_messages = $results = [];
        $live           = null;
        $schedule_at    = null;
        $schedule_end_at= null;

        $slug = (isset($data['name'])) ? str_slug($data['name']) : '';
        array_set($data, 'slug', $slug);

        if(isset($data['schedule_end_at']) && $data['schedule_end_at']) {
            $schedule_end_at = new DateTime($data['schedule_end_at']);
            if(isset($data['schedule_at']) && $data['schedule_at']) {
                $schedule_at = new DateTime($data['schedule_at']);
            }

            if($schedule_end_at < $schedule_at) {
                $error_messages[] = 'Scheduled End at datetime cannot be less than Scheduled at datetime';
            }
        }

      

        $data = $this->sanitizedData($data);

        if(empty($error_messages)) {
            $results['live'] = UpcomingEvents::create($data);

            $artist_id          =   (!empty($data) && isset($data['artist_id'])) ? $data['artist_id'] : '';
            $purge_result       =   $this->cache->purgeAllArtistEventListCache(['artist_id' => $artist_id]);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
      }

	public function upcomingEventList(Request $request)
	{

        	$data       = $request->all();
	        $results    = $this->repObj->upcomingEventList($data);
        	return $results;
	}
    public function getUpcomingEventById($event_id)
    {
	$results    = $this->repObj->upcomingEventData($event_id);
        return $results;

    }

     public function LiveEventUpdate($request) {
        $data           = $request->all();
        $error_messages = $results = [];
        $live           = null;
        $schedule_at    = null;
        $schedule_end_at= null;

        $slug = (isset($data['name'])) ? str_slug($data['name']) : '';
        array_set($data, 'slug', $slug);

        if(isset($data['schedule_end_at']) && $data['schedule_end_at']) {
            $schedule_end_at = new DateTime($data['schedule_end_at']);
            if(isset($data['schedule_at']) && $data['schedule_at']) {
                $schedule_at = new DateTime($data['schedule_at']);
            }

            if($schedule_end_at < $schedule_at) {
                $error_messages[] = 'Scheduled End at datetime cannot be less than Scheduled at datetime';
            }
        }

     

        $data = $this->sanitizedData($data);

        if(empty($error_messages)) {
		$event_obj = \App\Models\UpcomingEvents::where('_id', $data['event_id'])->first();
	
		
	if($event_obj)
	{

/*	\App\Models\UpcomingEvents::where('_id',  $data['event_id'])        
		->update(['name' => 'kkkkkkkkkkkkkk']);*/
		$event_obj->name =$data['name'];
		$event_obj->schedule_at = $data['schedule_at'];
		$event_obj->schedule_end_at = $data['schedule_end_at'];
		$event_obj->status = $data['status'];
		$event_obj->desc = $data['desc'];
		return $event_obj->save();

	}

        }

        return ['error_messages' => $error_messages, 'results' => $results];
      }



    public function refundLiveEvent(Request $request)
    {
        $error_messages=[];
        $requestData = $request->all();
        $results = $this->repObj->refundLiveEvent($requestData);
        return ['error_messages' => $error_messages, 'results' => $results];
    }


}



