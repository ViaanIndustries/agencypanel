<?php

namespace App\Services;

use Input;
use Redirect;
use Config;
use Session;
use App\Repositories\Contracts\PurchaseInterface;
use App\Models\Purchase as Purchase;
use App\Services\Jwtauth;

class PurchaseService
{
    protected $jwtauth;
    protected $purchaseRepObj;

    public function __construct(
        Jwtauth $jwtauth,
        Purchase $purchase,
        PurchaseInterface $purchaseRepObj
    )
    {
        $this->jwtauth = $jwtauth;
        $this->purchase = $purchase;
        $this->purchaseRepObj = $purchaseRepObj;
    }


    public function index($request)
    {
        $requestData = $request->all();

        $results = $this->purchaseRepObj->index($requestData);
        return $results;
    }






}