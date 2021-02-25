<?php

namespace App\Http\Requests\Api\Artist;

use App\Http\Requests\Request;
use App\Repositories\Contracts\ArtistInterface;

class LoginRequest extends Request
{

    protected $repObj;
    
    public function __construct(ArtistInterface $repObj)
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
            'email' => 'required|email',
            'identity' => 'required',
            'password' => 'required'
        ];

        return $rules;
    }
}
