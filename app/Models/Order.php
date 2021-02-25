<?php

namespace App\Models;
use App\Models\Basemodel;


class Order extends Basemodel
{

    protected $connection = 'arms_transactions';

	protected $primaryKey = '_id';

    protected $collection = "orders";


    public function setVendorAttribute($value){
        $this->attributes['vendor'] = strtolower(trim($value));
    }

    public function setPlatformAttribute($value){
        $this->attributes['platform'] = strtolower(trim($value));
    }



  	public function package(){
        return $this->belongsTo('App\Models\Package','package_id');
    }

    public function customer(){
        return $this->belongsTo('App\Models\Customer','customer_id');
    }
    
    public function artist(){
        return $this->belongsTo('App\Models\Cmsuser','artist_id');
    }

}
