<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\LivecommentInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Livecomment as Livecomment;

use Config, Carbon;

class LivecommentRepository extends AbstractRepository implements LivecommentInterface
{

    protected $modelClassName = 'App\Models\Livecomment';



    public function liveComments($requestData)
    {

        $artist_id              =   trim($requestData['artist_id']);
        $perpage                =   (isset($requestData['perpage'])) ? $requestData['perpage'] : Config::get('app.perpage');

        $data                   =   $this->model->active()->with('customer')->where('artist_id', $artist_id)->orderBy('_id')->paginate(intval($perpage))->toArray();


        $commentArr                                     =   [];
        $comments                                       =   (isset($data['data']))  ?   $data['data'] : [];
        foreach ($comments as $comment){
            $content_id                                 =   $comment['_id'];
            $comment['customer']                        =   (isset($comment['customer'])) ? array_only($comment['customer'],['first_name','last_name','email','fcm_id','device_id','segment_id','platform','picture']) : [];
            $comment['human_readable_created_date']     =   Carbon\Carbon::parse($comment['created_at'])->format('F j\\, Y h:i A');
            $comment['date_diff_for_human']             =   Carbon\Carbon::parse($comment['created_at'])->diffForHumans();
            array_push($commentArr, $comment);
        }


        $responeData                                    =   [];
        $responeData['list']                            =   $commentArr;
        $responeData['paginate_data']['total']          =   (isset($data['total'])) ? $data['total'] : 0;
        $responeData['paginate_data']['per_page']       =   (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['current_page']   =   (isset($data['current_page'])) ? $data['current_page'] : 0;
        $responeData['paginate_data']['last_page']      =   (isset($data['last_page'])) ? $data['last_page'] : 0;
        $responeData['paginate_data']['from']           =   (isset($data['from'])) ? $data['from'] : 0;
        $responeData['paginate_data']['to']             =   (isset($data['to'])) ? $data['to'] : 0;

        $artist                                         =   \App\Models\Cmsuser::find($artist_id,['first_name','last_name','email']);
        $responeData['artist']                          =   $artist;

        return $responeData;
    }


}

