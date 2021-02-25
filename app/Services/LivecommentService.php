<?php 

namespace App\Services;

use Input;
use Redirect;
use Config;
use Session;
use Request;
use App\Repositories\Contracts\LivecommentInterface;


class LivecommentService
{
    protected $repObj;

    public function __construct(LivecommentInterface $repObj)
    {
        $this->repObj = $repObj;
    }



    public function liveComments($request){

        $requestData        =   $request->all();
        $error_messages     =   $results = [];

        if(empty($error_messages)){
            $results['comments'] = $this->repObj->liveComments($requestData);
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }







}