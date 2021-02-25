<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;
use App\Repositories\Contracts\BucketInterface;

class BucketRequest extends Request
{

    protected $repObj;

    public function __construct(BucketInterface $repObj)
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
                'name' => 'required',
                'code' => 'required',
                'level' => 'required',
                'artist_id' => 'required',
                'meta.title' => 'required',
                'meta.description' => 'required',
                'meta.keywords' => 'required',
            ];
        } else {
            $rules = [
                'name' => 'required',
                'code' => 'required',
                'level' => 'required',
                'artist_id' => 'required',
                'meta.title' => 'required',
                'meta.description' => 'required',
                'meta.keywords' => 'required',
            ];
        }

        // dd($rules);
        return $rules;

    }
}
