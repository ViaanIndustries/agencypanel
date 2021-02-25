<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;
use App\Repositories\Contracts\CmsuserInterface;

class CmsuserRequest extends Request
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
                'first_name' => 'required',
                'email' => 'required|email',
             ];
        } else {
            $rules = [
                'first_name' => 'required',
                'email' => 'required|email',
              ];
        }


        // dd($rules);
        return $rules;


        // $rules = [
        // 'name'         => 'required',
        // 'slug'          => 'required|unique:countries'
        // ];
        // if($request->isMethod('PUT'))
        // {
        //     $rules['slug'] = 'required|unique:countries,slug:' . $request->slug;
        // }
        // //dd($rules);
        // return $rules;





    }
}
