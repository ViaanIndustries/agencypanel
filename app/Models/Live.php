<?php
/**
 * ModelName : Live.
 * Maintains a list of functions used for Live.
 *
 * @author      Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since       2019-05-29
 * @link        http://bollyfame.com
 * @copyright   2019 BOLLYFAME
 * @license     http://bollyfame.com/license
 */

namespace App\Models;
use App\Models\Basemodel;

class Live extends  Basemodel {

    protected $connection   = 'arms_contents';

    protected $primaryKey   = '_id';

    protected $collection   = 'lives';

    protected $dates        = ['created_at', 'updated_at', 'schedule_at', 'start_at', 'end_at', 'schedule_end_at'];

    public function setCoinsAttribute($value) {
        $this->attributes['coins'] = intval($value);
    }

	/**
	 * Get the artist that whose live belongs to.
	 */
	public function artist() {
		return $this->belongsTo('App\Models\Cmsuser');
	}

    /*
    public function getDates() {
        return ['created_at', 'updated_at', 'schedule_at'];
    }
    */


    /**
     * Get the Lead Cast that belongs to live event.
     */
    public function leadcast() {
        return $this->belongsTo('App\Models\Cast', 'lead_cast_id');
    }

    /**
     * The casts that belong to the live event.
     */
    public function casts(){
        return $this->belongsToMany('App\Models\Cast', null, 'lives', 'casts');
    }
}
