<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\GoliveInterface;
use App\Repositories\Contracts\RepositoryInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Golive as Golive;
use Carbon, Log, Config;
use App\Repositories\Contracts\OrderInterface;
use App\Repositories\Contracts\PurchaseInterface;

class GoliveRepository extends AbstractRepository implements GoliveInterface
{
    protected $modelClassName = 'App\Models\Golive';
    protected $orderRep;
    protected $purchaseRep;

    public function __construct(
        OrderInterface $orderRep,
        PurchaseInterface $purchaseRep
    )
    {
        parent::__construct();
        $this->orderRep = $orderRep;
        $this->purchaseRep = $purchaseRep;
    }


    public function getGoliveQuery($requestData)
    {
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $created_at = (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end = (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';

        $test = (isset($requestData['test']) && $requestData['test'] != '') ? $requestData['test'] : 'false';
        $live_type = (isset($requestData['type']) && $requestData['type'] != '') ? $requestData['type'] : 'general';

        $query = \App\Models\Golive::with('artist')->orderBy('created_at', 'desc');

        if ($platform != '') {
            $query->where('platform', $platform);
        }

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        if ($test != '') {
            $query->where('test', $test);
        }

        if ($live_type != '') {
            $query->where('type', $live_type);
        }

        if ($created_at != '') {
            $query->where('created_at', '>', mongodb_start_date($created_at));
        }

        if ($created_at_end != '') {
            $query->where('created_at', '<', mongodb_end_date($created_at_end));
        }

        return $query;
    }


    public function index($requestData)
    {
        $results = [];
        $artist_id = [];
        $perpage = 10;
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $live_type = (isset($requestData['type']) && $requestData['type'] != '') ? $requestData['type'] : 'general';

        $created_at = (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end = (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';

        $test = (isset($requestData['test']) && $requestData['test'] != '') ? $requestData['test'] : 'false';
        $appends_array = [
            'platform' => $platform,
            'artist_id' => $artist_id,
            'created_at' => $created_at,
            'created_at_end' => $created_at_end,
            'test' => $test,
            'type' => $live_type,
        ];

        $lists = $this->getGoliveQuery($requestData)->paginate($perpage);

        $results['golives'] = $lists;
        $results['appends_array'] = $appends_array;
        return $results;
    }

    public function getOrderSummaryQuery($requestData)
    {
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';

        $created_at = mongodb_start_date_millsec((isset($requestData['start']) && $requestData['start'] != '') ? $requestData['start'] : '');
        $created_at_end = mongodb_end_date_millsec((isset($requestData['end']) && $requestData['end'] != '') ? $requestData['end'] : '');
        $query = \App\Models\Order::orderBy('created_at', 'desc');

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }
        if ($created_at != '') {

            $query->where("created_at", '>=', $created_at);
        }

        if ($created_at_end != '') {
            $query->where("created_at", '<=', $created_at_end);

        }

        $query->where('order_status', 'successful');
        $customer_name = '';
        $query->GenuineCustomers($customer_name);

        return $query;
    }


    public function getPurchaseSummaryQuery($requestData)
    {
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $created_at = mongodb_start_date_millsec((isset($requestData['start']) && $requestData['start'] != '') ? $requestData['start'] : '');
        $created_at_end = mongodb_end_date_millsec((isset($requestData['end']) && $requestData['end'] != '') ? $requestData['end'] : '');

        $query = \App\Models\Purchase::orderBy('created_at', 'desc');

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        if ($created_at != '') {
            $query->where("created_at", '>=', $created_at);
        }

        if ($created_at_end != '') {
            $query->where("created_at", '<=', $created_at_end);
        }

        $customer_name = '';
        $query->GenuineCustomers($customer_name);


        return $query;
    }


    public function stats($requestData)
    {
        $stats = [];
        $orders_summary = [];
        $purchases_summary = [];
        $content_summary = [];

        $golive_id = (isset($requestData['golive_id'])) ? trim($requestData['golive_id']) : '';
        $goliveObjectExist = \App\Models\Golive::where('_id', '=', $golive_id)->first();

        if ($goliveObjectExist) {

            $data['artist_id'] = (isset($goliveObjectExist['artist_id'])) ? $goliveObjectExist['artist_id'] : '';
            $data['start'] = (isset($goliveObjectExist['start'])) ? $goliveObjectExist['start'] : $goliveObjectExist['created_at'];
            $data['end'] = (isset($goliveObjectExist['end'])) ? $goliveObjectExist['end'] : $goliveObjectExist['updated_at'];

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
                $orders_summary['count'] = intval($this->getOrderSummaryQuery($data)->count());
                $orders_summary['coins'] = intval($this->getOrderSummaryQuery($data)->sum('package_coins'));
                $orders_summary['prices'] = intval($this->getOrderSummaryQuery($data)->sum('package_price'));
                $orders_summary['xp_earns'] = intval($this->getOrderSummaryQuery($data)->sum('package_xp'));

                //Purchase
                $purchasesentitylists = $this->getPurchaseSummaryQuery($data)->where('entity', 'contents')->get()->toArray();
                $purchasesgiftlists = $this->getPurchaseSummaryQuery($data)->where('entity', 'gifts')->get()->toArray();

                $sumEntityCoins = 0;
                $sumGiftCoins = 0;

                foreach ($purchasesentitylists as $val) {
                    $entityObj = \App\Models\Content::where('_id', $val['entity_id'])->first();
                    $sumEntityCoins += $entityObj['coins'];
                }

                foreach ($purchasesgiftlists as $subval) {
                    $entityObj = \App\Models\Gift::where('_id', $subval['entity_id'])->first();
                    $sumGiftCoins += $entityObj['coins'];
                }

                $purchases_summary['contents'] = intval(count($purchasesentitylists));
                $purchases_summary['content_coins_spent'] = intval(count($purchasesgiftlists));
                $purchases_summary['gifts'] = intval($sumEntityCoins);
                $purchases_summary['gifts_coins_spent'] = intval($sumGiftCoins);

                //Likes/Comments/Views
                $content_summary['likes'] = (isset($goliveObjectExist['likes_count'])) ? $goliveObjectExist['likes_count'] : '';
                $content_summary['comments'] = (isset($goliveObjectExist['comments_count'])) ? $goliveObjectExist['comments_count'] : '';
                $content_summary['views'] = (isset($goliveObjectExist['views_count'])) ? $goliveObjectExist['views_count'] : '';


                $top_performing_gifts = $this->getGiftsWiseOrdersStats($data);

                $stats['orders_summary'] = $orders_summary;
                $stats['purchases_summary'] = $purchases_summary;
                $stats['content_summary'] = $content_summary;
                $stats['top_performing_gifts'] = $top_performing_gifts;
            }
        }

        return isset($stats) ? $stats : [];
    }

    public function getGiftsWiseOrdersStats($requestData)
    {
        $not_genuine_cids = not_genuine_cids();
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';

        if (!empty($artist_id)) {
            $artists = \App\Models\Cmsuser::where('status', '=', 'active')->where('_id', $artist_id)->get(['first_name', 'last_name', '_id'])->toArray();
            $artist_ids = array($artist_id);
        }

        /*
         * http://iknowit.inf.ovh/database/mongodb/aggregate-queries-examples
         * https://differential.com/insights/mongodb-aggregation-pipeline-patterns-part-1/
         */

//        $created_at = mongodb_start_date_millsec((isset($requestData['start']) && $requestData['start'] != '') ? $requestData['start'] : '');
//        $created_at_end = mongodb_end_date_millsec((isset($requestData['end']) && $requestData['end'] != '') ? $requestData['end'] : '');
        $created_at = mongodb_start_date_millsec((isset($requestData['created_at']) && $requestData['created_at'] != '') ? $requestData['created_at'] : Config::get('app.start_date'));

        $created_at_end = mongodb_end_date_millsec((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? $requestData['created_at_end'] : date('m/d/Y h:i:s', time()));

        $artistwise_customers = \App\Models\Purchase::raw(function ($collection) use ($artist_ids, $created_at, $created_at_end, $not_genuine_cids) {

            $aggregate = [
                [
                    '$match' => [
                        'artist_id' => ['$in' => $artist_ids],
                        'customer_id' => ['$nin' => $not_genuine_cids],
                        'entity' => ['$in' => ['gifts']],
                        '$and' => [
                            ["created_at" => ['$gte' => $created_at]],
                            ["created_at" => ['$lte' => $created_at_end]]
                        ],
                    ]
                ],
                [
                    '$group' => [
                        '_id' => ['entity_id' => '$entity_id'],
                        'coins' => ['$sum' => '$coins'],
                        'artist_id' => ['$last' => '$artist_id'],
                    ]
                ],
                [
                    '$project' => [
                        '_id' => '$_id.entity_id',
                        'entity_id' => '$_id.entity_id',
                        'coins' => '$coins',
                        'artist_id' => '$artist_id',
                    ]
                ],
                ['$sort' => ['coins' => -1]],
                ['$limit' => 10]
            ];
            return $collection->aggregate($aggregate);
        });

        $contentArr = [];
        $artistwise_customers = $artistwise_customers->toArray();
        $gift_ids = array_column($artistwise_customers, 'entity_id');
        $gift_result = \App\Models\Gift::where('status', '=', 'active')->whereIn('_id', $gift_ids)->get(['_id', 'name', 'type', 'photo', 'coins'])->toArray();

        foreach ($artistwise_customers as $customer) {
            $content_id = $customer['entity_id'];
            $artist_id = $customer['artist_id'];

            $artists_info = head(array_where($artists, function ($key, $val) use ($artist_id) {
                if ($val['_id'] == $artist_id) {
                    return $val;
                }
            }));
            $gift_info = head(array_where($gift_result, function ($key, $value) use ($content_id) {
                if ($value['_id'] == $content_id) {
                    return $value;
                }
            }));

            $customer['spent_coins'] = (isset($customer['coins'])) ? $customer['coins'] : 0;
            $name = $artists_info['first_name'] . ' ' . $artists_info['last_name'];
            $content_name = @$gift_info['name'];
            $gift_info['coins'] = @$gift_info['coins'];
            $_info = array_merge($customer, $gift_info, $artists_info, ['artist_name' => ucwords($name)], ['content_name' => $content_name]);
            array_push($contentArr, $_info);
        }

        return $contentArr;
    }

    public function store($postData)
    {

        $data = $postData;

        array_set($data, 'likes_count', intval($data['likes_count']));
        array_set($data, 'comments_count', intval($data['comments_count']));
        array_set($data, 'views_count', intval($data['views_count']));

        $recodset = new $this->model($data);
        $recodset->save();
        return $recodset;
    }


    public function update($postData, $id)
    {
        $data = $postData;

        array_set($data, 'likes_count', intval($data['likes_count']));
        array_set($data, 'comments_count', intval($data['comments_count']));
        array_set($data, 'views_count', intval($data['views_count']));

        //print_r($data); exit;
        $recodset = $this->model->findOrFail($id);
        $recodset->update($data);
        return $recodset;
    }


    public function start($postData)
    {
//        $test       =   (env('APP_ENV', 'stg') == 'production') ? "false" : "true";
        $data = $postData;
        array_set($data, 'start', Carbon::now());
        array_set($data, 'likes_count', 0);
        array_set($data, 'comments_count', 0);
        array_set($data, 'views_count', 0);
        array_set($data, 'is_processed', 'open');

        $recodset = new $this->model($data);
        $recodset->save();
        return $recodset;
    }


    public function end($postData)
    {
        $data                       =   $postData;
        $golive_id                  =   (isset($data['golive_id'])) ?  trim($data['golive_id']) : '';
        $recodset                   =   [];
        $goliveObjectExist          =   \App\Models\Golive::where('_id','=', $golive_id)->first();
        if($goliveObjectExist && !isset($goliveObjectExist['end']) ){
            array_set($data, 'end', Carbon::now());
            array_set($data, 'likes_count', intval($data['likes_count']));
            array_set($data, 'comments_count', intval($data['comments_count']));
            array_set($data, 'views_count', intval($data['views_count']));
            $recodset = $this->model->findOrFail($golive_id);
            $recodset->update($data);
        }
        return $recodset;
    }

    public function updateGoliveAdmin($data){
        $golive_id                  =   (isset($data['golive_id'])) ?  trim($data['golive_id']) : '';
        $recodset                   =   [];
        $goliveObjectExist          =   \App\Models\Golive::where('_id','=', $golive_id)->first();
        if($goliveObjectExist){

            $start = (isset($data['start']) && $data['start'] != '') ? hyphen_date($data['start']) : ''; //Get Start date time
            $start = new \MongoDB\BSON\UTCDateTime(strtotime($start) * 1000);//timestamp of start datetime
            array_set($data, 'start', !empty($start) ? $start : Carbon::now());//checking start time getting then set else current time set

            $end = (isset($data['end']) && $data['end'] != '') ? hyphen_date($data['end']) : '';//Get End date time
            $end = new \MongoDB\BSON\UTCDateTime(strtotime($end) * 1000);//timestamp of end datetime
            array_set($data, 'end', !empty($end) ? $end : Carbon::now());//checking end time getting then set else current time set

            array_set($data, 'likes_count', intval($data['likes_count']));
            array_set($data, 'comments_count', intval($data['comments_count']));
            array_set($data, 'views_count', intval($data['views_count']));

            $recodset = $this->model->findOrFail($golive_id);
            $recodset->update($data);

        }
    }


    public function generateStats($params)
    {
        $response = [];
        $data = $params;
        $golive_id = (isset($data['golive_id'])) ? trim($data['golive_id']) : '';

        $goliveObjectExist = \App\Models\Golive::where('_id', '=', $golive_id)->first();
        if ($goliveObjectExist) {

        }
        return $response;
    }


}
