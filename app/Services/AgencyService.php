<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Input, Redirect, Config, Session, Hash, Cache, Log;
use Carbon;
use App\Repositories\Contracts\AgencyInterface;
 

class AgencyService
{
    protected $repObj;
 
    public function __construct(
        AgencyInterface $repObj
        
    )
    {
         $this->repObj = $repObj;
       
    }

    public function index($request)
    {
        $results = $this->repObj->index($request);
        return $results;
    }

    public function store($request)
    {
        $data = $request->all();
        $error_messages = $results = [];
        array_set($data, 'slug', str_slug($data['agency_name']));

        if (empty($error_messages)) {

            $agency = $this->repObj->store($data);
            $results['agency'] = $agency;   
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function find($id)
    {
        $results = $this->repObj->find($id);
        return $results;
    }


    public function update($request, $id)
    {
        $data = $request->all();
        $error_messages = $results = [];
        $slug = str_slug($data['agency_name']);
        array_set($data, 'slug', $slug);

        if (empty($error_messages)) {

            $agency = $this->repObj->update($data, $id);
            $results['agency'] = $agency;
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }



    public function agencylist()
    {
        $error_messages     =   $results = [];
        $results = $this->repObj->agencylist();

        return ['error_messages' => $error_messages, 'results' => $results];
    }

}
