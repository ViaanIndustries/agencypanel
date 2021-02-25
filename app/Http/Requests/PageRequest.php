<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;
use App\Repositories\Contracts\PageInterface;

class PageRequest extends Request
{

    protected $repObj;

    public function __construct(PageInterface $repObj)
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
            'page_name' => 'required',
            'name'      => 'required',
            'type'      => 'required',
        ];

        return $rules;
    }
}
