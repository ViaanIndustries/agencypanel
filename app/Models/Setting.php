<?php

namespace App\Models;

use App\Models\Basemodel;

/**
 * ModelName : Gift.
 * Maintains a list of functions used for Gift.
 *
 * @author Pranita S. <pranita@razrmedia.com>
 */


class Setting extends Basemodel
{

    protected $connection = 'arms_activities_jobs';

    protected $primaryKey = '_id';

    protected $collection = "settings";



    public function artists(){
        return $this->belongsToMany('App\Models\Cmsuser',null, 'settings', 'artist_id');
    }

     public function customers(){
        return $this->belongsToMany('App\Models\Customer',null, 'settings', 'customer_id');
     }
}
