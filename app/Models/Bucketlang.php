<?php

namespace App\Models;

use App\Models\Basemodel;
use Crypt;

/** 
 * ModelName : BucketLang.
 * Maintains a list of functions used for BucketLang.
 *
 * @author Ruchi Sharma <ruchi.sharma@bollyfame.com>
 */


class Bucketlang extends  Basemodel {

    protected $connection = 'arms_contents';

    protected $primaryKey = '_id';

    protected $collection = "bucketlanguages";

    public function bucket()
    {
        return $this->belongsTo('App\Models\Bucket', 'bucket_id');
    }
    
}