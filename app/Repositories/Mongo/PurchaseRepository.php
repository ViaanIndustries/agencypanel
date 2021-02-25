<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\CustomerInterface;
use App\Repositories\Contracts\PurchaseInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Purchase as Purchase;
use Config;


class PurchaseRepository extends AbstractRepository implements PurchaseInterface
{

    protected $modelClassName = 'App\Models\Purchase';

    protected $customerrepObj;

    public function __construct(CustomerInterface $customerrepObj)
    {
        $this->customerrepObj = $customerrepObj;
        parent::__construct();
    }


    public function getPurchaseQuery($requestData)
    {
        $entity         = (isset($requestData['entity']) && $requestData['entity'] != '') ? $requestData['entity'] : '';
        $entity_id      = (isset($requestData['entity_id']) && $requestData['entity_id'] != '') ? $requestData['entity_id'] : '';
        $artist_id      = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $title          = (isset($requestData['title']) && $requestData['title'] != '') ? $requestData['title'] : '';
        $customer_name  = (isset($requestData['customer_name']) && $requestData['customer_name'] != '') ? $requestData['customer_name'] : '';
        $user_type      = (isset($requestData['user_type']) && $requestData['user_type'] != '') ? $requestData['user_type'] : 'genuine';

//        $created_at = mongodb_start_date_millsec((isset($requestData['created_at']) && $requestData['created_at'] != '') ? $requestData['created_at'] : '');
//        $created_at_end = mongodb_end_date_millsec((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? $requestData['created_at_end'] : '');

        $created_at = ((isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '');
        $created_at_end = ((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '');

        $query = \App\Models\Purchase::with('artist', 'customer')->orderBy('created_at', 'desc');

        if ($entity != '') {
            $query->where('entity', $entity);
        }
        if ($entity_id != '') {
            $query->where('entity_id', $entity_id);
        }

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        if ($title != '') {
            $query->where('title', $title);
        }

        if ($created_at != '') {
            $query->where("created_at", '>', mongodb_start_date($created_at));
        }

        if ($created_at_end != '') {
            $query->where("created_at", '<', mongodb_end_date($created_at_end));
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
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $entity = (isset($requestData['entity']) && $requestData['entity'] != '') ? $requestData['entity'] : '';
        $entity_id = (isset($requestData['entity_id']) && $requestData['entity_id'] != '') ? $requestData['entity_id'] : '';
        $created_at = (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end = (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';
        $customer_name = (isset($requestData['customer_name']) && $requestData['customer_name'] != '') ? $requestData['customer_name'] : '';
        $user_type = (isset($requestData['user_type']) && $requestData['user_type'] != '') ? $requestData['user_type'] : 'genuine';
        $appends_array = [
            'entity' => $entity,
            'entity_id' => $entity_id,
            'artist_id' => $artist_id,
            'created_at' => $created_at,
            'created_at_end' => $created_at_end,
            'customer_name' => $customer_name,
            'user_type' => $user_type,
        ];


        $purchaseslists = $this->getPurchaseQuery($requestData)->paginate($perpage);

        $purchaseslists->getCollection()->transform(function ($purchaseList, $key) use ($artist_id) {

            $purchaseData = $purchaseList;

            $entity_id = $purchaseList['entity_id'];

            if ($purchaseList['entity'] == 'contents') {
                $entityObj = \App\Models\Content::where('_id', $entity_id)->first();
                $purchaseData['content'] = ($entityObj) ? $entityObj : [];
            }

            if ($purchaseList['entity'] == 'gifts' || $purchaseList['entity'] == 'stickers') {
                $entityObj = \App\Models\Gift::where('_id', $entity_id)->first();
                $purchaseData['gift'] = ($entityObj) ? $entityObj : [];
            }

//            if ($purchaseList['entity'] == 'stickers') {
//                $entityObj = \App\Models\Gift::where('_id', $entity_id)->first();
//                $purchaseData['sticker'] = ($entityObj) ? $entityObj : [];
//            }

            return $purchaseData;
        });

        $results['purchases'] = $purchaseslists;
        $results['coins'] = $this->getPurchaseQuery($requestData)->sum('coins');
        $results['appends_array'] = $appends_array;

        return $results;
    }


    public function getPurchasesForCustomer($requestData, $customer_id)
    {
        $perpage = ($requestData['perpage'] == NULL) ? Config::get('app.perpage') : intval($requestData['perpage']);
        $reward_type = (isset($requestData['reward_type']) && $requestData['reward_type'] != '') ? $requestData['reward_type'] : '';
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $appends_array = array('artist_id' => $artist_id, 'reward_type' => $reward_type);
        $query = \App\Models\Purchase::with('artist', 'customer')->where('customer_id', '=', $customer_id)->orderBy('created_at', 'desc');


        if ($artist_id != '') {
            $query->where('artist_id', '=', $artist_id);
        }
        if ($reward_type != '') {
            $query->where('title', '=', $reward_type);
        }

        $query_list = $query;

        $results['rewards'] = $query_list->paginate($perpage);
        $results['appends_array'] = $appends_array;

        return $results;
    }


    public function saveOneTimePurchaseForCustomer($postdata, $reward_title)
    {

        $rewards = array_keys(Config::get('app.reward_title'));
        $recordset = [];

        if (in_array($reward_title, $rewards)) {
            $customer_id = $postdata['customer_id'];
            $artist_id = $postdata['artist_id'];
            $coins = intval($postdata['coins']);
            $reward_type = strtolower(trim($postdata['reward_type']));
//            $reward         =   \App\Models\Purchase::where('artist_id',$artist_id)->where('customer_id',$customer_id)->where('title',$reward_title)->first();
            $reward = \App\Models\Purchase::where('customer_id', $customer_id)->where('title', $reward_title)->first();
            if (!$reward) {
                array_set($postdata, 'coins', $coins);
                $recordset = new $this->model($postdata);
                $recordset->save();
                if ($recordset) {
                    if ($reward_type == 'coins') {
                        $this->customerrepObj->coinsDeposit($customer_id, $coins);
                    }
                }
            }//!reward
        }
        return $recordset;
    }


    public function savePurchaseForCustomer($postdata)
    {
        $rewards = array_keys(Config::get('app.reward_title'));
        $recordset = [];
        $customer_id = $postdata['customer_id'];
        $artist_id = $postdata['artist_id'];
        $coins = intval($postdata['coins']);
        $reward_type = strtolower(trim($postdata['reward_type']));
        array_set($postdata, 'coins', $coins);

        $recordset = new $this->model($postdata);
        $recordset->save();
        if ($recordset) {
            if ($reward_type == 'coins') {
                $this->customerrepObj->coinsDeposit($customer_id, $coins);
            }
        }

        return $recordset;
    }


    public function contentEarnings($requestData)
    {

        $error_messages     =   [];
        $artist_id          =   (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $entity             =   (isset($requestData['entity']) && $requestData['entity'] != '') ? $requestData['entity'] : '';
        $entity_id          =   (isset($requestData['entity_id']) && $requestData['entity_id'] != '') ? $requestData['entity_id'] : '';
        $created_at         =   (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end     =   (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';
        $customer_name      =   (isset($requestData['customer_name']) && $requestData['customer_name'] != '') ? $requestData['customer_name'] : '';
        $user_type          =   (isset($requestData['user_type']) && $requestData['user_type'] != '') ? $requestData['user_type'] : 'genuine';

        $total_purchase_count       =   0;
        $total_coins_spend          =   0;

        if($entity_id != ''){
            $one_coins_value            =   (Config::get('app.one_coins_value')) ? Config::get('app.one_coins_value') : 1.6;
            $total_purchase_count       =   $this->getPurchaseQuery($requestData)->count();
            $total_coins_spend          =   $this->getPurchaseQuery($requestData)->sum('coins');
            if($total_coins_spend > 0){
                $total_coins_spend = round($total_coins_spend * $one_coins_value);
//                $total_coins_spend = round($total_coins_spend/$one_coins_value);
            }
        }

        $results     =   [
            'total_purchase_count' => $total_purchase_count,
            'total_coins_spend' => $total_coins_spend
        ];

        return $results;
    }





}

