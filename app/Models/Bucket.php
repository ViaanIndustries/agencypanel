<?php

namespace App\Models;

use App\Models\Basemodel;
use Crypt;

/** 
 * ModelName : Bucket.
 * Maintains a list of functions used for Bucket.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */


class Bucket extends  Basemodel {


    protected $connection = 'arms_contents';

    protected $primaryKey = '_id';

    protected $collection = "buckets";

    public function setParentIdAttribute($value){
        $this->attributes['parent_id'] = trim($value);
    }

    public function setArtistIdAttribute($value){
        $this->attributes['artist_id'] = trim($value);
    }

    public function setCodeAttribute($value){
        $this->attributes['code'] = str_slug(strtolower(trim($value)));
    }

    public function setLevelAttribute($value){
        $this->attributes['level'] = intval($value);
    }

    public function bucketlanguage()
    {
        return $this->hasMany('App\Models\Bucketlang');
    }

    
}