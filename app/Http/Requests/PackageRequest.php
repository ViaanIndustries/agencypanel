<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;
use App\Repositories\Contracts\PackageInterface;

class PackageRequest extends Request
{

    protected $repObj;
    
    public function __construct(PackageInterface $repObj)
    {
        $this->repObj = $repObj;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {

        $regex='regex:/^\d*(\.\d{2})?$/';

        $rules = [
            'name'          => 'required',
            'coins'         => 'required | integer',
            'price'         => 'required',
            'xp'            => 'required | integer ',
            'sku'           => 'required',
            'artists'       =>  'required',
            'platforms'     => 'required',
        ];

        // dd($rules);
        return $rules;


        





    }
}
