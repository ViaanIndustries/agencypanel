<?php
/**
 * Created by PhpStorm.
 * User: sibani
 * Date: 24/5/18
 * Time: 1:56 PM
 */

namespace App\Models;
use App\Models\Basemodel;


class Artistbadge extends  Basemodel {

    protected $connection = 'arms_activities_jobs';

    protected $primaryKey = '_id';

    protected $collection = "artistbadges";

    public function badge(){
        return $this->belongsTo('App\Models\Badge','badge_id');
    }

}
