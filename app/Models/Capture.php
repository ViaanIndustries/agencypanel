<?php

namespace App\Models;

use App\Models\Basemodel;



class Capture extends Basemodel
{

    protected $connection = 'arms_transactions';

    protected $primaryKey = '_id';

    protected $collection = "captures";


    public function artist(){
        return $this->belongsTo('App\Models\Cmsuser','artist_id');
    }



    public function customer(){
        return $this->belongsTo('App\Models\Customer','customer_id');
    }




}
