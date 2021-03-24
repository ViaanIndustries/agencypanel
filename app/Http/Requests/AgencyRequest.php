<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;
use App\Repositories\Contracts\AgencyInterface;

class AgencyRequest extends Request
{

    protected $repObj;
    
    public function __construct(AgencyInterface $repObj)
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



        $rules = [
            'owner_name'          => 'required',
            'agency_name'         => 'required',
            'agency_address'        =>  'required',
            'mobile'          => 'required | integer',
            'email'         => 'required | email',
            'password'        =>  'required',
        ];

        // dd($rules);
        return $rules;


        





    }
}
