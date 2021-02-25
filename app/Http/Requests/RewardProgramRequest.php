<?php

namespace App\Http\Requests;

/**
 * RewardProgram
 *
 * @author      Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since       2019-08-27
 * @link        http://bollyfame.com
 * @copyright   2019 BOLLYFAME
 * @license     http://bollyfame.com/license
 */

use App\Http\Requests\Request;
use App\Repositories\Contracts\RewardProgramInterface;

class RewardProgramRequest extends Request {

    protected $repObj;

    public function __construct(RewardprogramInterface $repObj) {
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
            'artist_id' => 'required',
            'name'      => 'required',
            'event'     => 'required',
        ];

        return $rules;
    }
}
