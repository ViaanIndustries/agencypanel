<?php

namespace App\Repositories\Mongo;

/**
 * RepositoryName : Live.
 *
 *
 * @author      Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since       2019-05-29
 * @link        http://bollyfame.com
 * @copyright   2019 BOLLYFAME
 * @license     http://bollyfame.com//license
 */

use App\Repositories\Contracts\LiveInterface;
use App\Repositories\AbstractRepository;
use App\Models\Live;
use App\Models\Cmsuser;

use Config;
use DB;
use Carbon;

class LiveRepository extends AbstractRepository implements LiveInterface
{

    protected $modelClassName = 'App\Models\Live';


    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Return Live Event List
     *
     * @param array $requestData
     * @param integer $perpage
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function index_old($requestData, $perpage = NULL)
    {
        $results = [];
        $perpage = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $name = (isset($requestData['name']) && $requestData['name'] != '') ? $requestData['name'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';

        $appends_array = array('artist_id' => $artist_id, 'name' => $name, 'status' => $status);

        $query = $this->model->with(array('leadcast' => function ($q) {
            $q->select('_id', 'first_name', 'last_name');
        }))->orderBy('schedule_at');

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        if ($name != '') {
            $query->where('name', 'LIKE', $name . '%');
        }

        if ($status != '') {
            $query->where('status', $status);
        }

        $results['lives'] = $query->paginate($perpage);
        $results['appends_array'] = $appends_array;

        return $results;
    }


    /**
     * Return Live Event List with pagination
     *
     * @param array $requestData
     * @param integer $perpage
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function list($requestData, $perpage = NULL)
    {
        $results = [];
        $data_arr = [];
        $data = $this->index($requestData, $perpage);

        if ($data) {
            $lives = isset($data['lives']) ? $data['lives'] : [];
            if ($lives) {
                $data_arr = $lives->toArray();
                if ($data_arr) {
                    $results['list'] = isset($data_arr['data']) ? $data_arr['data'] : [];
                    $results['paginate_data']['total'] = (isset($data_arr['total'])) ? $data_arr['total'] : 0;
                    $results['paginate_data']['per_page'] = (isset($data_arr['per_page'])) ? $data_arr['per_page'] : 0;
                    $results['paginate_data']['current_page'] = (isset($data_arr['current_page'])) ? $data_arr['current_page'] : 0;
                    $results['paginate_data']['last_page'] = (isset($data_arr['last_page'])) ? $data_arr['last_page'] : 0;
                    $results['paginate_data']['from'] = (isset($data_arr['from'])) ? $data_arr['from'] : 0;
                    $results['paginate_data']['to'] = (isset($data_arr['to'])) ? $data_arr['to'] : 0;
                }
            }
        }

        return $results;
    }


    /**
     * Return List Query
     *
     * @param array $requestData
     * @param integer $perpage
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function listQuery($requestData)
    {
        $query = null;
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $sort_by = (isset($requestData['sort_by']) && $requestData['sort_by'] != '') ? $requestData['sort_by'] : '';
        $query = \App\Models\Live::with('casts');

        $query->with(array('leadcast' => function ($q) {
            $q->select('_id', 'first_name', 'last_name');
        }));

        $query->where('status', '=', 'active');

        $schedule_at_obj = Carbon::today();
        $schedule_end_at_obj = Carbon::now();

        if ($platform != '') {
            $query->whereIn('platforms', [$platform]);
        }

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        if ($sort_by) {
            if (is_array($sort_by)) {
                foreach ($sort_by as $key => $value) {
                    $query->orderBy($key, $value);
                }
            } else {
                switch (strtolower($sort_by)) {
                    case 'upcoming':
                        $query->where('status', 'active');
                        //$query->where('schedule_at', '>', $schedule_at_obj);
                        $query->where('schedule_end_at', '>', $schedule_end_at_obj);
                        $query->orderBy('schedule_at');
                        break;

                    case 'past':
                        $query->where('status', 'active');
                        $query->where('schedule_end_at', '<', $schedule_at_obj);
                        $query->orderBy('schedule_at', 'desc');
                        break;

                    default:
                        # code...
                        break;
                }
            }
        }

        return $query;
    }


    public function listQueryPagination($requestData, $perpage = '')
    {
        $ret = null;

        $query = $this->listQuery($requestData);
        $results = $query->paginate($perpage);

        if ($results) {
            $ret = $results->toArray();
        }

        return $ret;
    }

    /**
     * Return Live Event By Artist
     *
     * @param string $arist_id
     * @param array $data
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function artistEventsBy($artist_id, $requestData, $perpage = NULL)
    {
        $results = [];
        $data_arr = [];

        $results = $this->listQueryPagination($requestData);

        return $results;
    }


    /**
     * Return Live Event List By Artist
     *
     * @param string $arist_id
     * @param array $data
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function listArtistEventsBy($artist_id, $requestData, $perpage = NULL)
    {
        $results = [];
        $data_arr = [];
        $data_arr = $this->artistEventsBy($artist_id, $requestData, $perpage);
        if ($data_arr) {
            $results['list'] = (isset($data_arr['data'])) ? $data_arr['data'] : [];
            $results['paginate_data']['total'] = (isset($data_arr['total'])) ? $data_arr['total'] : 0;
            $results['paginate_data']['per_page'] = (isset($data_arr['per_page'])) ? $data_arr['per_page'] : 0;
            $results['paginate_data']['current_page'] = (isset($data_arr['current_page'])) ? $data_arr['current_page'] : 0;
            $results['paginate_data']['last_page'] = (isset($data_arr['last_page'])) ? $data_arr['last_page'] : 0;
            $results['paginate_data']['from'] = (isset($data_arr['from'])) ? $data_arr['from'] : 0;
            $results['paginate_data']['to'] = (isset($data_arr['to'])) ? $data_arr['to'] : 0;
        }

        return $results;
    }


    /**
     * Create/Store new record in database
     *
     * @param array $data
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function store($data)
    {
        \Log::info(__METHOD__ . ' $data :', $data);
        $recodset = new $this->model($data);
        $recodset->save();
        $this->syncCasts($data, $recodset);
        return $recodset;
    }

    /**
     * Update existing record in database
     *
     * @param array $data
     * @param string $id
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-10
     */
    public function update($data, $id)
    {
        \Log::info(__METHOD__ . ' $data :', $data);
        $recodset = $this->model->findOrFail($id);
        $recodset->update($data);
        $this->syncCasts($data, $recodset);
        return $recodset;
    }

    /**
     * Sync Casts
     *
     * @param array $data
     * @param object $id
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-10
     */
    public function syncCasts($data, $recodset)
    {
        \Log::info(__METHOD__ . ' $data :', $data);
        \App\Models\Cast::whereIn('lives', [$recodset->_id])->pull('lives', $recodset->_id);

        if (!empty($data['casts'])) {
            $casts = array_map('trim', $data['casts']);
            $recodset->casts()->sync(array());
            foreach ($casts as $key => $value) {
                $recodset->casts()->attach($value);
            }
        }
    }


    public function stats($requestData)
    {
        $stats = [];
        $orders_summary = [];
        $purchases_content_summary = [];
        $purchases_gifts_summary = [];
        $content_summary = [];
        $top_performing_gifts = [];

        $golive_id = (isset($requestData['live_id'])) ? trim($requestData['live_id']) : '';
        $goliveObjectExist = \App\Models\Live::where('_id', '=', $golive_id)->first();

        if ($goliveObjectExist) {

            $data['artist_id'] = (isset($goliveObjectExist['artist_id'])) ? $goliveObjectExist['artist_id'] : '';
            $data['start'] = (isset($goliveObjectExist['start_at'])) ? $goliveObjectExist['start_at'] : $goliveObjectExist['created_at'];
            $data['end'] = (isset($goliveObjectExist['end_at'])) ? $goliveObjectExist['end_at'] : $goliveObjectExist['updated_at'];

            if ($data['start'] != "" && $data['end'] != "") {

                $timeFirst = strtotime($data['start']);
                $timeSecond = strtotime($data['end']);

                $seconds = $timeSecond - $timeFirst;

                $differenceInSeconds = "";
                /*** get the days ***/
                $days = intval(intval($seconds) / (3600 * 24));
                if ($days > 0) {
                    $differenceInSeconds .= "$days days ";
                }

                /*** get the hours ***/
                $hours = (intval($seconds) / 3600) % 24;
                if ($hours > 0) {
                    $differenceInSeconds .= "$hours hrs ";
                }

                /*** get the minutes ***/
                $minutes = (intval($seconds) / 60) % 60;
                if ($minutes > 0) {
                    $differenceInSeconds .= "$minutes mins ";
                }

                /*** get the seconds ***/
                $seconds = intval($seconds) % 60;
                if ($seconds > 0) {
                    $differenceInSeconds .= "$seconds secs";
                }

                $params = [
                    'start' => $data['start'],
                    'end' => $data['end'],
                    'differenceInSeconds' => !empty($differenceInSeconds) ? $differenceInSeconds : 0

                ];
                $stats['params'] = $params;

                //Orders
                $orderRequestData = $data;
                $orderRequestData['created_at'] = $data['start'];
                $orderRequestData['created_at_end'] = $data['end'];
                $orderRequestData['user_type'] = 'genuine';
                $orderRequestData['entity'] = ['packages'];

                $orders_summary_query = $this->getPassbookQuery($orderRequestData);
                $orders_summary['count'] = intval($orders_summary_query->count());
                $orders_summary['coins'] = intval($orders_summary_query->sum('total_coins'));
                $orders_summary['prices'] = intval($orders_summary_query->sum('amount'));
                $orders_summary['xp'] = intval($orders_summary_query->sum('xp'));


                //Purchase Content
                $purchasesContentRequestData = $data;
                $purchasesContentRequestData['created_at'] = $data['start'];
                $purchasesContentRequestData['created_at_end'] = $data['end'];
                $purchasesContentRequestData['user_type'] = 'genuine';
                $purchasesContentRequestData['entity'] = ['contents'];

                $purchases_content_summary_query = $this->getPassbookQuery($purchasesContentRequestData);
                $purchases_content_summary['count'] = intval($purchases_content_summary_query->count());
                $purchases_content_summary['coins'] = intval($purchases_content_summary_query->sum('total_coins'));
                $purchases_content_summary['prices'] = intval($purchases_content_summary_query->sum('amount'));
                $purchases_content_summary['xp'] = intval($purchases_content_summary_query->sum('xp'));

                //Purchase Gifts
                $purchasesGiftRequestData = $data;
                $purchasesGiftRequestData['created_at'] = $data['start'];
                $purchasesGiftRequestData['created_at_end'] = $data['end'];
                $purchasesGiftRequestData['user_type'] = 'genuine';
                $purchasesGiftRequestData['entity'] = ['gifts'];
                $purchases_gifts_summary_query = $this->getPassbookQuery($purchasesGiftRequestData);
                $purchases_gifts_summary['count'] = intval($purchases_gifts_summary_query->count());
                $purchases_gifts_summary['coins'] = intval($purchases_gifts_summary_query->sum('total_coins'));
                $purchases_gifts_summary['prices'] = intval($purchases_gifts_summary_query->sum('amount'));
                $purchases_gifts_summary['xp'] = intval($purchases_gifts_summary_query->sum('xp'));


                //Likes/Comments/Views
                $content_summary['likes'] = (isset($goliveObjectExist['stats']) && isset($goliveObjectExist['stats']['likes'])) ? intval($goliveObjectExist['stats']['likes']) : 0;
                $content_summary['comments'] = (isset($goliveObjectExist['stats']) && isset($goliveObjectExist['stats']['comments'])) ? intval($goliveObjectExist['stats']['comments']) : 0;
                $content_summary['views'] = (isset($goliveObjectExist['stats']) && isset($goliveObjectExist['stats']['views'])) ? intval($goliveObjectExist['stats']['views']) : 0;


                /*

                $top_performing_gifts = $this->getGiftsWiseOrdersStats($data);

                */


                $stats['orders_summary'] = $orders_summary;
                $stats['purchases_contents_summary'] = $purchases_content_summary;
                $stats['purchases_gifts_summary'] = $purchases_gifts_summary;
                $stats['content_summary'] = $content_summary;
                //$stats['top_performing_gifts']          =   $top_performing_gifts;
            }
        }

        return isset($stats) ? $stats : [];
    }


    public function getPassbookQuery($requestData)
    {


        $artists = [];
        if (!empty($requestData['artist_id'])) {
            $artists = (is_array($requestData['artist_id'])) ? $requestData['artist_id'] : [trim($requestData['artist_id'])];
        }
        $entity = (!empty($requestData['entity']) && count($requestData['entity']) > 0) ? $requestData['entity'] : [];
        $entity_id = (isset($requestData['entity_id']) && $requestData['entity_id'] != '') ? $requestData['entity_id'] : '';
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $customer_id = (isset($requestData['customer_id']) && $requestData['customer_id'] != '') ? trim($requestData['customer_id']) : '';
        $customer_name = (isset($requestData['customer_name']) && $requestData['customer_name'] != '') ? $requestData['customer_name'] : '';
        $user_type = (isset($requestData['user_type']) && $requestData['user_type'] != '') ? $requestData['user_type'] : '';
        $txn_id = (isset($requestData['txn_id']) && $requestData['txn_id'] != '') ? $requestData['txn_id'] : '';
        $txn_type = (isset($requestData['txn_type']) && $requestData['txn_type'] != '') ? strtolower(trim($requestData['txn_type'])) : '';
        $vendor_txn_id = (isset($requestData['vendor_txn_id']) && $requestData['vendor_txn_id'] != '') ? $requestData['vendor_txn_id'] : '';
        $vendor = (isset($requestData['vendor']) && $requestData['vendor'] != '') ? $requestData['vendor'] : '';
        $reward_event = (isset($requestData['reward_event']) && $requestData['reward_event'] != '') ? $requestData['reward_event'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : 'success';
//        $created_at         =   (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
//        $created_at_end     =   (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';

        $created_at = mongodb_start_date_millsec((isset($requestData['created_at']) && $requestData['created_at'] != '') ? $requestData['created_at'] : '');
        $created_at_end = mongodb_end_date_millsec((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? $requestData['created_at_end'] : '');

        $txn_types = Config::get('app.passbook.txn_types');

        $query = \App\Models\Passbook::orderBy('created_at', 'desc');

        if (!empty($customer_id)) {
            $query->where('customer_id', $customer_id);
        }

        if (!empty($artists)) {
            $query->whereIn('artist_id', $artists);
        }


        if (!empty($entity)) {
            $query->whereIn('entity', $entity);
        }

        if ($entity_id != '') {
            $query->where('entity_id', $entity_id);
            $query->orWhere('live_id', $entity_id);
        }

        if ($txn_type != '' && in_array($txn_type, $txn_types)) {
            $query->where('txn_type', $txn_type);
        }

        if ($platform != '') {
            $query->where('platform', $platform);
        }

        if ($txn_id != '') {
            $query->where('_id', $txn_id);
        }

        if ($status != '') {
            $query->where('status', $status);
        }

        if ($reward_event != '') {
            $query->where('txn_meta_info.reward_event', $reward_event);
        }

        if ($vendor_txn_id != '') {
            $query->where('txn_meta_info.vendor_txn_id', $vendor_txn_id);
        }

        if ($vendor != '') {
            $query->where('txn_meta_info.vendor', $vendor);
        }

        if ($user_type && $user_type == 'genuine') {
            $query->GenuineCustomers($customer_name);
        }

        if ($user_type && $user_type != 'genuine') {
            $query->NotGenuineCustomers($customer_name);
        }

        if ($created_at != '') {

            $query->where("created_at", '>=', $created_at);
        }

        if ($created_at_end != '') {
            $query->where("created_at", '<=', $created_at_end);

        }


        return $query;


    }


    public function index($requestData, $perpage = NULL)
    {

        $results = [];
        $perpage = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
        $sort = (isset($requestData['sort']) && $requestData['sort'] != '') ? $requestData['sort'] : '';
        $agency_id = $requestData['agency_id'];
        $sort = (isset($requestData['sort']) && $requestData['sort'] != '') ? $requestData['sort'] : '';
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $created_at = (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end = (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';
          $appends_array = array('artist_id' => $artist_id,  'sort' => $sort, 'agency_id' => $agency_id, 'created_at' => $created_at,
            'created_at_end' => $created_at_end, 'sort' => $sort);

        $items = $this->getLiveHistory($requestData)->paginate($perpage);
        $data = $items->toArray();
        $array = array_pluck($data['data'], 'total_earning_doller');
        $total_earning_doller = array_sum($array);

        $results['lives'] = $items;
        $results['coins'] = $this->getLiveHistory($requestData)->sum('stats.coin_spent');
        $results['total_earning_doller'] = $total_earning_doller; //$this->getLiveHistory($requestData)->sum('total_earning_doller');
        $results['appends_array'] = $appends_array;
        return $results;
    }
    public function getLiveHistory($requestData,$perpage = NULL)
    {
        $perpage = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
        $sort = (isset($requestData['sort']) && $requestData['sort'] != '') ? $requestData['sort'] : '';
        $agency_id = $requestData['agency_id'];
        $sort = (isset($requestData['sort']) && $requestData['sort'] != '') ? $requestData['sort'] : '';
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $created_at = (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end = (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';


        $artist_list = Cmsuser::where('agency', $agency_id)->pluck('_id');
        $query = Live::where('is_refund', '<>', true)->with(array('artist' => function ($q) {
            $q->select('_id', 'picture', 'coins', 'stats', 'first_name', 'last_name', 'about_us', 'city', 'agency', 'mobile', 'email', 'dob');
        }));
        if (!empty($artist_id)) {
            $query->where('artist_id', $artist_id);
        } else {
            $query->whereIn('artist_id', $artist_list);
        }
        if ($created_at != '') {
            $query->where('start_at', '>', mongodb_start_date($created_at));
        }

        if ($created_at_end != '') {
            $query->where('start_at', '<', mongodb_end_date($created_at_end));
        }
        if ($sort == 'coins') {
            $query->orderby('stats.coin_spent', 'DESC');
        } else if ($sort == 'views')
        {
            $query->orderby('stats.views', 'DESC');
        }else if($sort == 'gifts')
        {
            $query->orderby('stats.gifts', 'DESC');
        }
        return $query;
//        $rows = $query->paginate($perpage);
//        $coins =   $query->sum('stats.coin_spent');
//
//        $results = $rows->toArray();
//
//        $results['lives']         =$rows;
//        $results['coins']         =$coins;
//
//        $results['appends_array']   = $appends_array;
//        return $results;
    }

    public function getArtistReportCount($entity_id)
    {
        $results = [];
        $type = 'live';
        $entity = 'report';

        $query = \App\Models\Feedback::where('type', $type)->where('entity', $entity)->where('entity_id', $entity_id);

        $results = $query->count();

        return $results;
    }


    public function upcomingEventList($requestData, $perpage = NULL)
    {

        $results = [];
        $perpage = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
        $sort = (isset($requestData['sort']) && $requestData['sort'] != '') ? $requestData['sort'] : '';
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';

        $appends_array = array('artist_id' => $artist_id, 'sort' => $sort, 'status' => $status);

        $schedule_end_at_obj = Carbon::now();


        $query = \App\Models\UpcomingEvents::with(array('artist' => function ($q) {
            $q->select('_id', 'picture', 'coins', 'stats', 'first_name', 'last_name', 'about_us', 'city', 'signature_msg', 'is_featured', 'platform', 'allow_packages', 'status');
        }));

        if ($sort != '') {
            if ($sort == "upcoming") {
                $query->where('schedule_at', '>', $schedule_end_at_obj);
            } else if ($sort == "past") {

                $query->where('schedule_at', '<', $schedule_end_at_obj);
            }


        }


        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }


        if ($status != '') {
            $query->where('status', $status);
        }

        $results['lives'] = $query->paginate($perpage);
        $results['appends_array'] = $appends_array;


        return $results;
    }

    public function upcomingEventData($event_id)
    {

        $results = [];
        $query = \App\Models\UpcomingEvents::where('_id', $event_id)->with(array('artist' => function ($q) {
            $q->select('_id', 'picture', 'coins', 'stats', 'first_name', 'last_name', 'about_us', 'city', 'signature_msg', 'is_featured', 'platform', 'allow_packages', 'status');
        }));
        $results = $query->paginate(1); //->toArray();
        return $results;
    }


    public function refundLiveEvent($requestData)
    {
        $results = [];
        $data_arr = [];
        $refundlist = [];
        $passbookdata = [];
        $requestData = [];
        $data_arr = \App\Models\Live::where('is_end', true)->where('is_refund', '<>', true)->where('ended_by', '=', 'cron')->get()->toArray();

        if ($data_arr) {

            foreach ($data_arr as $value) {
                $date = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $value['end_at']);
                $enddate = $date->toDateString();
                $curdate = Carbon::today();
                $currentDate = $curdate->toDateString();
                if ($enddate == $currentDate) {
                    $start_at = strtotime($value['start_at']);
                    $end_at = strtotime($value['end_at']);
                    $seconds = $end_at - $start_at;

                    /*** get the minutes ***/
                    $minutes = (intval($seconds) / 60) % 60;
                    $value['diffInMinutes'] = $seconds;
                    if ($seconds <= 300) {

                        $passbookdata['entity_id'] = $value['_id'];
                        $rows = $this->getPassbookQuery($passbookdata);
                        $passbook = $rows->get();
                        if (count($passbook) > 0) {

                            foreach ($passbook as $book) {
                                $requestData['event_id'] = $value['_id'];
                                $requestData['remark'] = "producer_live_event";
                                $requestData['customer_id'] = $book['customer_id'];
                                $requestData['refund_coins'] = $book['coins'];
                                $refund = $this->CustomerRepo->customerAddCoins($requestData);
                            }
                        }
                        $goliveObjectExist = \App\Models\Live::where('_id', '=', $value['_id'])->first();
                        $goliveObjectExist->is_refund = true;
                        $goliveObjectExist->save();

                        $value['passbookdata'] = $passbook;
                        $refundlist[] = $value;
                    }
                }
            }
            $results = $refundlist;


        }
        return $results;
    }


}
