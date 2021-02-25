<?php

namespace App\Models;

use App\Models\Basemodel;

/** 
 * ModelName : Campaign.
 * Maintains a list of functions used for Campaign.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */


//type      -   notification, sms, email
//status    -   created, sending, completed


class Campaign extends  Basemodel {


    protected $connection = 'arms_activities_jobs';

    protected $collection   =   "campaigns";

    protected $dates        =   ['created_at','updated_at'];



    public function setNameAttribute($value){

        if(isset($value) && $value != ''){
            $this->attributes['name'] = trim(strtolower($value));
        }

    }


    public function artist(){
        return $this->belongsTo('App\Models\Cmsuser','artist_id');
    }



}