<?php

namespace App\Models;

use App\Models\Basemodel;

/** 
 * ModelName : Artistconfig.
 * Maintains a list of functions used for Artistconfig.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */


//type      -   notification, sms, email
//status    -   created, sending, completed


class Artistconfig extends  Basemodel {


    protected $connection = 'arms_customers';

    protected $collection   =   "artistconfigs";

    protected $dates        =   ['created_at','updated_at','last_updated_gifts','last_updated_buckets','last_updated_packages','last_visited'];

    public function setNameAttribute($value){

        if(isset($value) && $value != ''){
            $this->attributes['name'] = trim(strtolower($value));
        }

    }

//
//    public function getLastUpdatedGiftsAttribute()
//    {
//        $value  =  (!empty($this->last_updated_gifts) && $this->last_updated_gifts != ''  && $this->last_updated_gifts != NULL) ? $this->last_updated_gifts : NULL;
//        return $value;
//    }
//
//
//    public function getLastUpdatedBucketsAttribute()
//    {
//        $value  =  (!empty($this->last_updated_buckets) && $this->last_updated_buckets != '' && $this->last_updated_buckets != NULL) ? $this->last_updated_buckets : NULL;
//        return $value;
//    }
//
//    public function getLastUpdatedPackagesAttribute()
//    {
//        $value  =  (!empty($this->last_updated_packages) && $this->last_updated_packages != '' && $this->last_updated_packages != NULL) ? $this->last_updated_packages : NULL;
//        return $value;
//    }


    public function setAndroidVersionNoAttribute($value){
        $this->attributes['android_version_no'] = intval($value);
    }

    public function setIosVersionNoAttribute($value){
        $this->attributes['ios_version_no'] = intval($value);
    }


    public function artist(){
        return $this->belongsTo('App\Models\Cmsuser','artist_id');
    }

}