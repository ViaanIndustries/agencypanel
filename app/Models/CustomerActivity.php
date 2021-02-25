<?php

namespace App\Models;

use App\Models\Basemodel;
use Crypt;

/**
 * ModelName : CustomerActivity.
 * Maintains a list of functions used for Bucket.
 *
schema:
name(purchase_package | send_gift | like | comment)
artist_id
customer_id
entity (gifts, likes,orders = name of the collection)
entity_id  ( id= respective collection id  )
total_quantity
total_coins	
coin_of_one
gained_xp
platform 


 *
 **/


class CustomerActivity extends  Basemodel {

    protected $connection = 'arms_activities_jobs';

    protected $primaryKey = '_id';

    protected $collection = "customeractivities";

	public function artist(){
        return $this->belongsTo('App\Models\Cmsuser','artist_id');
    }
	public function customer(){
        return $this->belongsTo('App\Models\Customer','customer_id');
    }

    public function order(){
        return $this->belongsTo('App\Models\Order','entity_id');
    }
    public function package(){
        return $this->belongsTo('App\Models\Package','package_id');
    }
    public function gift(){
        return $this->belongsTo('App\Models\Gift','entity_id');
    }
    public function scopeGift($query)
    {
        return $query->where('entity', '=', 'gift');
    }

     public function scopePackage($query)
    {
        return $query->where('entity', '=', 'orders');
    }
	
}