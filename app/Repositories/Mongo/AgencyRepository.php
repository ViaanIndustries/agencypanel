<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\AgencyInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Agency as Agency;
use Carbon, Log, DB, Config;
use App\Services\Jwtauth;
use Request;
use MongoDB\BSON\UTCDateTime;
use App\Services\CachingService;


class AgencyRepository extends AbstractRepository implements AgencyInterface
{
    protected $caching;
    protected $modelClassName = 'App\Models\Agency';

    public function __construct(Jwtauth $jwtauth, CachingService $caching)
    {
        $this->jwtauth = $jwtauth;
        $this->caching = $caching;
        parent::__construct();
    }



    public function index($requestData)
    {
        $results = [];
        $perpage = ($requestData['perpage'] == NULL) ? Config::get('app.perpage') : intval($requestData['perpage']);
        $mobile = (isset($requestData['mobile']) && $requestData['mobile'] != '') ? $requestData['mobile'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';
      
        $appends_array = array('mobile' => $mobile, 'status' => $status);

        $query = \App\Models\Agency::orderBy('name');;

        if ($mobile != '') {
            $query->where('mobile',  $mobile );
        }

        if ($status != '') {
            $query->where('status', $status);
        }

      $results['agency'] = $query->paginate($perpage);
      $results['appends_array'] = $appends_array;

        return $results;
    }


  

    public function store($postData)
    {
        $data = $postData;
        $agency = new $this->model($data);
        $agency->save();
    }


  

    public function update($postData, $id)
    {
        $data = $postData;
        
        $agency = $this->model->findOrFail($id);
        $agency->update($data);
        return $agency;
    }

    public function agencylist()
    {
        $agency         = \App\Models\Agency::where('status', "active")->get()->pluck('agency_name', '_id');
        return $agency;
    }

}
