<?php

namespace App\Http\Controllers\Admin;

/**
 * ControllerName : Feedback.
 * Maintains a list of functions used for Feedback.
 *
 * @author      Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since       2019-08-08
 * @link        http://bollyfame.com/
 * @copyright   2019 BOLLYFAME
 * @license     http://bollyfame.com/license
 */

use Illuminate\Http\Request;

use Input;
use Redirect;
use Config;
use Session;


use App\Http\Controllers\Controller;
//use App\Http\Requests\FeedbackRequest;
use App\Services\FeedbackService;

class FeedbackController extends Controller
{
    protected $service;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function __construct(FeedbackService $service) {
        $this->service = $service;
    }


    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request) {
        $viewdata                   = [];
        $data                       = $this->service->index($request);
        $viewdata['feedbacks']      = $data['feedbacks'];
        $viewdata['appends_array']  = $data['appends_array'];

        $viewdata['types']          = Config::get('app.feedback_types');
        $viewdata['entity']          = Config::get('app.entity');

        $viewdata['artists']        = [];
        $viewdata['platforms']      = array_except(Config::get('app.platforms'), 'paytm');

        return view('admin.feedbacks.index', $viewdata);
    }



}
