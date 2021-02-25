<?php
/**
 * Created by PhpStorm.
 * User: sibani
 * Date: 1/9/18
 * Time: 12:56 PM
 */

namespace App\Models;
use App\Models\Basemodel;

class Refundcoins extends  Basemodel {

    protected $connection = 'arms_transactions';

    protected $collection = "refundcoins";


    public function artist()
    {
        return $this->belongsTo('App\Models\Cmsuser', 'artist_id');
    }

    public function cmsuser()
    {
        return $this->belongsTo('App\Models\Cmsuser', 'loggedin_user_id');
    }

    public function customer()
    {
        return $this->belongsTo('App\Models\Customer', 'customer_id');
    }

}