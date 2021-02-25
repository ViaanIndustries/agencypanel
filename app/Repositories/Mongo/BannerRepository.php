<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\BannerInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Banner as Banner;
use Config;

class BannerRepository extends AbstractRepository implements BannerInterface
{

    protected $modelClassName = 'App\Models\Banner';


    public function index($requestData, $perpage = NULL)
    {

        $results            =     [];
        $perpage            =     ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
        $artist_id          =     (isset($requestData['artist_id']) && $requestData['artist_id'] != '')  ? $requestData['artist_id'] : '';
        $name               =     (isset($requestData['name']) && $requestData['name'] != '')  ? $requestData['name'] : '';
        $type               =     (isset($requestData['type']) && $requestData['type'] != '')  ? $requestData['type'] : '';
       
        $status             =     (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '' ;
        $query              =     \App\Models\Banner::with('artist')->orderBy('name');
        $appends_array      =     array('artist_id' => $artist_id, 'name' => $name,'type'=>$type,'status'=>$status);
        if($artist_id != ''){
           // echo $artist_id;exit;
            $query->where('artist_id', $artist_id);
        }

        if($name != ''){
            $query->where('name', 'LIKE', '%'. $name .'%');
        }
        if($type != ''){
           // echo $type;exit;
            $query->where('type', $type);
        }
         if($status != ''){
            $query->where('status', $status);
        }


        $results['banners']            =     $query->paginate($perpage);
        $results['appends_array']      =     $appends_array;
        return $results;
    }
    public function store($postData)
    {
        
        $data          =   $postData;

            
        if(!isset($postData['status'])){
            array_set($data, 'status', 'active');
        }
         
        $banner = new $this->model($data);
        $banner->save();
       
        
        return $banner;
    }

    public function getBannersByType($requestData)
    {
         $type               =     (isset($requestData['type']) && $requestData['type'] != '')  ? $requestData['type'] : '';
         $query              =     \App\Models\Banner::with('artist')->where('type',$type)->orderBy('created');
         $artist_id          =     (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id']: '';
         $perpage            =     ($requestData['perpage'] == NULL) ? \Config::get('app.perpage') : intval($perpage);


        $query              =     \App\Models\Banner::select('name','cover')->orderBy('name');
        if($artist_id != ''){
           // echo $artist_id;exit;
            $query->where('artist_id', $artist_id);
        }
         if($type != ''){
           // echo $artist_id;exit;
            $query->where('type', $type);
        }
        $banners                                        =     $query->paginate($perpage);
        
        $data                                           =    $banners->toArray();
        $bannerlist                                     =   (isset($data['data']))  ?   $data['data'] : [];
        
        $responeData                                    =   [];
        $responeData['list']                            =   $bannerlist;
        $responeData['paginate_data']['total']          =   (isset($data['total']))         ?   $data['total'] : 0;
        $responeData['paginate_data']['per_page']       =   (isset($data['per_page']))      ?   $data['per_page'] : 0;
        $responeData['paginate_data']['per_page']       =   (isset($data['per_page']))      ?   $data['per_page'] : 0;
        $responeData['paginate_data']['current_page']   =   (isset($data['current_page']))  ?   $data['current_page'] : 0;
        $responeData['paginate_data']['last_page']      =   (isset($data['last_page']))     ?   $data['last_page'] : 0;
        $responeData['paginate_data']['from']           =   (isset($data['from']))          ?   $data['from'] : 0;
        $responeData['paginate_data']['to']             =   (isset($data['to']))            ?   $data['to'] : 0;
       
        return $responeData;
    }
    public function update($postData,$id)
    {
        
        $data          =   $postData;

            
        if(!isset($postData['status'])){
            array_set($data, 'status', 'active');
        }
         
        $banner = $this->model->findOrFail($id);
        $banner->update($data);
       
        
        return $banner;
    }


}




