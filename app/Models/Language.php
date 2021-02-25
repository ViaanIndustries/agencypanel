<?php
/**
 * ModelName : Language.
 * Maintains a list of functions used for Language.
 *
 * @author      Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since       2019-06-25
 * @link        http://bollyfame.com/
 * @copyright   2019 BOLLYFAME
 * @license     http://bollyfame.com/license
 */

namespace App\Models;
use App\Models\Basemodel;

class Language extends  Basemodel {

    protected $connection = 'arms_contents';

    protected $primaryKey = '_id';

    protected $collection = 'languages';

	public function setCode3Attribute($value){
        $this->attributes['code_3'] = trim(strtolower($value));
    }

	public function setCode2Attribute($value){
        $this->attributes['code_2'] = trim(strtolower($value));
    }

}
