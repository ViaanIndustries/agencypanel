<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\SettingInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Services\Notifications\PushNotification;
use App\Models\Setting as Setting;

class SettingRepository extends AbstractRepository implements SettingInterface
{

    protected $modelClassName = 'App\Models\Setting';

    
	  public function index($requestData)
    { 

        $results            =     [];
        $perpage            =     ($requestData['perpage'] == NULL) ? Config::get('app.perpage') : intval($requestData['perpage']);
        $name               =     (isset($requestData['name']) && $requestData['name'] != '')  ? $requestData['name'] : '';
        $status             =     (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';

        $appends_array      =     array('name' => $name, 'status' => $status);

        $query              =     \App\Models\Setting::orderBy('name');

       if($name != ''){
           $query->where('name', 'LIKE', '%'. $name .'%');
       }

       if($status != ''){
           $query->where('status', $status);
       }


        $results['roles']       		=     $query->paginate($perpage);
        $results['appends_array']     	=     $appends_array;

        //print_pretty($results);exit;
  		//print_pretty($query->paginate($perpage)->toArray());exit;
        return $results;
    }
    
    // public function store($postData)
    // {
        
        
    // }

     public function syncCustomers($postData , $setting)
    {
        if (!empty($postData['customer_id'])) {
            $customers = array_map('trim', $postData['customer_id']);
            $setting->customers()->sync(array());
            foreach ($customers as $key => $value) {
                $setting->customers()->attach($value);
            }
        }
    }

    public function store($data)
    {
          
      
        $settingObj         = new $this->model($data);
        $settingObj->save();
       
       
        $this->syncArtists($syncCustomers , $settingObj);
      
        return $settingObj;
    }

    
    
}

