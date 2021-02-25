<?php 

namespace App\Services;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use App\Services\Gcp;
use Session;

use App\Repositories\Contracts\BannerInterface;
use App\Models\Banner as Banner;


class BannerService
{
    protected $repObj;
    protected $banner;

    public function __construct(Banner $banner, BannerInterface $repObj,Gcp $gcp)
    {
        $this->banner       = $banner;
        $this->repObj       = $repObj;
        $this->gcp          = $gcp;
    }


    public function index($request)
    {
        $error_messages     =   $results = [];
        $requestData        =   $request->all();
        $results            =   $this->repObj->index($requestData);

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function paginate()
    {
        $error_messages     =   $results = [];
        $results = $this->repObj->paginateForApi();

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function activeLists()
    {
        $error_messages     =   $results = [];
        $results = $this->repObj->activelists();

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function find($id)
    {
        $results = $this->repObj->find($id);
        return $results;
    }


    public function show($id)
    {
        $error_messages     =   $results = [];
        if(empty($error_messages)){
            $results['badge']    =   $this->repObj->find($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function getBannersByType($request)
    {
        $error_messages  = $results = [];
        $response        = $this->repObj->getBannersByType($request);
        $results         =  apply_cloudfront_url($response);
        return ['error_messages' => $error_messages, 'results' => $results];
      //  return $results;
    }

    public function store($request)
    {
        $data               =   $request->all();
        $error_messages     =   $results = [];
        if($request->hasFile('cover')) {

            //upload to local drive
            $upload         =   $request->file('cover');
            $folder_path    =   'uploads/banners/t/';
            $img_path       =   public_path($folder_path);
            $imageName      =   time() .'_'. str_slug($upload->getRealPath()). '.' . $upload->getClientOriginalExtension();
            $fullpath       =   $img_path . $imageName;
            $upload->move($img_path, $imageName);
            chmod($fullpath, 0777);


            //upload to gcp
            
            $object_source_path     =   $fullpath;
            $object_upload_path     =   'banners/t/'.$imageName;
            $params                 =   ['object_source_path' => $object_source_path, 'object_upload_path' => $object_upload_path];
            $uploadToGcp            =   $this->gcp->localFileUpload($params);
            $thumb_url              =   Config::get('gcp.base_url').Config::get('gcp.default_bucket_path').$object_upload_path;
             
            $photo                  =   [ 'thumb' => $thumb_url];
            array_set($data, 'cover', $photo);

            @unlink($fullpath);

        }

     //  print_r($data);exit;
        if(empty($error_messages)){
            $results['badge']    =   $this->repObj->store($data);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function update($request, $id)
    { 
        $data               =   $request->all();
        $error_messages     =   $results = [];
         if($request->hasFile('cover')) {

            //upload to local drive
            $upload         =   $request->file('cover');
            $folder_path    =   'uploads/banners/t/';
            $img_path       =   public_path($folder_path);
            $imageName      =   time() .'_'. str_slug($upload->getRealPath()). '.' . $upload->getClientOriginalExtension();
            $fullpath       =   $img_path . $imageName;
            $upload->move($img_path, $imageName);
            chmod($fullpath, 0777);


            //upload to gcp
            
            $object_source_path     =   $fullpath;
            $object_upload_path     =   'banners/t/'.$imageName;
            $params                 =   ['object_source_path' => $object_source_path, 'object_upload_path' => $object_upload_path];
            $uploadToGcp            =   $this->gcp->localFileUpload($params);
            $thumb_url              =   Config::get('gcp.base_url').Config::get('gcp.default_bucket_path').$object_upload_path;
             
            $photo                  =   [ 'thumb' => $thumb_url];
            array_set($data, 'cover', $photo);

            @unlink($fullpath);

        }
        if(empty($error_messages)){
            $results['badge']   = $this->repObj->update($data, $id);
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function destroy($id)
    {
        $results = $this->repObj->destroy($id);
        return $results;
    }


}