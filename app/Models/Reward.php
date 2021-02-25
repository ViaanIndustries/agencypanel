<?php

namespace App\Models;

use App\Models\Basemodel;

/** 
 * ModelName : Reward.
 * Maintains a list of functions used for Role.
 *
 * @author Pranita S. <pranita@razrmedia.com>
 */


class Reward extends  Basemodel {

    protected $connection = 'arms_transactions';

    protected $primaryKey = '_id';

    protected $collection = "rewards";


	public function artist(){
		return $this->belongsTo('App\Models\Cmsuser', 'artist_id');
	}

	public function customer(){
		return $this->belongsTo('App\Models\Customer', 'customer_id');
	}


    
}