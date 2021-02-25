<?php

namespace App\Models;
use App\Models\Basemodel;

use Carbon\Carbon;

class Passbook extends Basemodel
{

    protected $connection = 'arms_transactions';

	protected $primaryKey = '_id';

    protected $collection = "passbooks";

    public function setXpAttribute($value){
        $this->attributes['xp'] = intval(trim($value));
    }


    public function setCoinsAttribute($value){
        $this->attributes['coins'] = intval(trim($value));
    }

    public function setTotalCoinsAttribute($value){
        $this->attributes['total_coins'] = intval(trim($value));
    }

    public function setQuantityAttribute($value){
        $this->attributes['quantity'] = intval(trim($value));
    }

    public function setAmountAttribute($value){
        $this->attributes['amount'] = floatval(trim($value));
    }

    public function setCoinsBeforeTxnAttribute($value){
        $this->attributes['coins_before_txn'] = intval(trim($value));
    }

    public function setCoinsAfterTxnAttribute($value){
        $this->attributes['coins_after_txn'] = intval(trim($value));
    }


    public function setPlatformAttribute($value){
        $this->attributes['platform'] = strtolower(trim($value));
    }

    public function setStatusAttribute($value){
        $this->attributes['status'] = strtolower(trim($value));
    }

    public function setTxnTypeAttribute($value){
        $this->attributes['txn_type'] = strtolower(trim($value));
    }



    public function customer(){
        return $this->belongsTo('App\Models\Customer','customer_id');
    }

    public function artist(){
        return $this->belongsTo('App\Models\Cmsuser','artist_id');
    }


    public function rechargeby()
    {
        return $this->belongsTo('App\Models\Cmsuser', 'loggedin_user_id');
    }

    public function package(){
        return $this->belongsTo('App\Models\Package','entity_id');
    }


    public function content(){
        return $this->belongsTo('App\Models\Content','entity_id');
    }


    public function gift(){
        return $this->belongsTo('App\Models\Gift','entity_id');
    }


    /**
     * Get the live detail
     */
    public function live() {
        return $this->belongsTo('App\Models\Live','entity_id');
    }
}
