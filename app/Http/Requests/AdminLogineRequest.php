<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminLogineRequest extends FormRequest
{
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
       return [
             'adminname' => 'required|string|max:50',
            'adminpwd' => 'required|string'
         ];
    }
    public function messages()
    {
        return [
            'adminname.required' => 'Admin username is required!',
            'adminpwd.required' => 'Admin password is required!',
         ];
    }
    
}
