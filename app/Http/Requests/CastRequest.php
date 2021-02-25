<?php

namespace App\Http\Requests;

/**
 * Cast
 *
 * @author Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since 2019-05-13
 * @link http://bollyfame.com/
 * @copyright 2019 BOLLYFAME
 * @license http://bollyfame.com//license/
 */

use App\Http\Requests\Request;
use App\Repositories\Contracts\CastInterface;

class CastRequest extends Request
{

    protected $repObj;

    public function __construct(CastInterface $repObj)
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
            'first_name'    => 'required',
        ];

        return $rules;
    }
}
