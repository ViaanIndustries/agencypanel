<?php
/**
 * Created by PhpStorm.
 * User: sibani
 * Date: 20/7/18
 * Time: 12:56 PM
 */
namespace App\Models;

use App\Models\Basemodel;


class Dailystats extends Basemodel
{

    protected $connection = 'arms_transactions';

    protected $primaryKey = '_id';

    protected $collection = "dailystats";

    protected $dates = ['created_at', 'updated_at', 'stats_at'];
}