<?php

namespace App\Models;

use App\Models\Basemodel;

/** 
 * ModelName : Banner.
 * Maintains a list of functions used for Bucket.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */


class Poll extends  Basemodel {

    protected $connection = 'arms_contents';

    protected $primaryKey = '_id';

    protected $collection = "polls";


    public function artist(){
        return $this->belongsTo('App\Models\Cmsuser','artist_id');
    }
    public function pollOptions(){

        return $this->hasMany('App\Models\polloptions');
    }
}


