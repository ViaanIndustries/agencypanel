<?php

namespace App\Services;

/**
 * ServiceName : CustomerActivity.
 * Maintains a list of functions used for CustomerActivity.
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

use App\Repositories\Contracts\CustomerActivityInterface;
use App\Repositories\Contracts\CustomerInterface;
use App\Services\ArtistService;
use App\Services\RewardProgramService;
use App\Services\PassbookService;
use App\Services\Cache\AwsElasticCacheRedis;


class CustomerActivityService {

    protected $repObj;
    protected $serviceArtist;
    protected $serviceRewardProgram;
    protected $cache;
    protected $servicePassbook;

    private $cache_expire_time = 600; // 10 minutes in seconds

    public function __construct(CustomerActivityInterface $repObj, CustomerInterface  $customerRep, ArtistService $serviceArtist, AwsElasticCacheRedis $cache, RewardProgramService $serviceRewardProgram, PassbookService $servicePassbook) {
        $this->repObj               = $repObj;
        $this->customerRep          = $customerRep;
        $this->serviceArtist        = $serviceArtist;
        $this->cache                = $cache;
        $this->serviceRewardProgram = $serviceRewardProgram;
        $this->servicePassbook      = $servicePassbook;
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
            $results['customeractivity']    = $this->repObj->find($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function store($request) {
        $data           = $request->all();
        $error_messages = [];
        $results        = [];
        $customeractivity  = null;

        $data = $this->sanitizedData($data);

        if(empty($error_messages)) {
            $results['customeractivity'] = $this->storeData($data);

            $customer_id        = (!empty($data) && isset($data['customer_id'])) ? $data['customer_id'] : '';
            $artist_id          = (!empty($data) && isset($data['artist_id'])) ? $data['artist_id'] : '';
            $purge_params       = [
                'customer_id'   => $customer_id,
                'artist_id'     => $artist_id,
            ];
            $purge_result       = $this->cache->purgeAllCustomerActivityByArtistWiseListCache($purge_params);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function storeData($data) {
        $ret    = null;
        $data   = $this->sanitizedData($data);

        if(empty($error_messages)) {
            $ret = $this->repObj->store($data);

            $customer_id        = (!empty($data) && isset($data['customer_id'])) ? $data['customer_id'] : '';
            $artist_id          = (!empty($data) && isset($data['artist_id'])) ? $data['artist_id'] : '';
            $purge_params       = [
                'customer_id'   => $customer_id,
                'artist_id'     => $artist_id,
            ];
            $purge_result       = $this->cache->purgeAllCustomerActivityByArtistWiseListCache($purge_params);
        }

        return $ret;
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

            $results['customeractivity']   = $this->repObj->update($data, $id);

            $customer_id        = (!empty($data) && isset($data['customer_id'])) ? $data['customer_id'] : '';
            $artist_id          = (!empty($data) && isset($data['artist_id'])) ? $data['artist_id'] : '';
            $purge_params       = [
                'customer_id'   => $customer_id,
                'artist_id'     => $artist_id,
            ];
            $purge_result       = $this->cache->purgeAllCustomerActivityByArtistWiseListCache($purge_params);

        }

        return ['error_messages' => $error_messages, 'results' => $results];
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

        $ret = $data;

        return $ret;
    }


    /**
     * Save Customer Registration Activity
     *
     * @param   string  $customer_id
     * @param   string  $artist_id
     * @param   string  $event
     * @param   array   $data
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function saveActivityOnEvent($customer_id, $artist_id, $event, $data, $for_referrer = false) {
        $ret = [];
        $activity_data      = [];
        $passbook_data      = [];
        $activity_id        = '';
        $rewardprogram_id   = '';
        $platform           = isset($data['platform']) ? trim(strtolower($data['platform']))  : '';
        $platform_version   = isset($data['v']) ? trim(strtolower($data['v']))  : '';
        $xp                 = 0;
        $coins              = 0;
        $total_coins        = 0;
        $amount             = 0;
        $quantity           = 1;
        $coins_before_txn   = 0;
        $coins_after_txn    = 0;
        $xp_before_txn      = 0;
        $xp_after_txn       = 0;
        $status             = 'success';
        $remark             = '';


        $referrer_customer_id   = (!empty($data['referrer_customer_id'])) ? trim($data['referrer_customer_id']) : '';
        $referral_customer_id   = (!empty($data['referral_customer_id'])) ? trim($data['referral_customer_id']) : '';

        $reward_to_var      = 'referral';
        if($for_referrer) {
            $reward_to_var  = 'referrer';
            $referrer_customer_id = '';
        }


        // Prepare Customer Activity Data
        $activity_data['customer_id']   = $customer_id;
        $activity_data['artist_id']     = $artist_id;
        $activity_data['name']          = $event;
        $activity_data['status']        = 'active';

        if($referrer_customer_id) {
            $activity_data['referrer_customer_id']  = $referrer_customer_id;
        }

        if($referral_customer_id) {
            $activity_data['referral_customer_id']  = $referral_customer_id;
        }

        // Get Reward Program Detail for a event w.r.t artist
        $rewardprogram = $this->serviceRewardProgram->getOnEventForArtist($event, $artist_id);

        if($rewardprogram) {
            $rewardprogram_id = $rewardprogram['_id'];
            if($rewardprogram_id) {
                $activity_data['rewardprogram_id'] = $rewardprogram_id;
            }
        }

        // Get Customer Coins & XP's w.r.t Artist
        $customer = \App\Models\Customer::where('_id', '=', $customer_id)->first(['coins']);
        if($customer) {
            $coins_before_txn = isset($customer->coins) ? $customer->coins : 0;
        }

        $customer_artist = \App\Models\Customerartist::where('customer_id', $customer_id)->where('artist_id', $artist_id)->first([
            'xp', 'fan_xp', 'comment_channel_no', 'gift_channel_no'
        ]);

        if($customer_artist) {
            $xp_before_txn = isset($customer_artist->xp) ? $customer_artist->xp : 0;
        }
        else {
            // If not set
        }

        // First Create New Customer Activity for registration
        $activity = $this->storeData($activity_data);
        if($activity) {
            $activity_id = $activity->_id;

            // Now Make Entries in Passbook as per event & reward program

            // Prepare Customer Activity Data
            $passbook_data['entity']            = 'rewards';
            $passbook_data['entity_id']         = $rewardprogram_id;
            $passbook_data['customer_id']       = $customer_id;
            $passbook_data['artist_id']         = $artist_id;
            $passbook_data['platform']          = $platform;
            $passbook_data['platform_version']  = $platform_version;
            $passbook_data['rewardprogram_id']  = $rewardprogram_id;


            $passbook_data['txn_type']          = 'received';
            $passbook_data['status']            = 'success';
            $passbook_data['txn_meta_info']     = [];
            $passbook_data['customer_activity_id'] = $activity_id;

            if($referrer_customer_id) {
                $passbook_data['referrer_customer_id']  = $referrer_customer_id;
            }

            if($referral_customer_id) {
                $passbook_data['referral_customer_id']  = $referral_customer_id;
            }

            switch (strtolower($event)) {
                case 'on_registration':
                    if(isset($rewardprogram[ $reward_to_var . '_xp']) && $rewardprogram[$reward_to_var . '_xp']) {
                        $xp             = $rewardprogram[$reward_to_var . '_xp'];
                        $xp_after_txn   = $xp_before_txn + $rewardprogram[$reward_to_var . '_xp'];

                        $passbook_data['xp']            = $xp;
                        $passbook_data['xp_before_txn'] = $xp_before_txn;
                        $passbook_data['xp_after_txn']  = $xp_after_txn;
                    }

                    if(isset($rewardprogram[$reward_to_var . '_coins']) && $rewardprogram[$reward_to_var . '_coins']) {
                        $coins             = $rewardprogram[$reward_to_var . '_coins'];
                        $coins_after_txn   = $coins_before_txn + $rewardprogram[$reward_to_var . '_coins'];

                        $passbook_data['coins']             = $coins;
                        $passbook_data['total_coins']       = $coins;
                        $passbook_data['coins_before_txn']  = $coins_before_txn;
                        $passbook_data['coins_after_txn']   = $coins_after_txn;
                    }

                    $passbook_data['quantity']  = $quantity;
                    $passbook_data['amount']    = $amount;
                    $passbook_data['txn_meta_info']['reward_event'] = $event;
                    $passbook_data['txn_meta_info']['reward_type']  = 'coins';
                    $passbook_data['txn_meta_info']['reward_name']  = 'First time login';
                    $passbook_data['txn_meta_info']['reward_description']  = 'Rewarded ' . $coins .' coins for first time login';

                    if($for_referrer) {
                        $passbook_data['txn_meta_info']['reward_name']          = 'Referral program';
                        $passbook_data['txn_meta_info']['reward_description']   = 'Rewarded ' . $coins .' coins for referral program';
                    }

                    break;

                default:
                    # code...
                    break;
            }

            if($passbook_data) {
                $passbook_save_response = $this->servicePassbook->saveToPassbook($passbook_data);
                if($passbook_save_response) {
                    $passbook_id = '';
                    if (isset($passbook_save_response['results'])) {
                        $passbook_results = $passbook_save_response['results'];
                        if(isset($passbook_results['passbook'])) {
                            $passbook_obj = $passbook_results['passbook'];
                            if($passbook_obj) {
                                $passbook_id = isset($passbook_obj->_id) ? $passbook_obj->_id : '';
                            }
                        }
                    }

                    if($passbook_id) {
                        // Update Passbook Id with Customer Activity Record
                        $activity->passbook_id = $passbook_id;
                        $activity->save();

                        // Update Customer Coins
                        $customerObj = $this->customerRep->coinsDeposit($customer_id, $coins);

                        // Update Customer XP
                        $customerXpObj = $this->customerRep->xpDeposit($customer_id, $artist_id, $xp);

                        // Set Reward Property just for return object
                        $ret_reward = $passbook_data['txn_meta_info'];
                        $ret_reward['coins']  = $coins;
                        $ret_reward['xp']     = $xp;

                        $activity->reward = $ret_reward;

                        $ret = $activity;
                    }
                }
            }
        }

        return $ret;
    }


    /**
     * Save Customer Registration Activity
     *
     * @param   string  $customer_id
     * @param   string  $artist_id
     * @param   array   $data
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function onRegistration($customer_id, $artist_id, $data) {
        $ret    = null;
        $event  = 'on_registration';
        $data['event'] = $event;
        $referrer_customer_id = isset($data['referrer_customer_id']) ? $data['referrer_customer_id'] : '';

        $activity = $this->saveActivityOnEvent($customer_id, $artist_id, $event, $data);
        if($activity) {
            if($referrer_customer_id) {
                $data['referral_customer_id']   = $customer_id;
                $data['rewardprogram_id']       = isset($activity->rewardprogram_id) ? $activity->rewardprogram_id : '';
                $this->onRegistrationForReferrer($referrer_customer_id, $artist_id, $data);
            }

            // Default Return Array
            $ret = [
                'event' => $event,
                'name'  => 'On Registration Reward',
                'desc'  => "You've won 100 coins for registration",
                'coins' => 100,
                'xp'    => 10,
            ];

            if(isset($activity->reward)) {
                $ret['name']    = isset($activity->reward['reward_name']) ? $activity->reward['reward_name'] : $ret['name'];
                $ret['desc']    = isset($activity->reward['reward_description']) ? $activity->reward['reward_description'] : $ret['desc'];
                $ret['coins']   = isset($activity->reward['coins']) ? $activity->reward['coins'] : $ret['coins'];
                $ret['xp']      = isset($activity->reward['xp']) ? $activity->reward['xp'] : $ret['xp'];
            }
        }

        return $ret;
    }


    /**
     * Save Customer Registration Activity
     * 'artist_priority_customeractivities' => 'a:p:cact:list:',
     *
     * @param   string  $customer_id
     * @param   string  $artist_id
     * @param   array   $data
     *
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function onRegistrationForReferrer($customer_id, $artist_id, $data) {
        $ret    = null;
        $event  = 'on_registration';
        $data['event']          = $event;
        $data['for_referrer']   = true;

        $cache_client = $this->cache->PredisConnection();

        $save_data = $data;
        $save_data['customer_id']   = $customer_id;
        $save_data['artist_id']     = $artist_id;

        // Add Customer Registration Active w.r.t to referrer in background proccessing
        // Which will be latter processed via scheduled cron job
        $env_cache_key_lists = env_cache_key(Config::get('cache.hash_keys.artist_priority_customeractivities_list') . $artist_id . ':high');

        $save_key  = Config::get('cache.hash_keys.artist_priority_customeractivities') . $customer_id;
        $env_save_key_hash = env_cache_key($save_key); // KEYS for Customer Content View

        $cache_client->hmset($env_save_key_hash, $save_data);
        $cache_client->expire($env_save_key_hash, 864000); // 10 Days in Seconds

        $exist_key = $cache_client->exists($env_cache_key_lists);

        if (!$exist_key) {
            $cache_client->rpush($env_cache_key_lists, $env_save_key_hash);
        }
        else {
            $cache_client->rpushx($env_cache_key_lists, $env_save_key_hash); //Push values when not exists in keys
        }

        return $ret;
    }


    /**
     * Process Customer Activity In Cache
     * @param   array   $data
     *
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-30
     */
    public function processActivityInCache($data) {
        $ret    = null;
        $for_referrer = false;
        if(isset($data['for_referrer']) && $data['for_referrer']) {
            $for_referrer = true;
        }

        $customer_id= isset($data['customer_id']) ? $data['customer_id'] : '';
        $artist_id  = isset($data['artist_id']) ? $data['artist_id'] : '';
        $event      = isset($data['event']) ? $data['event'] : '';

        if($customer_id && $artist_id && $event) {
            $reward = $this->saveActivityOnEvent($customer_id, $artist_id, $event, $data, $for_referrer);
        }

        return $ret;
    }
}
