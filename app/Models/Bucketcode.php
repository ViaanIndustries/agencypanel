<?php

namespace App\Models;

use App\Models\Basemodel;
use Crypt;

/** 
 * ModelName : Bucketcode.
 * Maintains a list of functions used for Bucket.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */


class Bucketcode extends  Basemodel {

    protected $connection = 'arms_contents';

    protected $primaryKey = '_id';

    protected $collection = "bucketcodes";
    
}