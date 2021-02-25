<?php

namespace App\Models;

use App\Models\Basemodel;

/** 
 * ModelName : Notificationlog.
 * Maintains a list of functions used for Notificationlog.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */


//type      -   notification, sms, email
//status    -   created, sending, completed


class Notificationlog extends  Basemodel {


    protected $connection = 'arms_activities_jobs';

    protected $collection   =   "notificationlogs";

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