<?php
/**
 * Created by PhpStorm.
 * User: sibani
 * Date: 15/10/18
 * Time: 3:14 PM
 */

namespace App\Models;

use App\Models\Basemodel;

class Moderation Extends Basemodel
{

    protected $connection = 'arms_activities_jobs';

    protected $primaryKey = '_id';

    protected $collection = "moderations";

    public function customer()
    {
        return $this->belongsTo('App\Models\Customer', 'customer_id');
    }

    public function artist()
    {
        return $this->belongsTo('App\Models\Cmsuser', 'artist_id');
    }

    public function content()
    {
        return $this->belongsTo('App\Models\Content', 'entity_id');
    }

    public function comment()
    {
        return $this->belongsTo('App\Models\Comment', 'comment_id');
    }
}