<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;
use App\Repositories\Contracts\BannerInterface;

class BannerRequest extends Request
{

    protected $repObj;
    
    public function __construct(BannerInterface $repObj)
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
            'name'         => 'required',
            'artist_id'    =>'required',
            'type'         =>'required'
        ];

        // dd($rules);
        return $rules;


        





    }
}
