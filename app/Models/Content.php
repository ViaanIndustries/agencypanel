<?php

namespace App\Models;

use App\Models\Basemodel;
use Crypt;
//use Carbon;

//use MongoDB\BSON\UTCDateTime;

/**
 * ModelName : Content.
 * Maintains a list of functions used for Content.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 *

  vod_job_status -
    1. uploaded     (Uploaded to s3 using cms or api)
    2. submitted     (Created Hls Job using Cron and submited to elastic transcoder)
    3. completed    (Updated job status using cron)

 *
 */


class Content extends  Basemodel {

    protected $connection = 'arms_contents';

    protected $collection   =   "contents";

    protected $dates        =   ['created_at','updated_at','published_at','expired_at'];
//    protected $dates        =   ['created_at','updated_at'];


    public function setArtistIdAttribute($value){
        $this->attributes['artist_id'] = trim($value);
    }

    public function setBucketIdAttribute($value){
        $this->attributes['bucket_id'] = trim($value);
    }

    public function setLevelAttribute($value){
        $this->attributes['level'] = intval($value);
    }

    public function setCoinsAttribute($value){
        $this->attributes['coins'] = intval($value);
    }

    public function setAgeRestrictionAttribute($value){
        $this->attributes['age_restriction'] = intval($value);
    }


//    public function setCreatedAtAttribute($date)
//    {
//        $this->attributes['created_at'] = new UTCDateTime(strtotime($date) * 1000);
//    }
//
//    public function setUpdatedAtAttribute($date)
//    {
//    //        $this->attributes['updated_at'] = date('Y-m-d H:i:s', strtotime($date));
//        $this->attributes['updated_at'] = new UTCDateTime(strtotime($date) * 1000);
//    }


//    public function getCreatedAtAttribute($date)
//    {
//        return Carbon::parse($date);
//    }
//
//    public function getUpdatedAtAttribute($date)
//    {
//        return Carbon::parse($date);
//    }

    public function bucket(){
        return $this->belongsTo('App\Models\Bucket','bucket_id');
    }

    public function artist(){
        return $this->belongsTo('App\Models\Cmsuser','artist_id');
    }

    public function casts()
    {
        return $this->belongsToMany('App\Models\Cast', null, 'contents', 'casts');
    }

    public function contentlanguages()
    {
        return $this->hasMany('App\Models\Contentlang');
    }

    public function artistconfig() {
        return $this->belongsTo('App\Models\Artistconfig', 'artist_id');
    }
}
