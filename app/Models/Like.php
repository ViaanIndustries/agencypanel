<?php

namespace App\Models;

use App\Models\Basemodel;

/** 
 * ModelName : Like.
 * Maintains a list of functions used for Like.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */


class Like extends  Basemodel {

    protected $connection = 'arms_contents';

    protected $collection   =   "likes";



    public function setNameAttribute($value){

        if(isset($value) && $value != ''){
            $this->attributes['name'] = trim(strtolower($value));
        }

    }


    public function setEntityIdAttribute($value){
        $this->attributes['entity_id'] = trim($value);
    }


    public function customer(){
        return $this->belongsTo('App\Models\Customer','customer_id');
    }

    public function artist(){
        return $this->belongsTo('App\Models\Cmsuser','artist_id');
    }




}