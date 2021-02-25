<?php
/**
 * ModelName : ContestantContent.
 * Maintains a list of functions used for Contestant Contents.
 *
 * @author Shekhar <chandrashekhar.thalkar@bollyfame.com>
 */

namespace App\Models;
use App\Models\Basemodel;

class Contestantcontent extends  Basemodel {

    protected $connection = 'arms_contents';

    protected $primaryKey = '_id';

    protected $collection = 'contestantcontents';
}
