<?php

namespace App\Models;

use App\Models\Basemodel;
use Crypt;

/**
 * ModelName : Activity.
 * Maintains a list of functions used for Activity.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */


class Activity extends  Basemodel {

    protected $connection = 'arms_activities_jobs';

    protected $primaryKey = '_id';

//    protected $connection = 'arms_customers';

    protected $collection = "activities";

    public function setXpAttribute($value){

        $this->attributes['xp'] = intval($value);

//        var_dump($this->attributes['xp']);exit;

    }




}
