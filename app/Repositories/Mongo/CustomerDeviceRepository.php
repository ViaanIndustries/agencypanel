<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\CustomerDeviceInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use Config;

class CustomerDeviceRepository extends AbstractRepository implements CustomerDeviceInterface
{

    protected $modelClassName = 'App\Models\Customerdeviceinfo';


    public function __construct(){
        parent::__construct();
    }

    public function customerDevices($customer_id)
    {
        $customer_id      =   trim($customer_id);
        $devices          =   \App\Models\Customerdeviceinfo::with('artist')->where('customer_id', $customer_id)->orderBy('_id','desc')->get();
        return $devices;
    }









}
