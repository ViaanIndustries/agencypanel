<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\OrderInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Order as Order;
use Carbon, Log, Config;

class OrderRepository extends AbstractRepository implements OrderInterface
{

    protected $modelClassName = 'App\Models\Order';


    public function getOrderQuery($requestData)
    {
        $order_status = (isset($requestData['order_status']) && $requestData['order_status'] != '') ? $requestData['order_status'] : 'successful';
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $customer_name = (isset($requestData['customer_name']) && $requestData['customer_name'] != '') ? $requestData['customer_name'] : '';
        $user_type = (isset($requestData['user_type']) && $requestData['user_type'] != '') ? $requestData['user_type'] : 'genuine';
        $package_id = (isset($requestData['package_id']) && $requestData['package_id'] != '') ? $requestData['package_id'] : '';
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $vendor = (isset($requestData['vendor']) && $requestData['vendor'] != '') ? $requestData['vendor'] : '';
        $vendor_order_id = (isset($requestData['vendor_order_id']) && $requestData['vendor_order_id'] != '') ? $requestData['vendor_order_id'] : '';
        $order_id = (isset($requestData['order_id']) && $requestData['order_id'] != '') ? $requestData['order_id'] : '';

        $created_at = (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end = (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';

        $query = \App\Models\Order::with('artist', 'package', 'customer')->orderBy('created_at', 'desc');

        if ($order_status != '') {
            $query->where('order_status', $order_status);
        }

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        if ($package_id != '') {
            $query->where('package_id', $package_id);
        }

        if ($vendor_order_id != '') {
            $query->where('vendor_order_id', $vendor_order_id);
        }
        if ($order_id != '') {
            $query->where('_id', $order_id);
        }

        if ($created_at != '') {
            $query->where('created_at', '>', mongodb_start_date($created_at));
        }

        if ($created_at_end != '') {
            $query->where('created_at', '<', mongodb_end_date($created_at_end));
        }

        if ($platform != '') {
            $query->where('platform', $platform);
        }
        if ($vendor != '') {
            $query->where('vendor', $vendor);
        }

        if ($user_type != 'genuine') {
            $query->NotGenuineCustomers($customer_name);
        } else {
            $query->GenuineCustomers($customer_name);
        }

        return $query;

    }


    public function index($requestData)
    {
        $results = [];
        $artist_id = [];
        $perpage = 10;
        $order_status = (isset($requestData['order_status']) && $requestData['order_status'] != '') ? $requestData['order_status'] : 'successful';
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $package_id = (isset($requestData['package_id']) && $requestData['package_id'] != '') ? $requestData['package_id'] : '';
        $customer_name = (isset($requestData['customer_name']) && $requestData['customer_name'] != '') ? $requestData['customer_name'] : '';
        $user_type = (isset($requestData['user_type']) && $requestData['user_type'] != '') ? $requestData['user_type'] : 'genuine';
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $created_at = (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end = (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';
        $vendor = (isset($requestData['vendor']) && $requestData['vendor'] != '') ? $requestData['vendor'] : '';
        $order_id = (isset($requestData['order_id']) && $requestData['order_id'] != '') ? $requestData['order_id'] : '';
        $vendor_order_id = (isset($requestData['vendor_order_id']) && $requestData['vendor_order_id'] != '') ? $requestData['vendor_order_id'] : '';

        $appends_array = [
            'package_id' => $package_id,
            'customer_name' => $customer_name,
            'user_type' => $user_type,
            'order_status' => $order_status,
            'artist_id' => $artist_id,
            'platform' => $platform,
            'order_id' => $order_id,
            'vendor_order_id' => $vendor_order_id,
            'created_at' => $created_at,
            'created_at_end' => $created_at_end,
            'vendor' => $vendor,
        ];
        
        if (empty($requestData['data_report'])) {
            $results['orders'] = $this->getOrderQuery($requestData)->paginate($perpage);
        } else {
            $results['orders'] = $this->getOrderQuery($requestData)->get()->toArray();
        }
        $results['coins'] = $this->getOrderQuery($requestData)->sum('package_coins');
        $results['prices'] = $this->getOrderQuery($requestData)->sum('package_price');
        $results['appends_array'] = $appends_array;

        return $results;
    }

    public function getDashboardSalesStats($requestData)
    {
        $results = [];
        $order_status = (isset($requestData['order_status']) && $requestData['order_status'] != '') ? $requestData['order_status'] : 'successful';
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $user_type = (isset($requestData['user_type']) && $requestData['user_type'] != '') ? $requestData['user_type'] : 'genuine';
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';

        $created_at = (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end = (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';

        $appends_array = [
            'user_type' => $user_type,
            'order_status' => $order_status,
            'artist_id' => $artist_id,
            'platform' => $platform,
            'created_at' => $created_at,
            'created_at_end' => $created_at_end
        ];
        $results['orders_count'] = $this->getOrderQuery($requestData)->count();
        $results['coins'] = $this->getOrderQuery($requestData)->sum('package_coins');
        $results['prices'] = $this->getOrderQuery($requestData)->sum('package_price');
        $results['xp_earns'] = $this->getOrderQuery($requestData)->sum('package_xp');
        $results['order_wise'] = $this->getArtistWiseOrdersStats($requestData);
        $results['top_selling_packages'] = $this->getPackageWiseOrdersStats($requestData);
        $results['top_performing_contents'] = $this->getContentsWiseOrdersStats($requestData);
        $results['top_performing_gifts'] = $this->getGiftsWiseOrdersStats($requestData);
        $results['appends_array'] = $appends_array;

        return $results;
    }


    public function getArtistWiseOrdersStats($requestData)
    {
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $not_genuine_cids = not_genuine_cids();

        if (!empty($artist_id)) {
            $artists = \App\Models\Cmsuser::where('status', '=', 'active')->where('_id', $artist_id)->get(['first_name', 'last_name', '_id'])->toArray();
            $artist_ids = array($artist_id);
        } else {
            $artist_role_ids = \App\Models\Role::where('slug', 'artist')->lists('_id');
            $artist_role_ids = ($artist_role_ids) ? $artist_role_ids->toArray() : [];
            $artists = \App\Models\Cmsuser::where('status', '=', 'active')->whereIn('roles', $artist_role_ids)->get(['first_name', 'last_name', '_id'])->toArray();
            $artist_ids = array_pluck($artists, '_id');
        }

        if (!empty($platform)) {
            $platform = array($platform);
        } else {
            $platform = array_keys(Config::get('app.platforms'));
        }


//        $created_at = mongodb_start_date_millsec((isset($requestData['created_at']) && $requestData['created_at'] != '') ? $requestData['created_at'] : Config::get('app.start_date'));

//        $created_at_end = mongodb_end_date_millsec((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? $requestData['created_at_end'] : date('m/d/Y h:i:s', time()));

        $created_at = mongodb_start_date((isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '');
        $created_at_end = mongodb_end_date((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '');

        $artistwise_customers = \App\Models\Order::raw(function ($collection) use ($artist_ids, $platform, $created_at, $created_at_end, $not_genuine_cids) {

            $aggregate = [
                [
                    '$match' => [
                        'artist_id' => ['$in' => $artist_ids],
                        'platform' => ['$in' => $platform],
                        'customer_id' => ['$nin' => $not_genuine_cids],
                        'order_status' => ['$in' => ['successful']],
                        '$and' => [
                            ["created_at" => ['$gte' => $created_at]],
                            ["created_at" => ['$lte' => $created_at_end]]
                        ],
                    ]
                ],
                [
                    '$group' => [
                        '_id' => ['artist_id' => '$artist_id'],
                        'total_orders' => ['$sum' => 1],
                        "total_coins" => ['$sum' => '$package_coins'],
                        "total_price" => ['$sum' => '$package_price'],
                        "total_xp" => ['$sum' => '$package_xp']
                    ]
                ],
                [
                    '$project' => [
                        '_id' => '$_id.artist_id',
                        'artist_id' => '$_id.artist_id',
                        'total_orders' => '$total_orders',
                        'total_coins' => '$total_coins',
                        'total_price' => '$total_price',
                        'total_xp' => '$total_xp',
                    ]
                ]
            ];
            return $collection->aggregate($aggregate);
        });

        $artistsArr = [];
        $artistwise_customers = $artistwise_customers->toArray();

        foreach ($artists as $artist) {

            $artist_id = $artist['_id'];
            $name = $artist['first_name'] . ' ' . $artist['last_name'];

            $stats = head(array_where($artistwise_customers, function ($key, $value) use ($artist_id) {
                if ($value['_id'] == $artist_id) {
                    return $value;
                }
            }));

            $stats['total_orders'] = (isset($stats['total_orders'])) ? $stats['total_orders'] : 0;
            $stats['total_coins'] = (isset($stats['total_coins'])) ? $stats['total_coins'] : 0;
            $stats['total_price'] = (isset($stats['total_videos'])) ? $stats['total_price'] : 0;
            $stats['total_xp'] = (isset($stats['total_xp'])) ? $stats['total_xp'] : 0;
            $stats['artist_id'] = $artist_id;
            $artist_info = array_merge($artist, $stats, ['name' => ucwords($name)]);
            array_push($artistsArr, $artist_info);

        }
        return $artistsArr;
    }


    public function getPackageWiseOrdersStats($requestData)
    {
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $not_genuine_cids = not_genuine_cids();

        if (!empty($artist_id)) {
            $artists = \App\Models\Cmsuser::where('status', '=', 'active')->where('_id', $artist_id)->get(['first_name', 'last_name', '_id'])->toArray();
            $artist_ids = array($artist_id);
        } else {
            $artist_role_ids = \App\Models\Role::where('slug', 'artist')->lists('_id');
            $artist_role_ids = ($artist_role_ids) ? $artist_role_ids->toArray() : [];
            $artists = \App\Models\Cmsuser::where('status', '=', 'active')->whereIn('roles', $artist_role_ids)->get(['first_name', 'last_name', '_id'])->toArray();
            $artist_ids = array_pluck($artists, '_id');
        }

        if (!empty($platform)) {
            $platform = array($platform);
        } else {
            $platform = array_keys(Config::get('app.platforms'));
        }

//        $created_at = mongodb_start_date_millsec((isset($requestData['created_at']) && $requestData['created_at'] != '') ? $requestData['created_at'] : Config::get('app.start_date'));
//
//        $created_at_end = mongodb_end_date_millsec((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? $requestData['created_at_end'] : date('m/d/Y h:i:s', time()));
        $created_at = mongodb_start_date((isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '');
        $created_at_end = mongodb_end_date((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '');

        $artistwise_customers = \App\Models\Order::raw(function ($collection) use ($artist_ids, $platform, $created_at, $created_at_end, $not_genuine_cids) {

            $aggregate = [
                [
                    '$match' => [
                        'artist_id' => ['$in' => $artist_ids],
                        'platform' => ['$in' => $platform],
                        'customer_id' => ['$nin' => $not_genuine_cids],
                        'order_status' => ['$in' => ['successful']],
                        '$and' => [
                            ["created_at" => ['$gte' => $created_at]],
                            ["created_at" => ['$lte' => $created_at_end]]
                        ],
                    ]
                ],
                [
                    '$group' => [
                        '_id' => ['package_id' => '$package_id'],
                        'total_orders' => ['$sum' => 1],
                        "total_coins" => ['$sum' => '$package_coins'],
                        "total_price" => ['$sum' => '$package_price'],
                        "total_xp" => ['$sum' => '$package_xp'],
                        'artist_id' => ['$last' => '$artist_id'],
                    ]
                ],
                [
                    '$project' => [
                        '_id' => '$_id.package_id',
                        'package_id' => '$_id.package_id',
                        'total_orders' => '$total_orders',
                        'total_coins' => '$total_coins',
                        'total_price' => '$total_price',
                        'total_xp' => '$total_xp',
                        'artist_id' => '$artist_id',
                    ]
                ],
                ['$sort' => ['total_price' => -1]],
                ['$limit' => 5]
            ];
            return $collection->aggregate($aggregate);
        });

        $packageArr = [];
        $artistwise_customers = $artistwise_customers->toArray();
        $package_ids = array_column($artistwise_customers, 'package_id');
        $package_result = \App\Models\Package::select('name')->where('status', '=', 'active')->whereIn('_id', $package_ids)->get()->toArray();

        foreach ($artistwise_customers as $customers) {
            $package_id = $customers['package_id'];
            $artist_id = $customers['artist_id'];
            $package_info = head(array_where($package_result, function ($key, $value) use ($package_id) {
                if ($value['_id'] == $package_id) {
                    return $value;
                }
            }));
            $artist_info = head(array_where($artists, function ($key, $val) use ($artist_id) {
                if ($val['_id'] == $artist_id) {
                    return $val;
                }
            }));
            $name = $artist_info['first_name'] . ' ' . $artist_info['last_name'];
            $package_name = $package_info['name'];
            $customers['total_orders'] = (isset($customers['total_orders'])) ? $customers['total_orders'] : 0;
            $customers['total_coins'] = (isset($customers['total_coins'])) ? $customers['total_coins'] : 0;
            $customers['total_price'] = (isset($customers['total_orders'])) ? $customers['total_price'] : 0;
            $customers['total_xp'] = (isset($customers['total_xp'])) ? $customers['total_xp'] : 0;
            $_info = array_merge($customers, $package_info, $artist_info, ['artist_name' => ucwords($name)], ['package_name' => $package_name]);

            array_push($packageArr, $_info);
        }
        return $packageArr;
    }

    public function getContentsWiseOrdersStats($requestData)
    {
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';

        if (!empty($artist_id)) {
            $artists = \App\Models\Cmsuser::where('status', '=', 'active')->where('_id', $artist_id)->get(['first_name', 'last_name', '_id'])->toArray();
            $artist_ids = array($artist_id);
        } else {
            $artist_role_ids = \App\Models\Role::where('slug', 'artist')->lists('_id');
            $artist_role_ids = ($artist_role_ids) ? $artist_role_ids->toArray() : [];
            $artists = \App\Models\Cmsuser::where('status', '=', 'active')->whereIn('roles', $artist_role_ids)->get(['first_name', 'last_name', '_id'])->toArray();
            $artist_ids = array_pluck($artists, '_id');
        }

//        $created_at = mongodb_start_date_millsec((isset($requestData['created_at']) && $requestData['created_at'] != '') ? $requestData['created_at'] : Config::get('app.start_date'));

//        $created_at_end = mongodb_end_date_millsec((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? $requestData['created_at_end'] : date('m/d/Y h:i:s', time()));
        $created_at = mongodb_start_date((isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '');
        $created_at_end = mongodb_end_date((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '');

        $not_genuine_cids = not_genuine_cids();

        $artistwise_customers = \App\Models\Purchase::raw(function ($collection) use ($artist_ids, $created_at, $created_at_end, $not_genuine_cids) {

            $aggregate = [
                [
                    '$match' => [
                        'artist_id' => ['$in' => $artist_ids],
                        'customer_id' => ['$nin' => $not_genuine_cids],
                        'entity' => ['$in' => ['contents']],
                        '$and' => [
                            ["created_at" => ['$gte' => $created_at]],
                            ["created_at" => ['$lte' => $created_at_end]]
                        ],
                    ]
                ],
                [
                    '$group' => [
                        '_id' => ['entity_id' => '$entity_id'],
                        "coins" => ['$sum' => '$coins'],
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
                ['$limit' => 5]
            ];
            return $collection->aggregate($aggregate);
        });

        $contentArr = [];
        $artistwise_customers = $artistwise_customers->toArray();
        $entity_ids = array_column($artistwise_customers, 'entity_id');
        $content_result = \App\Models\Content::where('status', '=', 'active')->whereIn('_id', $entity_ids)->get(['_id', 'name', 'artist_id', 'type', 'photo'])->toArray();


        foreach ($artistwise_customers as $customer) {
            $content_id = $customer['entity_id'];
            $artist_id = $customer['artist_id'];
            $content_info = head(array_where($content_result, function ($key, $value) use ($content_id) {
                if ($value['_id'] == $content_id) {
                    return $value;
                }
            }));
            $artists_info = head(array_where($artists, function ($key, $val) use ($artist_id) {
                if ($val['_id'] == $artist_id) {
                    return $val;
                }
            }));
            $customer['coins'] = (isset($customer['coins'])) ? $customer['coins'] : 0;
            $name = $artists_info['first_name'] . ' ' . $artists_info['last_name'];
            $name = !empty($name) ? $name : '';
            $content_name = @$content_info['name'];
            $content_name = !empty($content_name) ? $content_name : '';

            $content_info = !empty($content_info) ? $content_info : [];
            $_info = array_merge($customer, $content_info, $artists_info, ['artist_name' => ucwords($name)], ['content_name' => $content_name]);
            $_info = !empty($_info) ? $_info : [];

            array_push($contentArr, $_info);
        }


        return $contentArr;
    }

    public function getGiftsWiseOrdersStats($requestData)
    {
        $not_genuine_cids = not_genuine_cids();
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
//        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';

        if (!empty($artist_id)) {
            $artists = \App\Models\Cmsuser::where('status', '=', 'active')->where('_id', $artist_id)->get(['first_name', 'last_name', '_id'])->toArray();
            $artist_ids = array($artist_id);
        } else {
            $artist_role_ids = \App\Models\Role::where('slug', 'artist')->lists('_id');
            $artist_role_ids = ($artist_role_ids) ? $artist_role_ids->toArray() : [];
            $artists = \App\Models\Cmsuser::where('status', '=', 'active')->whereIn('roles', $artist_role_ids)->get(['first_name', 'last_name', '_id'])->toArray();
            $artist_ids = array_pluck($artists, '_id');
        }

//        if (!empty($platform)) {
//            $platform = array($platform);
//        } else {
//            $platform = array_keys(Config::get('app.platforms'));
//        }

        /*
         * http://iknowit.inf.ovh/database/mongodb/aggregate-queries-examples
         * https://differential.com/insights/mongodb-aggregation-pipeline-patterns-part-1/
         */

//        $created_at = mongodb_start_date_millsec((isset($requestData['created_at']) && $requestData['created_at'] != '') ? $requestData['created_at'] : Config::get('app.start_date'));

//        $created_at_end = mongodb_end_date_millsec((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? $requestData['created_at_end'] : date('m/d/Y h:i:s', time()));
        $created_at = mongodb_start_date((isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '');
        $created_at_end = mongodb_end_date((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '');

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
                ['$limit' => 5]
            ];
            return $collection->aggregate($aggregate);
        });

        $contentArr = [];
        $artistwise_customers = $artistwise_customers->toArray();

        $gift_ids = array_column($artistwise_customers, 'entity_id');
        $gift_result = \App\Models\Gift::where('status', '=', 'active')->whereIn('_id', $gift_ids)->get(['_id', 'name', 'type', 'photo'])->toArray();

        foreach ($artistwise_customers as $customer) {
            $content_id = $customer['entity_id'];
            $artist_id = $customer['artist_id'];

            $artists_info = head(array_where($artists, function ($key, $val) use ($artist_id) {
                if ($val['_id'] == $artist_id) {
                    return $val;
                }
            }));
            $artists_info = !empty($artists_info) ? $artists_info : [];
            $gift_info = head(array_where($gift_result, function ($key, $value) use ($content_id) {
                if ($value['_id'] == $content_id) {
                    return $value;
                }
            }));
            $gift_info = !empty($gift_info) ? $gift_info : [];
            $customer['coins'] = (isset($customer['coins'])) ? $customer['coins'] : 0;
            $name = $artists_info['first_name'] . ' ' . $artists_info['last_name'];
            $content_name = @$gift_info['name'];

            $_info = array_merge($customer, $gift_info, $artists_info, ['artist_name' => ucwords($name)], ['content_name' => $content_name]);
            array_push($contentArr, $_info);
        }

        return $contentArr;
    }

    public function getPurchasePackageHistory($request)
    {
        $perpage = ($request['perpage'] == NULL) ? Config::get('app.perpage') : intval($request['perpage']);
        $artist_id = (isset($requestData['artist_id']) && $request['artist_id'] != '') ? $request['artist_id'] : '';
        $customer_id = $request['customer_id'];

//        $data           =   \App\Models\Order::where('customer_id','=', $customer_id)->where('artist_id','=', $artist_id)->orderBy('created_at','desc')->paginate($perpage)->toArray();

        $query = \App\Models\Order::with(array('artist' => function ($query) {
            $query->select('_id', 'first_name', 'last_name', 'cover');
        }))->where('customer_id', '=', $customer_id);

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        $data = $query->orderBy('created_at', 'desc')->paginate($perpage)->toArray();
        $responeData = [];
        $responeData['list'] = (isset($data['data'])) ? $data['data'] : [];
        $responeData['paginate_data']['total'] = (isset($data['total'])) ? $data['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($data['current_page'])) ? $data['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($data['last_page'])) ? $data['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($data['from'])) ? $data['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($data['to'])) ? $data['to'] : 0;

        return $responeData;
    }

    public function getPurchasePackageHistoryLists($request)
    {
        $perpage = 10;
        $artist_id = (isset($request['artist_id']) && $request['artist_id'] != '') ? $request['artist_id'] : '';
        $customer_id = (isset($request['customer_id']) && $request['customer_id'] != '') ? $request['customer_id'] : '';

        $query = \App\Models\Order::with(array('artist' => function ($query) {
            $query->select('_id', 'first_name', 'last_name', 'cover');
        }))
            ->where('customer_id', '=', $customer_id);

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        $data = $query->orderBy('created_at', 'desc')->paginate($perpage)->toArray();

        $responeData = [];
        $responeData['list'] = (isset($data['data'])) ? $data['data'] : [];
        $responeData['paginate_data']['total'] = (isset($data['total'])) ? $data['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($data['current_page'])) ? $data['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($data['last_page'])) ? $data['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($data['from'])) ? $data['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($data['to'])) ? $data['to'] : 0;

        return $responeData;
    }


    public function getPackageListing($requestData)
    {

        $artist_id = [];
        $perpage = ($requestData['perpage'] == NULL) ? \Config::get('app.perpage') : intval($requestData['perpage']);
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $query = $this->model->where('status', 'active');

        if (isset($requestData['artist_id']) && $requestData['artist_id'] != '') {
            $artist_id [] = $requestData['artist_id'];
        }

        if (count($artist_id) > 0) {
            $query->whereIn('artists', $artist_id);
        }

        if ($platform != '') {
            $query->where('platform', $platform);
        }

        $data = $query->paginate($perpage)->toArray();

        //return $data;

        $packages = (isset($data['data'])) ? $data['data'] : [];
        $responeData = [];
        $responeData['list'] = $packages;
        $responeData['paginate_data']['total'] = (isset($data['total'])) ? $data['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($data['current_page'])) ? $data['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($data['last_page'])) ? $data['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($data['from'])) ? $data['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($data['to'])) ? $data['to'] : 0;

        return $responeData;
    }


    public function store($postData)
    {
        $data = $postData;
        $recordset = new $this->model($data);
        $recordset->save();
        return $recordset;
    }


    public function update($postData, $id)
    {
        $data = $postData;
        $recordset = $this->model->findOrFail($id);
        $recordset->update($data);
        return $recordset;
    }

    public function update_order($requestOrderId)
    {
        $orderrecordset = $this->model->findOrFail($requestOrderId);
        $dataOrder = $orderrecordset->toArray();
        $dataOrder = array_set($dataOrder, 'remark', 'fake');
        $orderrecordset->update($dataOrder);

        $customerorderset = \App\Models\Customer::findOrFail($dataOrder['customer_id']);
        $dataCust = $customerorderset->toArray();
        $dataCust = array_set($dataCust, 'status', 'banned');
        $customerorderset->update($dataCust);

        return $orderrecordset;
    }


}