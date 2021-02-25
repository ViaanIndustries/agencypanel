<?php

namespace App\Models;

use App\Models\Basemodel;

class UpcomingEvents extends Basemodel
{
    protected $connection = 'arms_contents';

    protected $primaryKey = '_id';

    protected $collection = "upcomingevents";

    protected $dates        = ['created_at', 'updated_at', 'schedule_at', 'schedule_end_at'];

    protected $fillable = ['name','event_id','schedule_at','schedule_end_at','desc','slug','status','artist_id'];

    public function artist() {
		return $this->belongsTo('App\Models\Cmsuser');
    }
    
    
}











