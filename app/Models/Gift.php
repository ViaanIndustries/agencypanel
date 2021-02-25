<?php

namespace App\Models;

use App\Models\Basemodel;

/** 
 * ModelName : Gift.
 * Maintains a list of functions used for Gift.
 *
 * @author Pranita S. <pranita@razrmedia.com>
 */


class Gift extends Basemodel
{

    protected $connection = 'arms_contents';

    protected $primaryKey = '_id';

    protected $collection = "gifts";


  
    public function artists(){
        return $this->belongsToMany('App\Models\Cmsuser',null, 'gifts', 'artists');
    }
}
