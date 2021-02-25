<?php

namespace App\Services;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use App\Services\Gcp;
use Session;

use App\Repositories\Contracts\PollInterface;
use App\Repositories\Contracts\ContentInterface;
use App\Models\Poll as Poll;


class PollService
{
    protected $repObj;
    protected $poll;

    public function __construct(Poll $poll, ContentInterface $repObj, Gcp $gcp)
    {
        $this->poll = $poll;
        $this->repObj = $repObj;
        $this->gcp = $gcp;
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
            $results['poll'] = $this->repObj->find($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function getPollsByType($request)
    {
        $error_messages = $results = [];
        $response = $this->repObj->getPollsByType($request);
        $results = apply_cloudfront_url($response);
        return ['error_messages' => $error_messages, 'results' => $results];
        //  return $results;
    }

    public function store($request)
    {
        $data = $request->all();
        $error_messages = $results = [];
        array_set($data, 'slug', str_slug($data['name']));
        if ($request->hasFile('cover')) {

            //upload to local drive
            $upload = $request->file('cover');
            $folder_path = 'uploads/polls/t/';
            $img_path = public_path($folder_path);
            $imageName = time() . '_' . str_slug($upload->getRealPath()) . '.' . $upload->getClientOriginalExtension();
            $fullpath = $img_path . $imageName;
            $upload->move($img_path, $imageName);
            chmod($fullpath, 0777);


            //upload to gcp

            $object_source_path = $fullpath;
            $object_upload_path = 'polls/t/' . $imageName;
            $params = ['object_source_path' => $object_source_path, 'object_upload_path' => $object_upload_path];
            $uploadToGcp = $this->gcp->localFileUpload($params);
            $thumb_url = Config::get('gcp.base_url') . Config::get('gcp.default_bucket_path') . $object_upload_path;

            $photo = ['thumb' => $thumb_url];
            array_set($data, 'photo', $photo);

            @unlink($fullpath);

        }
//       print_r($data);exit;
        if (empty($error_messages)) {
            $results['poll'] = $this->repObj->store($data);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function update($request, $id)
    {
        $data = $request->all();
        $error_messages = $results = [];
        if ($request->hasFile('cover')) {

            //upload to local drive
            $upload = $request->file('cover');
            $folder_path = 'uploads/polls/t/';
            $img_path = public_path($folder_path);
            $imageName = time() . '_' . str_slug($upload->getRealPath()) . '.' . $upload->getClientOriginalExtension();
            $fullpath = $img_path . $imageName;
            $upload->move($img_path, $imageName);
            chmod($fullpath, 0777);


            //upload to gcp

            $object_source_path = $fullpath;
            $object_upload_path = 'polls/t/' . $imageName;
            $params = ['object_source_path' => $object_source_path, 'object_upload_path' => $object_upload_path];
            $uploadToGcp = $this->gcp->localFileUpload($params);
            $thumb_url = Config::get('gcp.base_url') . Config::get('gcp.default_bucket_path') . $object_upload_path;

            $photo = ['thumb' => $thumb_url];
            array_set($data, 'photo', $photo);

            @unlink($fullpath);

        }

        if (empty($error_messages)) {
            $results['poll'] = $this->repObj->update($data, $id);
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function destroy($id)
    {
        $results = $this->repObj->destroy($id);
        return $results;
    }


    //Sibani Mishra
    public function getPollResult($requestData)
    {
        $results['pollResults'] = \App\Models\Pollresult::where('cust_id', $requestData)->get();
        return ['results' => $results];
    }
    
}