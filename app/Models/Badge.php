<?php

namespace App\Models;

use App\Models\Basemodel;

/** 
 * ModelName : Badge.
 * Maintains a list of functions used for Bucket.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */


class Badge extends  Basemodel {

    protected $connection = 'arms_customers';

    protected $primaryKey = '_id';

    protected $collection = "badges";


    public function artist(){
        return $this->belongsTo('App\Models\Cmsuser','artist_id');
    }


    
}