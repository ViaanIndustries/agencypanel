<?php
/**
 * Created by PhpStorm.
 * User: sibani
 * Date: 22/5/18
 * Time: 3:13 PM
 */

namespace App\Models;
use App\Models\Basemodel;


class Artistactivity extends  Basemodel {

    protected $connection = 'arms_activities_jobs';

    protected $primaryKey = '_id';

    protected $collection = "artistactivities";

    public function setXpAttribute($value){
        $this->attributes['xp'] = intval($value);
    }

    public function activity(){
        return $this->belongsTo('App\Models\Activity','activity_id');
    }
}
