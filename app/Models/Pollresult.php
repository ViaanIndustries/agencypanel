<?php
/**
 * Created by PhpStorm.
 * User: sibani
 * Date: 3/5/18
 * Time: 4:33 PM
 */

namespace App\Models;
use App\Models\Basemodel;


class Pollresult extends Basemodel
{
    protected $connection = 'arms_contents';

    protected $primaryKey = '_id';
    protected $collection = "pollresults";

    public function artistconfig(){
        return $this->belongsTo('App\Models\Artistconfig','artist_id');
    }

//    public function poll(){
//        return $this->belongsTo('App\Models\Poll','poll_id');
//    } 

    public function content(){
        return $this->belongsTo('App\Models\Content','content_id');
    }

    public function polloption(){
        return $this->belongsTo('App\Models\Polloption','option_id');
    }

    public function customer(){
        return $this->belongsTo('App\Models\Customer','cust_id');
    }


}