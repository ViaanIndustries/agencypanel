<?php
/**
 * Created by PhpStorm.
 * User: sibani
 * Date: 12/6/18
 * Time: 3:25 PM
 */

namespace App\Http\Requests;

use App\Http\Requests\Request;
use App\Repositories\Contracts\AuctionproductInterface;


class AuctionproductRequest
{
    protected $repObj;

    public function __construct(AuctionproductInterface $repObj)
    {
        $this->repObj = $repObj;
    }


    public function rules()
    {
        $rules = [
            'name' => 'required'
        ];
        return $rules;
    }
}