<?php

namespace App\Repositories\Mongo;

use Request;
use Config;
use App\Repositories\Contracts\PollInterface;
use App\Repositories\AbstractRepository as AbstractRepository;


class PollRepository extends AbstractRepository implements PollInterface
{

    protected $modelClassName = 'App\Models\Poll';


    public function index($requestData)
    {
        $results = [];
        $perpage = (isset($requestData['perpage']) && $requestData['perpage'] != '') ? $requestData['perpage'] : '';
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $name = (isset($requestData['name']) && $requestData['name'] != '') ? $requestData['name'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';
        $appends_array = array('artist_id' => $artist_id, 'name' => $name, 'status' => $status);

        $query = \App\Models\Poll::with('artist')->orderBy('name');

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        if ($name != '') {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }

        if ($status != '') {
            $query->where('status', $status);
        }

        $results['polls'] = $query->paginate($perpage);
        $results['appends_array'] = $appends_array;
        return $results;
    }


    public function store($postData)
    {
        $data = array_except($postData, ['_token', 'cover']);

        if ($postData['status'] == 'active' || $postData['status'] == '') {
            array_set($data, 'status', 'active');
        }
//        if(!isset($postData['status'])){
//            array_set($data, 'status', 'active');
//        }
        array_set($data, 'stats', []);
        $poll = new $this->model($data);
        $poll->save();
        return $poll;
    }


    public function update($postData, $id)
    {
        $data = array_except($postData, ['_token', 'cover']);
        if ($postData['status'] == 'active' || $postData['status'] == '') {
            array_set($data, 'status', 'active');
        }
        $poll = $this->model->findOrFail($id);
        $poll->update($data);
        return $poll;
    }


    public function getPollsByType($requestData)
    {
        $type = (isset($requestData['type']) && $requestData['type'] != '') ? $requestData['type'] : '';
        $query = \App\Models\Poll::with('artist')->where('type', $type)->orderBy('created');
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $perpage = ($requestData['perpage'] == NULL) ? \Config::get('app.perpage') : intval($perpage);


        $query = \App\Models\Poll::select('name', 'cover')->orderBy('name');
        if ($artist_id != '') {
            // echo $artist_id;exit;
            $query->where('artist_id', $artist_id);
        }
        if ($type != '') {
            // echo $artist_id;exit;
            $query->where('type', $type);
        }
        $polls = $query->paginate($perpage);

        $data = $polls->toArray();
        $polllist = (isset($data['data'])) ? $data['data'] : [];

        $responeData = [];
        $responeData['list'] = $polllist;
        $responeData['paginate_data']['total'] = (isset($data['total'])) ? $data['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($data['current_page'])) ? $data['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($data['last_page'])) ? $data['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($data['from'])) ? $data['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($data['to'])) ? $data['to'] : 0;

        return $responeData;
    }
}

