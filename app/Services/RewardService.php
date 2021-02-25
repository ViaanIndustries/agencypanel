<?php

namespace App\Services;

use Input;
use Redirect;
use Config;
use Session;
use App\Repositories\Contracts\RewardInterface;
use App\Models\Reward as Reward;
use App\Services\Jwtauth;
use App\Repositories\Contracts\CustomerInterface;

class RewardService
{
    protected $jwtauth;
    protected $rewardRepObj;
    protected $customerrepObj;

    public function __construct(
        Jwtauth $jwtauth,
        Reward $reward,
        RewardInterface $rewardRepObj,
        CustomerInterface $customerrepObj
    )
    {
        $this->jwtauth = $jwtauth;
        $this->reward = $reward;
        $this->rewardRepObj = $rewardRepObj;
        $this->customerrepObj = $customerrepObj;
    }


    public function index($request)
    {
        $requestData = $request->all();

        $results = $this->rewardRepObj->index($requestData);
        return $results;
    }


    public function getRewardsForCustomer($request, $id)
    {
        $error_messages = $results = [];

        if (empty($error_messages)) {

            $results = $this->rewardRepObj->getRewardsForCustomer($request, $id);
        }


        return $results;
    }


    public function watchAdsVideo($request)
    {
        $error_messages = $results = [];

        if (empty($error_messages)) {

            $artist_id = request()->header('artistid');
            $customer_id = $this->jwtauth->customerIdFromToken();
            $artist = \App\Models\Cmsuser::where('_id', '=', $artist_id)->first();

            $saveData = [
                'customer_id' => $customer_id,
                'artist_id' => $artist_id,
                'reward_title' => 'ads',
                'title' => 'reward_on_watch_ads_video',
                'description' => "You've won 2 coins for watching video ads on " . $artist->fullname . " app",
                'reward_type' => 'coins',
                'coins' => 2
            ];

            $results = $this->rewardRepObj->saveRewardForCustomer($saveData);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function store($request)
    {
        $error_messages = $results = [];
        $requestData = array_except($request->all(), '_token');

        $verifyEmail = \App\Models\Customer::where('email', $requestData['email'])->select('_id')->first();
        $artist_name = \App\Models\Cmsuser::where('_id', $requestData['artist_id'])->select('first_name', 'last_name')->first();

        if (isset($artist_name['first_name']) || isset($artist_name['last_name'])) {
            $name = $artist_name['first_name'] . ' ' . $artist_name['last_name'];
        }

        if (!empty($verifyEmail)) {

            $customerId = $verifyEmail['_id'];

            $verifyEmailWithArtist = \App\Models\Customerdeviceinfo::where('artist_id', $requestData['artist_id'])->where('customer_id', $customerId)->select('_id')->first();

            if (!empty($verifyEmailWithArtist)) {

                array_set($requestData, 'customer_id', $customerId);
                array_set($requestData, 'reward_type', strtolower(trim($requestData['reward_type'])));
                array_set($requestData, 'reward_title', 'installed');

                if ($requestData['title'] == 'reward_on_first_login') {
                    array_set($requestData, 'description', 'get on first login for ' . $name);
                } elseif ($requestData['title'] == 'reward_on_fan_of_the_month') {
                    array_set($requestData, 'description', 'Congratulations : You have been rewarded ' . $requestData['coins'] . ' ' . $requestData['reward_type'] . ' by ' . $name . ' cheers.');
                } elseif ($requestData['title'] == 'reward_on_watch_ads_video') {
                    array_set($requestData, 'description', '');
                }

                $requestData = array_except($requestData, 'email');

                if ($requestData['coins'] > 0) {

                    if ($requestData['reward_type'] == 'coins') {
                        array_set($requestData, 'coins', !empty($requestData['coins']) ? $requestData['coins'] : 1);
                        $this->customerrepObj->coinsDeposit($requestData['customer_id'], $requestData['coins']);
                    } else {
                        array_set($requestData, 'xp', !empty($requestData['coins']) ? $requestData['coins'] : 1);
                        $requestData = array_except($requestData, 'coins');
                        $this->customerrepObj->xpDeposit($requestData['customer_id'], $requestData['artist_id'], $requestData['xp']);
                    }

                    $results = $this->rewardRepObj->store($requestData);
                } else {
                    $error_messages[] = 'Coins should not be less than 1';
                }

            } else {
                $error_messages[] = 'Email is not registered with this Artist.Please register first.';
            }

        } else {
            $error_messages[] = 'Email id is not exist,Kindly enter valid email address;';
        }

        return ['error_messages' => $error_messages, 'results' => $results];

    }


}