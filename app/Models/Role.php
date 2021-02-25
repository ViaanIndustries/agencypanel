<?php

namespace App\Models;

use App\Models\Basemodel;

/** 
 * ModelName : Role.
 * Maintains a list of functions used for Role.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */


class Role extends  Basemodel {

    protected $connection = 'arms_customers';

    protected $primaryKey = '_id';

    protected $collection = "roles";


	public function cmsusers(){
		return $this->belongsToMany('App\Models\Cmsuser', null, 'roles', 'cumsusers');
	}
	public function badges(){
		return $this->belongsToMany('App\Models\Badge', null, 'roles', 'badges');
	}

    
}