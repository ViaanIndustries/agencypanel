<?php

namespace App\Models;

use App\Models\Basemodel;


class Fan extends Basemodel
{

    protected $connection = 'arms_customers';

    protected $primaryKey = '_id';

    protected $collection = "fans";


    public function artist()
    {
        return $this->belongsTo('App\Models\Cmsuser', 'artist_id');
    }

    public function customer()
    {
        return $this->belongsTo('App\Models\Customer', 'customer_id');
    }

    public function reward()
    {
        return $this->belongsTo('App\Models\Reward', 'reward_id');
    }


}