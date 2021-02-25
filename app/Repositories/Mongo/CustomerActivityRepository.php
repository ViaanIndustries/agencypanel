<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\CustomerActivityInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\CustomerActivity as CustomerActivity;
use Carbon, Log,DB ,Config;
class CustomerActivityRepository extends AbstractRepository implements CustomerActivityInterface
{

    protected $modelClassName = 'App\Models\CustomerActivity';

    public function index($requestData)
    {

        $results            =     [];
        $artist_id          =      [];
        $perpage            =     ($requestData['perpage'] == NULL) ? Config::get('app.perpage') : intval($requestData['perpage']);
        $name               =     (isset($requestData['name']) && $requestData['name'] != '')  ? $requestData['name'] : '';
        $status             =     (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '' ;
        $appends_array      =     array('name' => $name);

        $query              =     \App\Models\CustomerActivity::orderBy('name');

        if($name != ''){
            $query->where('name', 'LIKE', '%'. $name .'%');
        }

        if($status != ''){
            $query->where('status', $status);
        }

        if($artist_id != ''){
            $query->where('artists', $artist_id);
        }

        $results['activities']       		    =     $query->paginate($perpage);
        $results['appends_array']     	=     $appends_array;

        return $results;
    }


    public function store($postData)
    {

        $data          =   $postData;
        $recodset = new $this->model($data);
        $recodset->save();
        return $recodset;
    }


    public function update($postData, $id)
    {

        $data = $postData;


        $recodset = $this->model->findOrFail($id);
        $recodset->update($data);
        return $recodset;
    }



    public function customerActivities($requestData,$customer_id)
    {
        $perpage            =     ($requestData['perpage'] == NULL) ? Config::get('app.perpage') :  intval($requestData['perpage']);
        $name               =     (isset($requestData['name']) && $requestData['name'] != '')  ? $requestData['name'] : '';
        $artist_id          =     (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : ''; ;
        $appends_array      =     array('name' => $name, 'artist_id' => $artist_id);

        // echo $customer_id;
        $query     =   \App\Models\CustomerActivity::with(
            [
                'artist'=>function($query){$query->select('_id','first_name','last_name');},
                'package'=>function($query){$query->select('_id','name');},
                'gift'=>function($query){$query->select('_id','name');}
            ]
        )->where('customer_id', $customer_id)->orderBy('created_at','desc');

        if($name != ''){
            $query->where('name', 'LIKE', '%'. $name .'%');
        }


        if($artist_id != ''){
            $query->where('artist_id', $artist_id);
        }
        $activities        =     $query->paginate($perpage);

        $results['appends_array']       =     $appends_array;
        $results['activities']          =     $activities;
        // $activities          =     $query->paginate();
        // dd($results);exit();
        return $results;
    }



}