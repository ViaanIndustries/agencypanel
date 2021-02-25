<?php

namespace App\Models;

use App\Models\Basemodel;

/**
 * ModelName : Package.
 * Maintains a list of functions used for package.
 *
 * @author Pranita S. <pranita@razrmedia.com>
 */

class Package extends Basemodel
{

    protected $connection = 'arms_transactions';

    protected $primaryKey = '_id';

    protected $collection = "packages";


    public function setCoinsAttribute($value){
        $this->attributes['coins'] = intval($value);
    }


    public function setPriceAttribute($value){
        $this->attributes['price'] = float_value($value);
    }

    public function setPriceUsdAttribute($value){
        $this->attributes['price_usd'] = floatval($value);
    }


    public function setXpAttribute($value){
        $this->attributes['xp'] = intval($value);
    }

    public function artists(){
        return $this->belongsToMany('App\Models\Cmsuser',null, 'packages', 'artists');
    }

}
