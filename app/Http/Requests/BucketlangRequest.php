<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;
use App\Repositories\Contracts\BucketlangInterface;

class BucketlangRequest extends Request
{

    protected $repObj;
    
    public function __construct(BucketlangInterface $repObj)
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
            'lang'         => 'required',
            'name'         => 'required',
            'caption'      => 'required'
        ];

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
