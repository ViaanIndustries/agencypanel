<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;
use App\Repositories\Contracts\BucketInterface;

class ContentRequest extends Request
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
        if ($this->method() == 'PUT') {
            $rules = [
                'artist_id' => 'required',
                'bucket_id' => 'required',
                'level' => 'required',
            ];
        } else {
            $rules = [
                'artist_id' => 'required',
                'bucket_id' => 'required',
                'level' => 'required',
            ];
        }
        return $rules;
    }
}
