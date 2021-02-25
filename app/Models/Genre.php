<?php
/**
 * ModelName : Genre.
 * Maintains a list of functions used for Genre.
 *
 * @author      Ruchi <ruchi.sharma@bollyfame.com>
 * @since       2019-07-23
 * @link        http://bollyfame.com/
 * @copyright   2019 BOLLYFAME
 * @license     http://bollyfame.com/license
 */

namespace App\Models;
use App\Models\Basemodel;

class Genre extends  Basemodel {

    protected $connection = 'arms_contents';

    protected $primaryKey = '_id';

    protected $collection = 'genres';

}
