<?php
/**
 * Created by PhpStorm.
 * User: sibani
 * Date: 10/8/18
 * Time: 1:32 PM
 */

namespace App\Models;

use App\Models\Basemodel;
class Media extends Basemodel
{
    protected $connection = 'arms_contents';

    protected $primaryKey = '_id';

    protected $collection = "medias";

    
}