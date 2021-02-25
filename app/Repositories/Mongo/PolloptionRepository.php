<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\PolloptionInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Polloption as Polloption;
use Config;

class PolloptionRepository extends AbstractRepository implements PolloptionInterface
{

    protected $modelClassName = 'App\Models\Polloption';


    public function index($requestData, $perpage = NULL)
    {

        $results = [];
        $perpage = (isset($requestData['perpage']) && $requestData['perpage'] != '') ? $requestData['perpage'] : '';
        $content_id = (isset($requestData['content_id']) && $requestData['content_id'] != '') ? $requestData['content_id'] : '';
        $name = (isset($requestData['name']) && $requestData['name'] != '') ? $requestData['name'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';
        $appends_array = array('name' => $name, 'status' => $status);

        $query = \App\Models\Polloption::orderBy('name');

        if ($content_id != '') {
            $query->where('content_id', $content_id);
        }

        if ($name != '') {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }

        if ($status != '') {
            $query->where('status', $status);
        }

        $results['polloptions'] = $query->paginate($perpage);
        $results['appends_array'] = $appends_array;
        return $results;
    }


    public function store($postData)
    {

        $data = array_except($postData, ['_token', 'cover']);
        if ($postData['status'] == 'active' || $postData['status'] == '') {
            array_set($data, 'status', 'active');
        }

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


}




