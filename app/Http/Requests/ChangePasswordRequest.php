<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;
use App\Repositories\Contracts\CmsuserInterface;

class ChangePasswordRequest extends Request
{

    protected $repObj;

    public function __construct(CmsuserInterface $repObj)
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
        
        if ($this->method() == 'PUT')
        {
            $rules = [
                'new_pass'      => 'required',
                'confirm_pass'  => 'required|same:new_pass'
            ];
        } else {
            $rules = [
                'new_pass'      => 'required',
                'confirm_pass'  => 'required|same:new_pass'
            ];
        }
        
        // dd($rules);
        return $rules;
    }
}
