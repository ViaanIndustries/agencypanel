<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class AdminUser extends Eloquent
{
    protected $connection = 'arms_customers';
   // protected $connection = 'mongodb';
    protected $collection = 'super_admin_user';
     protected $fillable = [
        'username', 'password','role'
    ];
}
