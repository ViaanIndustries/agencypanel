<?php

namespace App\Http\Controllers\Admin;

use Input;
use Redirect;
use Config;
use Session;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Services\ArtistService;
use App\Services\RewardService;

class RewardController extends Controller
{
    public function __construct(
        RewardService $rewardservice,
        ArtistService $artistservice
    )
    {
        $this->artistservice = $artistservice;
        $this->rewardservice = $rewardservice;
    }

    public function index(Request $request)
    {
        $viewdata = [];
        $request['perpage'] = 10;
        $responseData = $this->rewardservice->index($request);
        $artists = $this->artistservice->artistList();

        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results']->toArray() : [];
        $viewdata['titles'] = Config::get('app.reward_title');

        $viewdata['user_type'] = Array('genuine' => 'Genuine User', 'fake' => 'Test user');
        $viewdata['rewards'] = (isset($responseData['rewards'])) ? $responseData['rewards'] : [];
        $viewdata['coins'] = (isset($responseData['coins'])) ? $responseData['coins'] : 0;
        $viewdata['appends_array'] = (isset($responseData['appends_array'])) ? $responseData['appends_array'] : [];
        return view('admin.rewards.index', $viewdata);
    }

    public function create()
    {
        $viewdata = [];
        $artists = $this->artistservice->artistList();
        
        $viewdata['reward_title'] = Config::get('app.reward_title');
        $viewdata['reward_type'] = Array('coins' => 'Coins', 'xp' => 'XP');

        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];

        return view('admin.rewards.create', $viewdata);
    }

    public function store(Request $request)
    {
        $response = $this->rewardservice->store($request);

        if (!empty($response['error_messages'])) {
            return Redirect::back()->withErrors(['', $response['error_messages']]);
        }
        Session::flash('message', 'Reward added succesfully');
        return Redirect::route('admin.rewards.index');
    }



}