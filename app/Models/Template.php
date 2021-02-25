<?php

namespace App\Models;

use App\Models\Basemodel;


/** 
 * ModelName : CommunicationTemplate.
 * Maintains a list of functions used for CommunicationTemplate.
 *
 * @author Sanjay Sahu <sanjay.id7@gmail.com>
 */


class Template extends  Basemodel {


    protected $connection = 'arms_activities_jobs';

	protected $table = "templates";


}