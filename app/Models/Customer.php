<?php

namespace App\Models;

use App\Models\Basemodel;
use Hash;

/**
 * ModelName : Customer.
 * Maintains a list of functions used for Customer.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */


class Customer extends  Basemodel {

    protected $connection = 'arms_customers';


    protected $collection = "customers";

    protected $hidden = array('password', 'token');

    protected $dates = array('last_visited', 'dob', 'email_otp_generated_at');

    public function setPasswordAttribute($value){
        $this->attributes['password'] = Hash::make($value);
    }

    public function setEmailAttribute($value){
        $this->attributes['email'] = trim(strtolower($value));
    }

    public function getFullNameAttribute () {
        return $this->first_name.' '.$this->last_name;
    }

//
//    public function setDeviceIdAttribute($value){
//        $this->attributes['device_id'] = (string) trim($value);
//    }
//
//    public function setFcmIdAttribute($value){
//
//        $this->attributes['fcm_id'] = (string) trim($value);
//    }
//
//    public function setSegmentIdAttribute($value){
//
//        $this->attributes['segment_id'] = intval($value);
//    }
//
//    public function setPlatformAttribute($value){
//        $this->attributes['platform'] = (string) trim($value);
//    }

    public function setMobileCountryCodeAttribute($value){
        $this->attributes['mobile_country_code'] = intval(trim($value));
    }

    public function likes(){
        return $this->hasMany('App\Models\Like');
    }


    public function comments(){
        return $this->hasMany('App\Models\Comment');
    }

    public function customerdevice(){
        return $this->hasMany('App\Models\Customerdeviceinfo');
    }

    public function customeractivity(){
        return $this->hasMany('App\Models\CustomerActivity');
    }

    public function livecomments(){
        return $this->hasMany('App\Models\Livecomment');
    }

    public function settings(){
        return $this->belongsToMany('App\Models\Setting',null, 'customer_id', 'settings');
    }

    public function artists(){
        return $this->belongsToMany('App\Models\Cmsuser',null, 'customers', 'artists');
    }


}
