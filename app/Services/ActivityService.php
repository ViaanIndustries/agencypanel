<?php

namespace App\Services;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;

use App\Repositories\Contracts\ActivityInterface;
use App\Models\Activity as Activity;


class ActivityService
{
    protected $repObj;
    protected $activity;

    public function __construct(Activity $activity, ActivityInterface $repObj)
    {
        $this->activity = $activity;
        $this->repObj = $repObj;
    }


    public function index($request)
    {
        $requestData = $request->all();
        $results = $this->repObj->index($requestData);
        return $results;
    }

    public function paginate()
    {
        $error_messages = $results = [];
        $results = $this->repObj->paginateForApi();

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function activeLists()
    {
        $error_messages = $results = [];
        $results = $this->repObj->activelists();

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function find($id)
    {
        $results = $this->repObj->find($id);
        return $results;
    }


    public function show($id)
    {
        $error_messages = $results = [];
        if (empty($error_messages)) {
            $results['activity'] = $this->repObj->find($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function store($request)
    {
        $data = $request->all();
        $error_messages = $results = [];

        array_set($data, 'slug', str_slug($data['name']));
        if (empty($error_messages)) {
            $results['activity'] = $this->repObj->store($data);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function update($request, $id)
    {
        $data = $request->all();
        $error_messages = $results = [];
        $slug = str_slug($data['name']);
        array_set($data, 'slug', $slug);
        $category_count = $this->repObj->checkUniqueOnUpdate($id, 'slug', $slug);
        if ($category_count > 0) {
            $error_messages[] = 'activity with name already exist : ' . str_replace("-", " ", ucwords($slug));
        }

        if (empty($error_messages)) {
            $results['bucketcode'] = $this->repObj->update($data, $id);
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function destroy($id)
    {
        $results = $this->repObj->destroy($id);
        return $results;
    }

    public function activity_list($requset, $artistid)
    {
        $artistActivitiesData = \App\Models\Artistactivity::where('artist_id', $artistid)->get()->toArray();
        $masterActivitiesData = $this->repObj->activity_list()->toArray();

        $activityArr=[];
        foreach ($masterActivitiesData as $key => $value) {
            $activity_id = $value['_id'];
            $master_artist = head(array_where($artistActivitiesData, function ($master_key, $master_val) use ($activity_id) {
                if ($master_val['activity_id'] == $activity_id) {
                    return $master_val;
                }
            }));

            array_set($value, 'xp', (!empty($master_artist['xp'])) ? $master_artist['xp'] : $value['xp']);
            array_push($activityArr,$value);
        }
        return (!empty($activityArr)) ? $activityArr : $masterActivitiesData;
    }

}