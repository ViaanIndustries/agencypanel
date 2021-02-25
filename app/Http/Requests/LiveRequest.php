<?php

namespace App\Http\Requests;

/**
 * Live
 *
 * @author      Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since       2019-05-29
 * @link        http://bollyfame.com
 * @copyright   2019 BOLLYFAME
 * @license     http://bollyfame.com/license
 */

use App\Http\Requests\Request;
use App\Repositories\Contracts\LiveInterface;

class LiveRequest extends Request {

    protected $repObj;

    public function __construct(LiveInterface $repObj) {
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
            'artist_id'         => 'required',
            'type'              => 'required',
            'name'              => 'required',
            'schedule_at'       => 'required',
            'schedule_end_at'   => 'required',
        ];

        return $rules;
    }
}
