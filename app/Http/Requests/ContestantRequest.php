<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;
use App\Repositories\Contracts\ContestantInterface;

class ContestantRequest extends Request
{
    protected $repObj;

    public function __construct(ContestantInterface $repObj)
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
            'first_name'=> 'required',
            'last_name' => 'required',
            'email'     => 'required|email',
            'mobile'    => 'required',
        ];

        if ($this->method() == 'POST') {
            $rules['photo'] = 'required';
        }

        return $rules;
    }
}
