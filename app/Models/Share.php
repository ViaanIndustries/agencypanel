<?php

namespace App\Models;

use App\Models\Basemodel;

/** 
 * ModelName : Share.
 * Maintains a list of functions used for Share.
 *
 * @author Ruchi Sharma <ruchi.sharma@bollyfame.com>
 */


class Share extends  Basemodel {

	protected $connection = 'arms_contents';

    protected $collection   =   "shares";

}