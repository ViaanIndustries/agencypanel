<?php

namespace App\Models;

use Config;

use Illuminate\Database\Eloquent\Model;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;
class Basemodel extends Eloquent
{


    protected $guarded = [];

    protected $maintain_slug = true;

    protected $primaryKey = '_id';

    protected $dates = ['created_at', 'updated_at'];


    public function scopeActive($query)
    {

        return $query->where('status', '=', 'active');
    }


    public function scopeInactive($query)
    {

        return $query->where('status', '=', 'inactive');
    }

    public function scopeNotGenuineCustomers($query, $customer_name)
    {
        if (!empty($customer_name)) {
            $not_genuine_cids = array_column(\App\Models\Customer::select('_id')
                ->where('first_name', 'LIKE', '%' . $customer_name . '%')
                ->orWhere('last_name', 'LIKE', '%' . $customer_name . '%')
                ->orWhere('email', 'LIKE', '%' . $customer_name . '%')->get()
                ->toArray(),'_id');
        } else {
            $not_genuine_cids = array_column(\App\Models\Customer::whereIn('email', Config::get('product.' . env('PRODUCT') . '.test_customers'))
                ->orWhereIn('status', ['inactive', 'banned'])
                ->select('_id')
                ->get()
                ->toArray(), '_id');
        }

        return $query->whereIn('customer_id', $not_genuine_cids);
    }

    public function scopeGenuineCustomers($query, $customer_name)
    {
        $test_cids = array_column(\App\Models\Customer::whereIn('email', Config::get('product.' . env('PRODUCT') . '.test_customers'))
                ->orWhereIn('status', ['inactive', 'banned'])
                ->select('_id')
                ->get()
                ->toArray(), '_id');

        if (!empty($customer_name)) {
            $cids = array_column(\App\Models\Customer::select('_id')
                ->where('first_name', 'LIKE', '%' . $customer_name . '%')
                ->orWhere('last_name', 'LIKE', '%' . $customer_name . '%')
                ->orWhere('email', 'LIKE', '%' . $customer_name . '%')
                ->get()
                ->toArray(),'_id');
            $result = $query->whereIn('customer_id', $cids)->whereNotIn('customer_id', $test_cids);
        } else {
            $result = $query->whereNotIn('customer_id', $test_cids);
        }

        return $result;
    }


    public function scopeBetweenDate($query, $start_date, $end_date, $field = 'created_at')
    {
        return $query->where($field, '>=', new \DateTime(date("d-m-Y", strtotime($start_date))))->where($field, '<=', new \DateTime(date("d-m-Y", strtotime($end_date))));
    }


    public function setIdAttribute($value)
    {
        $this->attributes['_id'] = $value;
    }


    public function setOrderingAttribute($value)
    {
        $this->attributes['ordering'] = intval($value);
    }



    public function scopeUserTypeCustomers($query, $customer_name, $user_type = 'genuine')
    {

        if (!empty($customer_name)){
            $cids = \App\Models\Customer::where('first_name', 'LIKE', '%' . $customer_name . '%')->orWhere('last_name', 'LIKE', '%' . $customer_name . '%')->orWhere('email', 'LIKE', '%' . $customer_name . '%')->lists('_id');
            return $result = $query->whereIn('customer_id', $cids);
        }

        $not_genuine_cids = \App\Models\Customer::whereIn('email', Config::get('product.' . env('PRODUCT') . '.test_customers'))->orWhereIn('status', ['inactive', 'banned'])->lists('_id');

        if($user_type == 'genuine'){
            $result = $query->whereNotIn('customer_id', $not_genuine_cids);
        }else{
            $result = $query->whereIn('customer_id', $not_genuine_cids);
        }

        return $result;
    }




}


