<?php
/**
 * Created by PhpStorm.
 * User: sibani
 * Date: 12/6/18
 * Time: 3:24 PM
 */

namespace App\Services;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;

use App\Repositories\Contracts\AuctionproductInterface;
use App\Models\Auctionproduct as Auctionproduct;
use App\Services\Gcp;

class AuctionproductService
{
    protected $repObj;
    protected $gcp;

    public function __construct(AuctionproductInterface $repObj, Gcp $gcp)
    {
        $this->repObj = $repObj;
        $this->gcp = $gcp;
    }

    public function index(Request $request)
    {
        $error_messages = $results = [];
        $requestData = $request->all();
        $results = $this->repObj->index($requestData);

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $error_messages = $results = [];
        array_set($data, 'slug', str_slug($data['name']));
        if ($request->hasFile('cover')) {

            //upload to local drive
            $upload = $request->file('cover');
            $folder_path = 'uploads/auctionproduct/t/';
            $img_path = public_path($folder_path);
            $imageName = time() . '_' . str_slug($upload->getRealPath()) . '.' . $upload->getClientOriginalExtension();
            $fullpath = $img_path . $imageName;
            $upload->move($img_path, $imageName);
            chmod($fullpath, 0777);


            //upload to gcp
            $object_source_path = $fullpath;
            $object_upload_path = 'auctionproduct/t/' . $imageName;
            $params = ['object_source_path' => $object_source_path, 'object_upload_path' => $object_upload_path];
            $uploadToGcp = $this->gcp->localFileUpload($params);
            $thumb_url = Config::get('gcp.base_url') . Config::get('gcp.default_bucket_path') . $object_upload_path;

            $photo = ['thumb' => $thumb_url];
            array_set($data, 'cover', $photo);

            @unlink($fullpath);

        }


        if (empty($error_messages)) {
            $results['auctionproduct'] = $this->repObj->store($data);
        }

        return ['error_messages' => $error_messages, 'results' => $results];

    }

    public function find($id)
    {
        $results = $this->repObj->find($id);
        return $results;
    }

    public function update($request, $id)
    {
        $data = $request->all();
        $error_messages = $results = [];

        array_set($data, 'slug', str_slug($data['name']));
        if ($request->hasFile('cover')) {

            //upload to local drive
            $upload = $request->file('cover');
            $folder_path = 'uploads/auctionproduct/t/';
            $img_path = public_path($folder_path);
            $imageName = time() . '_' . str_slug($upload->getRealPath()) . '.' . $upload->getClientOriginalExtension();
            $fullpath = $img_path . $imageName;
            $upload->move($img_path, $imageName);
            chmod($fullpath, 0777);


            //upload to gcp
            $object_source_path = $fullpath;
            $object_upload_path = 'auctionproduct/t/' . $imageName;
            $params = ['object_source_path' => $object_source_path, 'object_upload_path' => $object_upload_path];
            $uploadToGcp = $this->gcp->localFileUpload($params);
            $thumb_url = Config::get('gcp.base_url') . Config::get('gcp.default_bucket_path') . $object_upload_path;

            $photo = ['thumb' => $thumb_url];
            array_set($data, 'cover', $photo);

            @unlink($fullpath);

        }

        if (empty($error_messages)) {
            $results['package'] = $this->repObj->update($data, $id);
        }
        return ['error_messages' => $error_messages, 'results' => $results];

    }
}