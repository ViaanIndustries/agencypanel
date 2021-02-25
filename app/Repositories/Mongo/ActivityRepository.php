<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\ActivityInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Activity as Activity;
use Config;

class ActivityRepository extends AbstractRepository implements ActivityInterface
{

    protected $modelClassName = 'App\Models\Activity';

    public function index($requestData, $perpage = NULL)
    {

        $results = [];
        $perpage = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
        $name = (isset($requestData['name']) && $requestData['name'] != '') ? $requestData['name'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';

        $appends_array = array('name' => $name, 'status' => $status);

        $query = \App\Models\Activity::orderBy('name');

        if ($name != '') {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }

        if ($status != '') {
            $query->where('status', $status);
        }
        $results['activities'] = $query->paginate($perpage);
        $results['appends_array'] = $appends_array;
        return $results;
    }

    public function activity_list(){
        $query = \App\Models\Activity::orderBy('name')->get();
        return $query;
    }

}




