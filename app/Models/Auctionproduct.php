<?php
/**
 * Created by PhpStorm.
 * User: sibani
 * Date: 12/6/18
 * Time: 1:24 PM
 */

namespace App\Models;
use App\Models\Basemodel;
use Crypt;

class Auctionproduct extends Basemodel
{

    protected $connection = 'arms_contents';

    protected $primaryKey = '_id';

    protected $collection = "auctionproducts";

    public function artist()
    {
        return $this->belongsTo('App\Models\Cmsuser', 'artist_id');
    }
}