<?php

namespace App\Services;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use App\Services\Gcp;
use Session;

use App\Repositories\Contracts\PolloptionInterface;
use App\Models\Polloption as Polloption;
use App\Services\Kraken;
use App\Services\CachingService;


class PolloptionService
{
    protected $repObj;
    protected $poll;
    protected $kraken;
    protected $caching;

    public function __construct(Polloption $poll, PolloptionInterface $repObj, Gcp $gcp, Kraken $kraken, CachingService $caching)
    {
        $this->poll = $poll;
        $this->repObj = $repObj;
        $this->gcp = $gcp;
        $this->kraken = $kraken;
        $this->caching = $caching;
    }


    public function index($request)
    {
        $error_messages = $results = [];
        $requestData = $request->all();
        $results = $this->repObj->index($requestData);

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function paginate()
    {
        $error_messages = $results = [];
        $results = $this->repObj->paginateForApi();

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function activeLists()
    {
        $error_messages = $results = [];
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
        $error_messages = $results = [];
        if (empty($error_messages)) {
            $results['polloption'] = $this->repObj->find($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function store($request)
    {
        $data = $request->all();
        $error_messages = $results = [];
        array_set($data, 'slug', str_slug($data['name']));
        if ($request->hasFile('cover')) {

            //upload to local drive
//            $upload         =   $request->file('cover');
//            $folder_path    =   'uploads/polloptions/t/';
//            $img_path       =   public_path($folder_path);
//            $imageName      =   time() .'_'. str_slug($upload->getRealPath()). '.' . $upload->getClientOriginalExtension();
//            $fullpath       =   $img_path . $imageName;
//            $upload->move($img_path, $imageName);
//            chmod($fullpath, 0777);
//
//
//            //upload to gcp
//
//            $object_source_path     =   $fullpath;
//            $object_upload_path     =   'polloptions/t/'.$imageName;
//            $params                 =   ['object_source_path' => $object_source_path, 'object_upload_path' => $object_upload_path];
//            $uploadToGcp            =   $this->gcp->localFileUpload($params);
//            $thumb_url              =   Config::get('gcp.base_url').Config::get('gcp.default_bucket_path').$object_upload_path;
//
//            $photo                  =   [ 'thumb' => $thumb_url];
//            array_set($data, 'photo', $photo);
//
//            @unlink($fullpath);

//------------------------------------Kraken Image Compression--------------------------------------------

            $parmas = ['file' => $request->file('cover'), 'type' => 'polloptions'];
//              $parmas = ['url' => 'https://storage.googleapis.com/arms-razrmedia/contents/c/php1qTnAH.jpg'];
            $kraked_img = $this->kraken->uploadKrakenImageToGCP($parmas);
            $photo = $kraked_img;
            array_set($data, 'photo', $photo);

//------------------------------------Kraken Image Compression--------------------------------------------
        }

        if (empty($error_messages)) {
            $results['polloption'] = $this->repObj->store($data);

            $content_id = $data['content_id'];

            $bucket_id = \App\Models\Content::where('_id', $content_id)->project(['_id' => 0])->first(['bucket_id']);
            $bucket_id = !empty($bucket_id) ? $bucket_id->toArray()['bucket_id'] : '';

            $platforms = ['android', 'ios', 'web'];

            foreach ($platforms as $key => $platform) {
                $cachetag_name = $platform . '_' . $bucket_id . "_contents";
                $env_cachetag = env_cache_tag_key($cachetag_name);              //  ENV_PLATFORM_BUCKETID_contents

                $this->caching->flushTag($env_cachetag);
            }

            $cachetag_name = $content_id . "_contentdetails";
            $env_cachetag = env_cache_tag_key($cachetag_name);    //ENV_contentid_contentdetails
            $this->caching->flushTag($env_cachetag);

        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function update($request, $id)
    {
        $data = $request->all();

        $error_messages = $results = [];
        if ($request->hasFile('cover')) {

            //upload to local drive
//            $upload = $request->file('cover');
//            $folder_path = 'uploads/polloptions/t/';
//            $img_path = public_path($folder_path);
//            $imageName = time() . '_' . str_slug($upload->getRealPath()) . '.' . $upload->getClientOriginalExtension();
//            $fullpath = $img_path . $imageName;
//            $upload->move($img_path, $imageName);
//            chmod($fullpath, 0777);
//
//            //upload to gcp
//            $object_source_path = $fullpath;
//            $object_upload_path = 'polloptions/t/' . $imageName;
//            $params = ['object_source_path' => $object_source_path, 'object_upload_path' => $object_upload_path];
//            $uploadToGcp = $this->gcp->localFileUpload($params);
//            $thumb_url = Config::get('gcp.base_url') . Config::get('gcp.default_bucket_path') . $object_upload_path;
//
//            $photo = ['thumb' => $thumb_url];
//            array_set($data, 'photo', $photo);
//
//            @unlink($fullpath);

//------------------------------------Kraken Image Compression--------------------------------------------

            $parmas = ['file' => $request->file('cover'), 'type' => 'polloptions'];
//              $parmas = ['url' => 'https://storage.googleapis.com/arms-razrmedia/contents/c/php1qTnAH.jpg'];
            $kraked_img = $this->kraken->uploadKrakenImageToGCP($parmas);
            $photo = $kraked_img;
            array_set($data, 'photo', $photo);

//------------------------------------Kraken Image Compression--------------------------------------------
        }

        if (empty($error_messages)) {
            $results['polloption'] = $this->repObj->update($data, $id);

            $content_id = $data['content_id'];

            $bucket_id = \App\Models\Content::where('_id', $content_id)->project(['_id' => 0])->first(['bucket_id']);
            $bucket_id = !empty($bucket_id) ? $bucket_id->toArray()['bucket_id'] : '';

            $platforms = ['android', 'ios', 'web'];

            foreach ($platforms as $key => $platform) {
                $cachetag_name = $platform . '_' . $bucket_id . "_contents";
                $env_cachetag = env_cache_tag_key($cachetag_name);              //  ENV_PLATFORM_BUCKETID_contents

                $this->caching->flushTag($env_cachetag);
            }

            $cachetag_name = $content_id . "_contentdetails";
            $env_cachetag = env_cache_tag_key($cachetag_name);    //ENV_contentid_contentdetails
            $this->caching->flushTag($env_cachetag);

        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function destroy($id)
    {
        $results = $this->repObj->destroy($id);
        return $results;
    }


}