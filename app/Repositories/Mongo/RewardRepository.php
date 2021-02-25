<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\CustomerInterface;
use App\Repositories\Contracts\RewardInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Reward as Reward;
use Config;


class RewardRepository extends AbstractRepository implements RewardInterface
{

    protected $modelClassName = 'App\Models\Reward';

    protected $customerrepObj;

    public function __construct(CustomerInterface $customerrepObj)
    {
        $this->customerrepObj = $customerrepObj;
        parent::__construct();
    }


    public function index($requestData)
    {

        $results = [];
        $artist_id = [];
        $perpage = 10;
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $title = (isset($requestData['title']) && $requestData['title'] != '') ? $requestData['title'] : '';
        $customer_name = (isset($requestData['customer_name']) && $requestData['customer_name'] != '') ? $requestData['customer_name'] : '';
        $user_type = (isset($requestData['user_type']) && $requestData['user_type'] != '') ? $requestData['user_type'] : 'genuine';

        $created_at = ((isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '');
        $created_at_end = ((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '');

        $appends_array = [
            'status' => $status,
            'artist_id' => $artist_id,
            'title' => $title,
            'created_at' => $created_at,
            'created_at_end' => $created_at_end,
            'customer_name' => $customer_name,
            'user_type' => $user_type,
        ];


        $query = \App\Models\Reward::with('artist', 'customer')->orderBy('created_at', 'desc');

        if ($status != '') {
            $query->where('status', $status);
        }

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        if ($title != '') {
            $query->where('title', $title);
        }

        if ($created_at != '') {

            $query->where("created_at",'>',mongodb_start_date($created_at));
        }

        if ($created_at_end != '') {
            $query->where("created_at",'<',mongodb_end_date($created_at_end));
        }

        if ($user_type != 'genuine') {
            $query->NotGenuineCustomers($customer_name);
        } else {
            $query->GenuineCustomers($customer_name);
        }

        $results['rewards'] = $query->paginate($perpage);
        $results['coins'] = $query->sum('coins');
        $results['appends_array'] = $appends_array;

        return $results;
    }


    public function getRewardsForCustomer($requestData, $customer_id)
    {
        $perpage = ($requestData['perpage'] == NULL) ? Config::get('app.perpage') : intval($requestData['perpage']);
        $reward_type = (isset($requestData['reward_type']) && $requestData['reward_type'] != '') ? $requestData['reward_type'] : '';
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $appends_array = array('artist_id' => $artist_id, 'reward_type' => $reward_type);
        $query = \App\Models\Reward::with('artist')->where('customer_id', '=', $customer_id)->orderBy('created_at', 'desc');

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


    public function saveOneTimeRewardForCustomer($postdata, $reward_title)
    {

        $rewards = array_keys(Config::get('app.reward_title'));
        $recordset = [];

        if (in_array($reward_title, $rewards)) {
            $customer_id = $postdata['customer_id'];
            $artist_id = $postdata['artist_id'];
            $coins = intval($postdata['coins']);
            $reward_type = strtolower(trim($postdata['reward_type']));
//            $reward         =   \App\Models\Reward::where('artist_id',$artist_id)->where('customer_id',$customer_id)->where('title',$reward_title)->first();
            $reward = \App\Models\Reward::where('customer_id', $customer_id)->where('title', $reward_title)->first();
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


    public function saveRewardForCustomer($postdata)
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
    
    public function store($postData){
        $rewardset = new $this->model($postData);
        $rewardset->save();
    }
}

