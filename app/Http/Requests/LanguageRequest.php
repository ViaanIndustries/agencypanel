<?php

namespace App\Http\Requests;

/**
 * Language
 *
 * @author      Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since       2019-05-13
 * @link        http://bollyfame.com
 * @copyright   2019 BOLLYFAME
 * @license     http://bollyfame.com/license
 */

use App\Http\Requests\Request;
use App\Repositories\Contracts\LanguageInterface;

class LanguageRequest extends Request {

    protected $repObj;

    public function __construct(LanguageInterface $repObj) {
        $this->repObj = $repObj;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        $rules = [
            'name'      => 'required',
            'code_3'    => 'required|size:3',
            'code_2'    => 'required|size:2',
        ];

        return $rules;
    }
}
