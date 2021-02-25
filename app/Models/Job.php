<?php

namespace App\Models;

use App\Models\Basemodel;

/** 
 * ModelName : Job.
 * Maintains a list of functions used for Job.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */

// comments, likes, views

class Job extends  Basemodel {

    protected $connection = 'arms_activities_jobs';

    protected $primaryKey = '_id';

    protected $collection = "jobs";


    
}