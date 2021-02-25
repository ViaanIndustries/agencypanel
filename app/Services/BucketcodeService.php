<?php 

namespace App\Services;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;

use App\Repositories\Contracts\BucketcodeInterface;
use App\Models\Bucketcode as Bucketcode;
use \App\Services\AwsCloudfront;

class BucketcodeService
{
    protected $repObj;
    protected $bucketcode;
    protected $awscloudfrontService;

    public function __construct(Bucketcode $bucketcode, BucketcodeInterface $repObj)
    {
        $this->bucketcode   = $bucketcode;
        $this->repObj       = $repObj;
    }


    public function index($request)
    {
        $requestData = $request->all();
//        return $request;
        $results = $this->repObj->index($requestData);
        return $results;
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
            $results['bucketcode']    =   $this->repObj->find($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function store($request)
    {
        $data               =   $request->all();
        $error_messages     =   $results = [];

        array_set($data, 'slug', str_slug($data['name']));
        if(empty($error_messages)){
            $results['bucketcode']    =   $this->repObj->store($data);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function update($request, $id)
    { 
        $data               =   $request->all();
        $error_messages     =   $results = [];
        $slug               =   str_slug($data['name']);
        array_set($data, 'slug', $slug);
        $category_count = $this->repObj->checkUniqueOnUpdate($id, 'slug', $slug);
        if ($category_count > 0) {
            $error_messages[] = 'Bucketcode with name already exist : ' . str_replace("-", " ", ucwords($slug));
        }

        if(empty($error_messages)){
            $results['bucketcode']   = $this->repObj->update($data, $id);
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function destroy($id)
    {
        $results = $this->repObj->destroy($id);
        return $results;
    }


}