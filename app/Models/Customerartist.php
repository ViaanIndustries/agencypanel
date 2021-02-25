<?php

namespace App\Models;

use App\Models\Basemodel;

/** 
 * ModelName : Customerartist.
 * Maintains a list of functions used for Customerartist.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */


class Customerartist extends  Basemodel {


    protected $connection = 'arms_customers';

    protected $collection = "customerartists";


    public function customer(){
        return $this->belongsTo('App\Models\Customer','customer_id');
    }


    public function artist(){
        return $this->belongsTo('App\Models\Cmsuser','artist_id');
    }



}