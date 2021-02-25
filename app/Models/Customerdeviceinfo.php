<?php

namespace App\Models;

use App\Models\Basemodel;

/** 
 * ModelName : Customerdeviceinfo.
 * Maintains a list of functions used for Customerdeviceinfo.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */


class Customerdeviceinfo extends  Basemodel {


    protected $connection = 'arms_customers';

    protected $collection = "customerdeviceinfos";

    public function setDeviceIdAttribute($value){
        $this->attributes['device_id'] = trim($value);
    }

    public function setFcmIdAttribute($value){

        $this->attributes['fcm_id'] = trim($value);
    }

    public function setSegmentIdAttribute($value){

        $this->attributes['segment_id'] = intval($value);
    }

    public function setPlatformAttribute($value){
        $this->attributes['platform'] = strtolower(trim($value));
    }


    public function customer(){
        return $this->belongsTo('App\Models\Customer','customer_id');
    }


    public function artist(){
        return $this->belongsTo('App\Models\Cmsuser','artist_id');
    }


}