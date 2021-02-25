<?php

namespace App\Models;

use App\Models\Basemodel;

/** 
 * ModelName : Purchase.
 * Maintains a list of functions used for Purchase.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */


class Purchase extends  Basemodel {


    protected $connection = 'arms_transactions';

    protected $collection   =   "purchases";



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

    public function content(){
//        return $this->belongsTo('App\Models\Content','entity_id');
        return $this->belongsTo('App\Models\Content','entity_id')->where('entity', 'contents');
    }

    public function gift(){
        return $this->belongsTo('App\Models\Content','entity_id')->where('entity', 'gifts');
    }




}