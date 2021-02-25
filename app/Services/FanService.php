<?php

namespace App\Services;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use App\Services\Gcp;
use Session;

use App\Repositories\Contracts\FanInterface;
use App\Models\Fan as Fan;
use App\Services\CachingService;
use App\Services\Cache\AwsElasticCacheRedis;

class FanService
{
    protected $repObj;
    protected $fan;
    protected $caching;
    protected $awsElasticCacheRedis;

    public function __construct(Fan $fan, FanInterface $repObj, Gcp $gcp, CachingService $caching, AwsElasticCacheRedis $awsElasticCacheRedis)
    {
        $this->fan = $fan;
        $this->repObj = $repObj;
        $this->gcp = $gcp;
        $this->caching = $caching;
        $this->awsElasticCacheRedis = $awsElasticCacheRedis;
    }

    public function index(Request $request)
    {
        $error_messages = $results = [];
        $requestData = $request->all();
        $results = $this->repObj->index($requestData);

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function store(Request $request)
    {
        $error_messages = $results = [];
        $requestData = array_except($request->all(), '_token');

        $verifyEmail = \App\Models\Customer::where('email', $requestData['email'])->select('_id')->first();

        if (!empty($verifyEmail)) {

            $customerId = $verifyEmail['_id'];
            $verifyEmailWithArtist = \App\Models\Customer::whereIn('artists', Array($requestData['artist_id']))->where('_id', $customerId)->select('_id')->first();

            if (!empty($verifyEmailWithArtist)) {

                $emailExisting = \App\Models\Fan::where('artist_id', $requestData['artist_id'])->where('customer_id', $customerId)->select('_id')->first();

                if (empty($emailExisting)) {
                    array_set($requestData, 'customer_id', $customerId);
                    array_set($requestData, 'status', 'inactive');
                    array_set($requestData, 'fans', 'month');
                    $requestData = array_except($requestData, 'email');
                    $results = $this->repObj->store($requestData);

                    $artist_id      = $requestData['artist_id'];
                    $purge_result   = $this->awsElasticCacheRedis->purgeArtistLeaderBoardsCache(['artist_id' => $artist_id]);

                } else {
                    $error_messages[] = 'Already Exist';
                }

            } else {
                $error_messages[] = 'Email is not registered with this Artist.Please register first.';
            }

        } else {
            $error_messages[] = 'Email id is not exist,Kindly enter valid email address;';
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function find($id)
    {
        $results = \App\Models\Fan::with('artist', 'customer', 'reward')->orderBy('created_at', 'desc')->where('_id', $id)->first();

        $data = [];

        array_set($data, '_id', $results->_id);
        array_set($data, 'artist_id', $results->artist_id);
        array_set($data, 'customer_id', $results->customer_id);
        array_set($data, 'status', $results->status);
        array_set($data, 'fans', $results->fans);
        array_set($data, 'given_reward', $results->given_reward);
        array_set($data, 'reward_id', $results->reward_id);
        array_set($data, 'first_name', $results->customer->first_name);
        array_set($data, 'last_name', $results->customer->last_name);
        array_set($data, 'email', $results->customer->email);
        array_set($data, 'picture', $results->customer->picture);
        array_set($data, 'title', @$results->reward->title);
        array_set($data, 'reward_type', @$results->reward->reward_type);
        array_set($data, 'coins', @$results->reward->coins ? @$results->reward->coins : @$results->reward->xp);
        array_set($data, 'reward_title', @$results->reward->reward_title);

        return $data;
    }

    public function update($data, $id)
    {
        $error_messages = $results = [];

        if (empty($error_messages)) {
            $results['fan'] = $this->repObj->update($data, $id);
            $artist_id      = $results['fan']['artist_id'];
            $purge_result   = $this->awsElasticCacheRedis->purgeArtistLeaderBoardsCache(['artist_id' => $artist_id]);

        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }
    public function sendNotification($data,$id){
        $error_messages = $results = [];

        if (empty($error_messages)) {
            $results['fan'] = $this->repObj->sendNotification($data, $id);
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }
}