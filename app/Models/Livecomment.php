<?php

namespace App\Models;

use App\Models\Basemodel;

/** 
 * ModelName : Comment.
 * Maintains a list of functions used for Comment.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */


class Livecomment extends  Basemodel {


    protected $connection = 'arms_contents';


    protected $collection   =   "livecomments";

    protected $dates        =   ['created_at','updated_at'];


    public function setContentIdAttribute($value){
        $this->attributes['content_id'] = trim($value);
    }


    public function customer(){
        return $this->belongsTo('App\Models\Customer');
    }


    public function artist(){
        return $this->belongsTo('App\Models\Cmsuser','artist_id');
    }



}