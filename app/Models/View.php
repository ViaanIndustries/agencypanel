<?php

namespace App\Models;

use App\Models\Basemodel;

/** 
 * ModelName : View.
 * Maintains a list of functions used for View.
 *
 * @author Ruchi Sharma <ruchi.sharma@bollyfame.com>
 */


class View extends  Basemodel {

	protected $connection = 'arms_contents';

    protected $collection   =   "views";

}