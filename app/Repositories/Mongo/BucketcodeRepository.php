<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\BucketcodeInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Bucketcode as Bucketcode;
use Config,DB;

class BucketcodeRepository extends AbstractRepository implements BucketcodeInterface
{

    protected $modelClassName = 'App\Models\Bucketcode';

    public function index($requestData, $perpage = NULL)
    {
        //DB::enableQueryLog();
        $results            =     [];
        $perpage            =     ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
        $name               =     (isset($requestData['name']) && $requestData['name'] != '')  ? $requestData['name'] : '';
        $status             =     (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';

        $appends_array      =     array('name' => $name, 'status' => $status);

        $query              =     \App\Models\Bucketcode::orderBy('name');

       // dd(DB::getQueryLog());
       if($name != ''){
           $query->where('name', 'LIKE', '%'. $name .'%');
       }

       if($status != ''){
           $query->where('status', $status);
       }



        $results['bucketcodes']       =     $query->paginate($perpage);

        $results['appends_array']     =     $appends_array;


//        print_pretty($query->paginate($perpage)->toArray());exit;
        return $results;
    }

}




