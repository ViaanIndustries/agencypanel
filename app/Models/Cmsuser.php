<?php

namespace App\Models;

use App\Models\Basemodel;
use Hash;

/**
 * ModelName : Cmsuser.
 * Maintains a list of functions used for Cmsuser.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */
class Cmsuser extends Basemodel
{

    protected $connection = 'arms_customers';


    protected $collection = "cmsusers";

    protected $hidden = array('password', 'token');

    protected $dates = array('last_visited', 'dob');


    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Hash::make($value);
    }

    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = trim(strtolower($value));
    }


    public function setIsLiveAttribute($value)
    {
        $value = (isset($value) && ($value == 'true' || $value == true)) ? true : false;
        $this->attributes['is_live'] = $value;
    }


    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

//    public function getLastVisitedAttribute()
//    {
//        $last_visited  =  (!empty($this->last_visited) && $this->last_visited != '') ? $this->last_visited : NULL;
//        return $last_visited;
//    }
//
//    public function getDobAttribute()
//    {
//        $dob  =  (!empty($this->dob) && $this->dob != '') ? $this->dob : NULL;
//        return $dob;
//    }



    public function roles()
    {
        return $this->belongsToMany('App\Models\Role', null, 'cmsusers', 'roles');
    }

    public function gifts()
    {
        return $this->belongsToMany('App\Models\Gift', null, 'cmsusers', 'artists');
    }

    public function packages()
    {
        return $this->belongsToMany('App\Models\Package', null, 'cmsusers', 'artists');
    }

    public function settings()
    {
        return $this->belongsToMany('App\Models\Setting', null, 'cmsusers', 'artist_id');
    }

    public function artistconfig()
    {
        return $this->hasOne('App\Models\Artistconfig', 'artist_id');
    }

    public function buckets()
    {
        return $this->hasMany('App\Models\Bucket');
    }

    /**
     * Get the lives for the cmsuser/artist.
     */
    public function lives() {
        return $this->hasMany('App\Models\Live');
    }

    /**
     * Get Artist Languages
     */
    public function getArtistLanguages() {
        $ret = [];
        $language_ids = [];
        $artist_config = $this->artistconfig;
        if($artist_config) {
            if(isset($artist_config->languages)) {
                $language_ids = $artist_config->languages;
            }

            if(isset($artist_config->language_default)) {
                $language_ids[] = $artist_config->language_default;
            }

            $language_ids = array_unique($language_ids);
        }

        return $ret;
    }

}
