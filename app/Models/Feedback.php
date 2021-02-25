<?php

namespace App\Models;

use App\Models\Basemodel;


class Feedback extends Basemodel
{

    protected $connection = 'arms_activities_jobs';

    protected $primaryKey = '_id';

    protected $collection = "feedbacks";


    public function customer(){
        return $this->belongsTo('App\Models\Customer','customer_id');
    }


    public function artist(){
        return $this->belongsTo('App\Models\Cmsuser','artist_id');
    }




}
