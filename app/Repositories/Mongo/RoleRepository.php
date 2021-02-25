<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\RoleInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Role as Role;

class RoleRepository extends AbstractRepository implements RoleInterface
{

    protected $modelClassName = 'App\Models\Role';


	  public function index($requestData)
    {

        $results            =     [];
        $perpage            =     ($requestData['perpage'] == NULL) ? Config::get('app.perpage') : intval($requestData['perpage']);
        $name               =     (isset($requestData['name']) && $requestData['name'] != '')  ? $requestData['name'] : '';
        $status             =     (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';

        $appends_array      =     array('name' => $name, 'status' => $status);

        $query              =     \App\Models\Role::orderBy('name');

       if($name != ''){
           $query->where('name', 'LIKE', '%'. $name .'%');
       }

       if($status != ''){
           $query->where('status', $status);
       }


        $results['roles']       		=     $query->paginate($perpage);
        $results['appends_array']     	=     $appends_array;

        return $results;
    }
}

