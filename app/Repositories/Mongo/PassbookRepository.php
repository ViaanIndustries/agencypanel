<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\PassbookInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use Config;


class PassbookRepository extends AbstractRepository implements PassbookInterface
{

    protected $modelClassName = "App\Models\Passbook";




    public function getPassbookQuery($requestData)
    {

        $artists          =   [];
        if(!empty($requestData['artist_id'])){
            $artists  =  (is_array($requestData['artist_id'])) ? $requestData['artist_id'] : [trim($requestData['artist_id'])];
        }
        $entity             =   (!empty($requestData['entity']) && count($requestData['entity']) > 0) ? $requestData['entity'] : [];
        $entity_id          =   (isset($requestData['entity_id']) && $requestData['entity_id'] != '') ? $requestData['entity_id'] : '';
        $platform           =   (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $customer_id        =   (isset($requestData['customer_id']) && $requestData['customer_id'] != '') ? trim($requestData['customer_id']) : '';
        $customer_name      =   (isset($requestData['customer_name']) && $requestData['customer_name'] != '') ? $requestData['customer_name'] : '';
        $user_type          =   (isset($requestData['user_type']) && $requestData['user_type'] != '') ? $requestData['user_type'] : '';
        $txn_id             =   (isset($requestData['txn_id']) && $requestData['txn_id'] != '') ? $requestData['txn_id'] : '';
        $txn_type           =   (isset($requestData['txn_type']) && $requestData['txn_type'] != '') ? strtolower(trim($requestData['txn_type'])) : '';
        $vendor_txn_id      =   (isset($requestData['vendor_txn_id']) && $requestData['vendor_txn_id'] != '') ? $requestData['vendor_txn_id'] : '';
        $vendor             =   (isset($requestData['vendor']) && $requestData['vendor'] != '') ? $requestData['vendor'] : '';
        $reward_event       =   (isset($requestData['reward_event']) && $requestData['reward_event'] != '') ? $requestData['reward_event'] : '';
        $status             =   (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : 'success';
        $created_at         =   (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end     =   (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';

        $txn_types          =   Config::get('app.passbook.txn_types');

        $query              =   \App\Models\Passbook::with(array('customer'=>function($query){$query->select('_id','first_name','first_name','email','mobile');}))
            ->with(array('artist'=>function($query){$query->select('_id','first_name','last_name','email');}))
            ->with(array('rechargeby'=>function($query){$query->select('_id','first_name','last_name','email');}))
            ->with(array('package'=>function($query){$query->select('_id','name','sku','price');}))
            ->with(array('content'=>function($query){$query->select('_id', 'name', 'slug', 'caption', 'type', 'photo','video', 'audio', 'source', 'commercial_type', 'coins', 'level');}))
            ->with(array('gift'=>function($query){$query->select('_id','name','slug','photo','coins');}))
            ->with(array('live'=>function($query){$query->select('_id','name','slug','photo','coins');}))
            ->orderBy('created_at', 'desc');

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
        }

        if($txn_type != '' && in_array($txn_type, $txn_types)) {
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
            $query->where('created_at', '>', mongodb_start_date($created_at));
        }

        if ($created_at_end != '') {
            $query->where('created_at', '<', mongodb_end_date($created_at_end));
        }


        return $query;

    }


    public function searchListing($requestData)
    {
        $results = [];
        $perpage            =   (isset($requestData['perpage']) && $requestData['perpage'] != '') ? intval($requestData['perpage']) : 10;
        $entity_id          =   (isset($requestData['entity_id']) && $requestData['entity_id'] != '') ? $requestData['entity_id'] : '';
        $artist_id          =   (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $platform           =   (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $customer_name      =   (isset($requestData['customer_name']) && $requestData['customer_name'] != '') ? $requestData['customer_name'] : '';
        $user_type          =   (isset($requestData['user_type']) && $requestData['user_type'] != '') ? $requestData['user_type'] : '';
        $txn_id             =   (isset($requestData['txn_id']) && $requestData['txn_id'] != '') ? $requestData['txn_id'] : '';
        $vendor_txn_id      =   (isset($requestData['vendor_txn_id']) && $requestData['vendor_txn_id'] != '') ? $requestData['vendor_txn_id'] : '';
        $vendor             =   (isset($requestData['vendor']) && $requestData['vendor'] != '') ? $requestData['vendor'] : '';
        $reward_event       =   (isset($requestData['reward_event']) && $requestData['reward_event'] != '') ? $requestData['reward_event'] : '';
        $status             =   (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : 'success';
        $created_at         =   (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end     =   (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';

//        $perpage = 1;
        $appends_array = [
            'entity_id' => $entity_id,
            'artist_id' => $artist_id,
            'platform' => $platform,
            'customer_name' => $customer_name,
            'user_type' => $user_type,
            'txn_id' => $txn_id,
            'vendor_txn_id' => $vendor_txn_id,
            'vendor' => $vendor,
            'status' => $status,
            'reward_event' => $reward_event,
            'created_at' => $created_at,
            'created_at_end' => $created_at_end,

        ];

//        \DB::connection('arms_transactions')->enableQueryLog();
//        $queries = \DB::connection('arms_transactions')->getQueryLog();
//        dd($queries);exit;

        $items                      =   $this->getPassbookQuery($requestData)->paginate($perpage);
        $results['items']           =   $items;
        $results['coins']           =   $this->getPassbookQuery($requestData)->sum('total_coins');
        $results['amount']          =   $this->getPassbookQuery($requestData)->sum('amount');
        $results['count']           =   $items->total();
        $results['appends_array']   =   $appends_array;
//        dd($results);exit;

        return $results;
    }



    public function customerPassbook($requestData)
    {

        $perpage            =   (isset($requestData['perpage']) && $requestData['perpage'] != '') ? intval($requestData['perpage']) : 10;
        $results            =   $this->getPassbookQuery($requestData)->paginate($perpage)->toArray();


        $passbookArr        =   [];
        $passbooks          =   (isset($results['data'])) ? $results['data'] : [];

        $wallet_name        = 'Arms Wallet';
        $received_coin_from = 'Coins Received From BOLLYFAME Pvt Ltd';

        $product = strtolower(trim(env('PRODUCT')));
        if($product == 'hsw') {
            $wallet_name        = 'Wallet';
            $received_coin_from = 'Coins Received From BollyFame Digital Entertainment';
        }

        if(count($passbooks) > 0){

            $type = $name = $caption = $thumb = $video = $audio = $description = $remark = "";

            foreach ($passbooks as $passbook){

                $common_fields  =  ['_id','artist','entity', 'entity_id', 'platform', 'platform_version','xp','coins','total_coins','quantity','amount','total_coins','coins_before_txn','coins_after_txn','txn_type', 'status', 'created_at','updated_at','passbook_applied'];
                $passbookData   =  array_only($passbook, $common_fields);

                $entity         =  $passbook['entity'];
                $artist_name    =  ucfirst(@$passbook['artist']['first_name']) . ' ' . ucfirst(@$passbook['artist']['last_name']);
                switch ($entity) {

                    case "packages":
                        $type           =   'photo';
                        $name           =   (!empty($passbook['package']['name'])) ?  trim($passbook['package']['name']) : "";
                        $caption        =   '';
                        $thumb          =   '';
                        $description    =   'Added To ' . $wallet_name . ' On ' . $artist_name;
                        break;

                    case "contents":
                        //$type         =   (!empty($passbook['content']) && !empty($passbook['content']['type'])) ? $passbook['content']['type'] : 'photo';
                        $type         =   'video';
                        $name         =   (!empty($passbook['content']['name'])) ?  trim($passbook['content']['name']) : "";
                        $caption      =   (!empty($passbook['content']['caption'])) ?  trim($passbook['content']['caption']) : "";

                        if(!empty($passbook['content']['video']) && $type == 'video'){

                            $thumb          =   (!empty($passbook['content']['video']['thumb'])) ?  trim($passbook['content']['video']['thumb']) : "";
                            $video          =   (!empty($passbook['content']['video']['url'])) ?  trim($passbook['content']['video']['url']) : "";

                        }elseif(!empty($passbook['content']['audio']) && $type == 'audio'){

                            $thumb          =   (!empty($passbook['content']['audio']['thumb'])) ?  trim($passbook['content']['audio']['thumb']) : "";
                            $audio          =   (!empty($passbook['content']['audio']['url'])) ?  trim($passbook['content']['audio']['url']) : "";

                        } elseif(!empty($passbook['content']['photo']) && $type == 'photo'){

                            $thumb          =   (!empty($passbook['content']['photo']['thumb'])) ?  trim($passbook['content']['photo']['thumb']) : "";

                        }elseif(!empty($passbook['content']['photo'])){

                            $thumb          =   (!empty($passbook['content']['photo']['thumb'])) ?  trim($passbook['content']['photo']['thumb']) : "";
                        }

                        $description  = 'Paid For Photo/Video On ' . $artist_name;

                        break;

                    case "gifts":
                        $type           =   'photo';
                        $name           =   (!empty($passbook['gift']['name'])) ?  trim($passbook['gift']['name']) : "";
                        $caption        =   (!empty($passbook['gift']['caption'])) ?  trim($passbook['gift']['caption']) : "";
                        $thumb          =   (!empty($passbook['gift']['photo']['thumb'])) ?  trim($passbook['gift']['photo']['thumb']) : "";
                        $description    =   'Paid For Gift On ' . $artist_name;
                        break;

                    case "stickers":
                        $type           =   'photo';
                        $name           =   ucfirst(mb_substr(@$passbook['artist']['first_name'], 0, 1, 'utf-8')).ucfirst(mb_substr(@$passbook['artist']['last_name'], 0, 1, 'utf-8'))."- Premium Sticker -".$passbook['coins'];
                        $caption        =   '';
                        $thumb          =   '';
                        $description    =   'Paid For Sticker On '. $artist_name;
                        break;

                    case "rewards":
                        $type           = 'photo';
                        $name           = '';
                        $caption        = '';
                        $thumb          = '';
                        $description    = '';

                        if(!empty($passbook['txn_meta_info']) && !empty($passbook['txn_meta_info']['reward_name'])) {
                            $name = $passbook['txn_meta_info']['reward_name'];
                        }
                        else {
                            $name = (!empty($passbook['txn_meta_info']) && !empty($passbook['txn_meta_info']['reward_event'])) ? ucfirst($passbook['txn_meta_info']['reward_event']) ." on $artist_name" : '';
                        }

                        if(!empty($passbook['txn_meta_info']) && !empty($passbook['txn_meta_info']['reward_description'])) {
                            $description = $passbook['txn_meta_info']['reward_description'];
                        }
                        else {
                            if(!empty($passbook['txn_meta_info']) && !empty($passbook['txn_meta_info']['reward_type'])) {
                                $reward_type = ucfirst($passbook['txn_meta_info']['reward_type']);
                                $description .=  trim($reward_type)." Received for ";
                            }

                            if(!empty($passbook['txn_meta_info']) && !empty($passbook['txn_meta_info']['reward_event'])) {
                                $reward_event = ucfirst($passbook['txn_meta_info']['reward_event']);
                                $description .=  trim($reward_event);
                            }

                            $description  .= " on $artist_name";
                        }
                        break;

                    case "rechargecoins":
                        $type           =   'photo';
                        $name           =   '';
                        $caption        =   '';
                        $thumb          =   '';
                        $description    =   $received_coin_from . ' On ' . $artist_name;

                        break;

                    case "lives":
                        $type           =   'photo';
                        $name           =   (!empty($passbook['live']['name'])) ?  trim($passbook['live']['name']) : "";
                        $caption        =   (!empty($passbook['live']['caption'])) ?  trim($passbook['live']['caption']) : "";
                        $thumb          =   (!empty($passbook['live']['photo']['thumb'])) ?  trim($passbook['live']['photo']['thumb']) : "";
                        $description    =   "Paid For Live On ".$artist_name;
                        break;

                }


                $meta_info   =  ['type' => $type, 'name'   => $name,  'caption'   => $caption, 'thumb' => $thumb, 'video' => $video, 'audio' => $audio, 'description' => $description];

                if(!empty($passbook['txn_meta_info']) && !empty($passbook['txn_meta_info']['vendor'])){
                    $meta_info['vendor']   = trim($passbook['txn_meta_info']['vendor']) ;
                }

                if(!empty($passbook['txn_meta_info']) && !empty($passbook['txn_meta_info']['vendor_txn_id'])){
                    $meta_info['vendor_txn_id']   = trim($passbook['txn_meta_info']['vendor_txn_id']) ;
                }

                if(!empty($passbook['txn_meta_info']) && !empty($passbook['txn_meta_info']['currency_code'])){
                    $meta_info['currency_code']   = trim($passbook['txn_meta_info']['currency_code']) ;
                }

                if(!empty($passbook['txn_meta_info']) && !empty($passbook['txn_meta_info']['transaction_price'])){
                    $meta_info['transaction_price']   = trim($passbook['txn_meta_info']['transaction_price']) ;
                }

                $passbookData['meta_info'] = $meta_info;

                array_push($passbookArr, $passbookData);
            }
        }

        $total              =   (isset($results['total'])) ? $results['total'] : 0;
        $per_page           =   (isset($results['per_page'])) ? $results['per_page'] : 0;
        $current_page       =   (isset($results['current_page'])) ? $results['current_page'] : 0;
        $last_page          =   (isset($results['last_page'])) ? $results['last_page'] : 0;
        $from               =   (isset($results['from'])) ? $results['from'] : 0;
        $to                 =   (isset($results['to'])) ? $results['to'] : 0;

        $responeData = [
            'list' => array_values($passbookArr),
            'paginate_data' => [
                'total' => $total,
                'per_page' => $per_page,
                'current_page' => $current_page,
                'last_page' => $last_page,
                'last_page' => $last_page,
                'from' => $from,
                'to' => $to
            ]

        ];

        return $responeData;
    }

public function searchListingVideoContent($requestData)
    {
        $results = [];
        $perpage            =   (isset($requestData['perpage']) && $requestData['perpage'] != '') ? intval($requestData['perpage']) : 10;
        $entity_id          =   (isset($requestData['entity_id']) && $requestData['entity_id'] != '') ? $requestData['entity_id'] : '';
        $artist_id          =   (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $platform           =   (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $customer_name      =   (isset($requestData['customer_name']) && $requestData['customer_name'] != '') ? $requestData['customer_name'] : '';
        $user_type          =   (isset($requestData['user_type']) && $requestData['user_type'] != '') ? $requestData['user_type'] : '';
        $txn_id             =   (isset($requestData['txn_id']) && $requestData['txn_id'] != '') ? $requestData['txn_id'] : '';
        $vendor_txn_id      =   (isset($requestData['vendor_txn_id']) && $requestData['vendor_txn_id'] != '') ? $requestData['vendor_txn_id'] : '';
        $vendor             =   (isset($requestData['vendor']) && $requestData['vendor'] != '') ? $requestData['vendor'] : '';
        $reward_event       =   (isset($requestData['reward_event']) && $requestData['reward_event'] != '') ? $requestData['reward_event'] : '';
        $status             =   (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : 'success';
        $created_at         =   (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end     =   (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';

        $perpage = 1;
        $appends_array = [
            'entity_id' => $entity_id,
            'artist_id' => $artist_id,
            'platform' => $platform,
            'customer_name' => $customer_name,
            'user_type' => $user_type,
            'txn_id' => $txn_id,
            'vendor_txn_id' => $vendor_txn_id,
            'vendor' => $vendor,
            'status' => $status,
            'reward_event' => $reward_event,
            'created_at' => $created_at,
            'created_at_end' => $created_at_end,

        ];

        $items                      =   $this->getPassbookQuery($requestData)->count();        //->paginate($perpage);e
        $results['items']           =   $items;
        $results['coins']           =   $this->getPassbookQuery($requestData)->sum('total_coins');
        return $results;
    }


}
