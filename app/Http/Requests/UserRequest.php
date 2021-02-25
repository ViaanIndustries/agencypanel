<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;
use App\Repositories\Contracts\UserInterface;

class UserRequest extends Request
{

    protected $repObj;
    
    public function __construct(UserInterface $repObj)
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


        // // dd( $users->_id);
        // if ($this->method() == 'PUT')
        // {
        //     $name_rule = 'required';
        // }
        // else
        // {
        //     $name_rule = 'required|unique:users';
        // }


        $rules = [
        'name'    => 'required',
        'email'    => 'required|email', 
        'password' => 'required|min:6' ,
        'groups'    => 'required'
        ];
        
        // dd($rules);
        return $rules;



    }
}
