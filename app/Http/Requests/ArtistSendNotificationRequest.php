<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class ArtistSendNotificationRequest extends Request
{


    public function __construct()
    {

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
            'deeplink'  => 'required',
            'test'      => 'required',
            'title'     => 'required',
            'body'      => 'required'
        ];

        // dd($rules);
        return $rules;

    }
}
