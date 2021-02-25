<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\FanInterface;
use App\Repositories\Contracts\RepositoryInterface;
use App\Repositories\Contracts\CustomerDeviceInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Fan as Fan;
use Config;
use App\Repositories\Contracts\CustomerInterface;
use App\Services\Notifications\CustomerNotification;

class FanRepository extends AbstractRepository implements FanInterface
{

    protected $modelClassName = 'App\Models\Fan';

    protected $customerrepObj;
    protected $devicerepObj;
    protected $customernotification;

    public function __construct(CustomerInterface $customerrepObj, CustomerDeviceInterface $devicerepObj, CustomerNotification $customerNotification)
    {
        $this->customerrepObj = $customerrepObj;
        $this->devicerepObj = $devicerepObj;
        $this->customernotification = $customerNotification;

        parent::__construct();
    }

    public function index($requestData, $perpage = NULL)
    {
        $results = [];
        $perpage = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $email = (isset($requestData['name']) && $requestData['name'] != '') ? $requestData['name'] : '';
        $fans = (isset($requestData['type']) && $requestData['type'] != '') ? $requestData['type'] : '';

        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';
        $query = \App\Models\Fan::with('artist', 'customer', 'reward')->orderBy('created_at', 'desc');
        $appends_array = array('artist_id' => $artist_id, 'email' => $email, 'fans' => $fans, 'status' => $status);

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }
        if ($email != '') {
            $query->where('email', 'LIKE', '%' . $email . '%');
        }
        if ($fans != '') {
            $query->where('fans', $fans);
        }
        if ($status != '') {
            $query->where('status', $status);
        }


        $results['fans'] = $query->paginate($perpage);
        $results['appends_array'] = $appends_array;
        return $results;
    }

    public function store($requestData)
    {
        $fanset = new $this->model($requestData);
        $fanset->save();
        return $fanset;
    }

    public function update($requestData, $id)
    {

        $record_data = array_except($requestData, ['status']);

        $fan_data = [];
        $error_messages = [];
        if (!empty($record_data['reward_type'])) {
            if ($record_data['coins'] > 0) {

                $customer_id      =     trim($record_data['customer_id']);
                $coins_xp_val     =     intval($record_data['coins']);

                if ($record_data['reward_type'] == 'coins') {
                    array_set($record_data, 'coins', !empty($coins_xp_val) ? $coins_xp_val : 1);
                    $this->customerrepObj->coinsDeposit($customer_id, $coins_xp_val);
                } else {
                    array_set($record_data, 'xp', !empty($coins_xp_val) ? $coins_xp_val : 1);
                    $record_data = array_except($record_data, 'coins');
                    $this->customerrepObj->xpDeposit($customer_id, $record_data['artist_id'], $coins_xp_val);
                }

                $customerObj                    =   \App\Models\Customer::where('_id', $customer_id)->first();
                $coins_before_txn               =   (isset($customerObj->coins)) ? $customerObj->coins : 0;
                $coins_after_txn                =   (isset($customerObj->coins)) ? $customerObj->coins + $coins_xp_val : $coins_xp_val;

                $artist = \App\Models\Cmsuser::select('first_name', 'last_name')->where('_id', $record_data['artist_id'])->first();
                if (isset($artist) && isset($artist['first_name']) && isset($artist['last_name'])) {
                    $artist_name = $artist['first_name'] . ' ' . $artist['last_name'];
                }
                if ($record_data['reward_type'] == 'xp') {
                    $value = $coins_xp_val;
                } else {
                    $value = $coins_xp_val;
                }

                array_set($record_data, 'title', $record_data['title']);
                array_set($record_data, 'description', 'Congratulations : You have been rewarded ' . $value . ' ' . $record_data['reward_type'] . ' by ' . $artist_name . ', cheers.');
                array_set($record_data, 'reward_title', 'install');
                array_set($record_data, 'coins_before_txn', intval($coins_before_txn));
                array_set($record_data, 'coins_after_txn', intval($coins_after_txn));
                array_set($record_data, 'passbook_applied', true);
                $rewardset = new \App\Models\Reward($record_data);
                $rewardset->save();


                array_set($fan_data, 'given_reward', 1);
                array_set($fan_data, 'reward_id', $rewardset->_id);

            } else {
                $error_messages[] = 'Coins should not be less than 1';
            }
        }
        array_set($fan_data, 'status', $requestData['status']);
        $fanset = $this->model->findOrFail($id);
        $fanset->update($fan_data);
        return $fanset;
    }

    public function sendNotification($requestData, $id)
    {
        $results = [];

        $results['artist_id'] = $requestData['artist_id'];
        $artist_name = \App\Models\Cmsuser::select('first_name', 'last_name')->where('_id', $requestData['artist_id'])->first();
        if (isset($artist_name) && isset($artist_name['first_name']) && isset($artist_name['last_name'])) {
            $results['artist_name'] = $artist_name['first_name'] . ' ' . $artist_name['last_name'];
        }

        $customerdevices = \App\Models\Customerdeviceinfo::with('artist')
            ->where('customer_id', $requestData['customer_id'])
            ->where('artist_id', $requestData['artist_id'])
            ->first();

        $results['fcm_device_token'] = !empty($customerdevices) ? $customerdevices['fcm_device_token'] : '';

        $checkReward = \App\Models\Fan::select('reward_id')->where('_id', $id)->first();

        if (!empty($checkReward) && !empty($checkReward['reward_id'])) {

            $getRewardInfo = \App\Models\Reward::select('reward_type', 'coins')->where('_id', $checkReward['reward_id'])->first();

            $results['coins'] = $getRewardInfo['coins'];
            $results['reward_type'] = $getRewardInfo['reward_type'];

        } else {
            $results['coins'] = $requestData['coins'];
            $results['reward_type'] = $requestData['reward_type'];
        }
        
//        $results['topic_id'] = str_replace(' ', '', $results['artist_name']);
        $results['title'] = (!empty($results['title']) ? $results['title'] : '');
        $results['priority'] = (!empty($results['priority'])) ? trim($results['priority']) : "high";
        $results['body'] = 'Congratulations : You have been rewarded ' . $results['coins'] . ' ' . $results['reward_type'] . ' by ' . $results['artist_name'] . ' cheers.';
        
        $response = $this->customernotification->sendNotificationToCustomer($results);

        return $response;
    }
}