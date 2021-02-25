<?php

namespace App\Models;

use App\Models\Basemodel;

/** 
 * ModelName : Golive.
 * Maintains a list of functions used for Golive.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */

// comments, likes, views

class Golive extends  Basemodel {

    protected $connection = 'arms_transactions';

    protected $primaryKey = '_id';

    protected $collection = "golives";

    protected $dates        =   array('start','end');
    
    public function setLikesCountAttribute($value){
        $this->attributes['likes_count'] = intval($value);
    }


    public function setCommentsCountAttribute($value){
        $this->attributes['comments_count'] = intval($value);
    }


    public function setViewsCountAttribute($value){
        $this->attributes['views_count'] = intval($value);
    }


    public function artistconfig(){
        return $this->hasOne('App\Models\Artistconfig','artist_id');
    }


    public function artist(){
        return $this->belongsTo('App\Models\Cmsuser','artist_id');
    }





}