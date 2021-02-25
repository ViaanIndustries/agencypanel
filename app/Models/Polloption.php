<?php

namespace App\Models;

use App\Models\Basemodel;

/** 
 * ModelName : Polloption.
 * Maintains a list of functions used for Polloption.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */


class Polloption extends  Basemodel {

    protected $connection = 'arms_contents';

    protected $primaryKey = '_id';

    protected $collection = "polloptions";


//    public function poll(){
//        return $this->belongsTo('App\Models\Poll','poll_id');
//    }
    public function content(){
        return $this->belongsTo('App\Models\Content','content_id');
    }

    
}