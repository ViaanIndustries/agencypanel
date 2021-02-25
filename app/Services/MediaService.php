<?php
/**
 * Created by PhpStorm.
 * User: sibani
 * Date: 10/8/18
 * Time: 3:47 PM
 */
namespace App\Services;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;

use App\Services\Gcp;
use App\Services\Image\Kraken;

class MediaService
{
    protected $gcp;
    protected $kraken;

    public function __construct(Kraken $kraken, Gcp $gcp)
    {
        $this->kraken = $kraken;
        $this->gcp = $gcp;
    }

    public function index($requestData)
    {
        $error_messages = $results = [];

        $perpage = isset($requestData['perpage']) ? $requestData['perpage'] : 10;

        $responseData = \App\Models\Media::paginate($perpage);

        return ['error_messages' => $error_messages, 'results' => $responseData];
    }

    public function store($request)
    {

        $error_message = $requestData = [];

        if (empty($request['photo']) && $request['photo'] == '') {
            $error_message[] = 'Image file is required.';
        }

        if (empty($request['name']) && $request['name'] == '') {
            $error_message[] = 'name field is required.';
        }

        if (empty($error_message)) {
            $cover_url = '';

//            if ($request->hasFile('photo')) {
//                $upload = $request->file('photo');
//                $folder_path = 'uploads/media/c/';
//                $obj_path = public_path($folder_path);
//                $imageName = time() . '_' . str_slug($upload->getRealPath()) . '.' . $upload->getClientOriginalExtension();
//                $fullpath = $obj_path . $imageName;
//                $upload->move($obj_path, $imageName);
//                chmod($fullpath, 0777);
////              chmod($folder_path, 0777);
//
//                //upload to gcp
//                $object_source_path = $fullpath;
//                $object_upload_path = 'media/' . $upload->getClientOriginalName();
//                $params = ['object_source_path' => $object_source_path, 'object_upload_path' => $object_upload_path];
//                $uploadToGcp = $this->gcp->localFileUpload($params);
//                $cover_url = Config::get('gcp.base_url') . Config::get('gcp.default_bucket_path') . $object_upload_path;
//                @unlink($fullpath);
//            }

            //upload photo
            if ($request->hasFile('photo')) {
                $parmas     =   ['file' => $request->file('photo'), 'type' => 'media'];
                $photo      =   $this->kraken->uploadToAws($parmas);
                if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                    $photo = $photo['results'];
                    if(!empty($photo) && !empty($photo['cover'])){
                        $cover_url  =  $photo['cover'];
                    }
                }
            }

            $requestData['image'] = $cover_url;

            $requestData['name'] = $request['name'];

            $media = new \App\Models\Media($requestData);
            $media->save();
        }

        return ['error_messages' => $error_message, 'results' => null];
    }
}

