<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;
use App\Repositories\Contracts\CustomerInterface;

class CustomerRequest extends Request
{

    protected $repObj;

    public function __construct(CustomerInterface $repObj)
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

        // dd( $blogs->_id);
        if ($this->method() == 'PUT')
        {
            $name_rule = 'required';

        }
        else
        {
            $name_rule = 'required|unique:customers';
        }


        $rules = [
            'first_name'         => $name_rule
        ];


        // dd($rules);
        return $rules;




    }
}
