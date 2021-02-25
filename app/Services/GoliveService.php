<?php

namespace App\Services;

use Input;
use Redirect;
use Config;
use Session;
use App\Repositories\Contracts\GoliveInterface;
use App\Models\Golive as Golive;


class GoliveService
{
    protected $goliveRepObj;
    protected $golive;

    public function __construct(Golive $golive, GoliveInterface $goliveRepObj)
    {
        $this->golive = $golive;
        $this->goliveRepObj = $goliveRepObj;
    }


    public function index($request)
    {
        $requestData = $request->all();

        $results = $this->goliveRepObj->index($requestData);
        return $results;
    }


    public function stats($request)
    {
        $requestData = $request->all();

        $results = $this->goliveRepObj->stats($requestData);
        return $results;
    }


    public function paginate()
    {
        $error_messages = $results = [];
        $results = $this->goliveRepObj->paginateForApi();

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function activeLists()
    {
        $error_messages = $results = [];
        $results = $this->goliveRepObj->activeLists();

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function find($id)
    {
        $results = $this->goliveRepObj->find($id);
        return $results;
    }


    public function show($id)
    {
        $error_messages = $results = [];
        if (empty($error_messages)) {
            $results['golive'] = $this->goliveRepObj->find($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function store($request)
    {
        $data = $request->all();
        $error_messages = $results = [];

        if (empty($error_messages)) {
            array_set($data, 'artist_id', $data['artist_id']);
            $results['golive'] = $this->goliveRepObj->store($data);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function update($request, $id)
    {
        $data = $request->all();
        $error_messages = $results = [];

        if (empty($error_messages)) {
            $results['golive'] = $this->goliveRepObj->update($data, $id);
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function destroy($id)
    {
        $results = $this->goliveRepObj->destroy($id);
        return $results;
    }


    public function start($request)
    {
        $data = array_except($request->all(), '_token');

        $data['type'] = !empty($data['type']) ? $data['type'] : 'general';
        $data['selected_auction_products'] = !empty($data['selected_auction_products']) ? $data['selected_auction_products'] : [];

        $error_messages = $results = [];

        if (empty($error_messages)) {
            $results['golive'] = $this->goliveRepObj->start($data);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function end($request)
    {
        $error_messages = [];
        $results = [];
        $data = $request->all();
        $golive_id = (isset($data['golive_id'])) ? trim($data['golive_id']) : '';
        $goliveObjectExist = \App\Models\Golive::where('_id', '=', $golive_id)->first();

        if (empty($goliveObjectExist)) {
            $error_messages[] = 'Golive session does not exists';
        }

        if (empty($error_messages)) {
            $results['golive'] = $this->goliveRepObj->end($data);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function web_end($data)
    {
        $error_messages = [];
        $results = [];
        $golive_id = (isset($data['golive_id'])) ? trim($data['golive_id']) : '';
        $goliveObjectExist = \App\Models\Golive::where('_id', '=', $golive_id)->first();

        if (empty($goliveObjectExist)) {
            $error_messages[] = 'Golive session does not exists';
        }

        if (empty($error_messages)) {
            $results['golive'] = $this->goliveRepObj->updateGoliveAdmin($data);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function generateStats($params)
    {

        $error_messages = $results = [];
        if (empty($error_messages)) {
            $results = $this->goliveRepObj->generateStats($params);
        }

        return ['error_messages' => $error_messages, 'results' => $results];

    }

}