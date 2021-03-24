<?php

namespace App\Models;

use App\Models\Basemodel;
use Hash;
/** 
 * ModelName : Banner.
 * Maintains a list of functions used for Bucket.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */


class Agency extends  Basemodel {

    protected $connection = 'arms_contents';

    protected $primaryKey = '_id';
    protected $hidden = array('password', 'token');

    protected $collection = "agency";

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Hash::make($value);
    }

    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = trim(strtolower($value));
    }
    
}