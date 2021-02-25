<?php

namespace App\Models;

use App\Models\Basemodel;
use Crypt;

/** 
 * ModelName : Contentlang.
 * Maintains a list of functions used for ContentLang.
 *
 * @author Ruchi Sharma <ruchi.sharma@bollyfame.com>
 */


class Contentlang extends  Basemodel {

    protected $connection = 'arms_contents';

    protected $primaryKey = '_id';

    protected $collection = "contentlanguages";

    public function content()
    {
        return $this->belongsTo('App\Models\Content', 'content_id');
    }
    
}