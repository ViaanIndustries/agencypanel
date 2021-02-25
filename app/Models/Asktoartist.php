<?php

namespace App\Models;

use App\Models\Basemodel;

/** 
 * ModelName : Asktoartist.
 * Maintains a list of functions used for Asktoartist.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */

// comments, likes, views

class Asktoartist extends  Basemodel {

    protected $connection = 'arms_activities_jobs';

    protected $primaryKey = '_id';

    protected $collection = "asktoartists";




    public function customer(){
        return $this->belongsTo('App\Models\Customer','customer_id');
    }

    public function artist(){
        return $this->belongsTo('App\Models\Cmsuser','artist_id');
    }


}