<?php
namespace App\Services;

use Config, File, Log;

use App\Services\Gcp;

class Kraken
{



    /*



            $upload_params     =   [
            "resize"    => $resize_with_storage_path_arr,
            "wait" => true,
            "lossy" => true,
            "s3_store"  => array(
                "key"   => Config::get('s3.key'),
                "secret" => Config::get('s3.secret'),
                "bucket" => Config::get('s3.bucket'),
                "region" => Config::get('s3.region')
            )
        ];
        array_set($upload_params['convert'], 'format', !empty($convert_format) ? strtolower($convert_format) : 'jpg');
        array_set($upload_params, 'url', "https://s3-ap-south-1.amazonaws.com/apmediavideos/et/1552497478_tmpphprio0uy/1552498203/thumbnail/hls3000k_1552497478_tmpphprio0uy00001.png");
        $data = $this->url($upload_params);

        Log::info("Kraken Request Params - ");
        print_pretty($upload_params);
        Log::info("Kraken Response - ");
        print_pretty($data);
        exit;









     */







    protected $auth = array();
    protected $gcp;

    public function __construct($key = '', $secret = '', Gcp $gcp)
    {
        $this->auth = array(
            "auth" => array(

//------------------------------------prod----------------------------------------------
                "api_key" => '1c260523d876c9000c5091c807bb6348',
                "api_secret" => '1e565d437d59396d07f761b14dff8af048c2cc88',

//------------------------------------test----------------------------------------------
//                 "api_key" => Config::get('app.kraken_key', '73dbf866dbe673867134dc90204ddf96'),
//                 "api_secret" => Config::get('app.kraken_secret', 'd206555b6c07d8e3eba3807402a183578471251e')
            )
        );
        $this->gcp = $gcp;
    }

    public function url($opts = array())
    {
        // return "calling from kraken";
        $data = json_encode(array_merge($this->auth, $opts));
        $response = self::request($data, "https://api.kraken.io/v1/url");
        return $response;
    }

    public function upload($opts = array())
    {
        if (!isset($opts['file'])) {
            return array(
                "success" => false,
                "error" => "File parameter was not provided"
            );
        }

        if (preg_match("/\/\//i", $opts['file'])) {
            $opts['url'] = $opts['file'];
            unset($opts['file']);
            return $this->url($opts);
        }

        if (!file_exists($opts['file'])) {
            return array(
                "success" => false,
                "error" => "File `" . $opts['file'] . "` does not exist"
            );
        }

        if (class_exists('CURLFile')) {
            $file = new \CURLFile($opts['file']);
        } else {
            $file = '@' . $opts['file'];
        }

        unset($opts['file']);

        $data = array_merge(array(
            "file" => $file,
            "data" => json_encode(array_merge(
                $this->auth, $opts
            ))
        ));

        $response = self::request($data, "https://api.kraken.io/v1/upload");

        return $response;
    }

    public function status()
    {
        $data = array('auth' => array(
            'api_key' => $this->auth['auth']['api_key'],
            'api_secret' => $this->auth['auth']['api_secret']
        ));
        $response = self::request(json_encode($data), "https://api.kraken.io/user_status");
        return $response;
    }

    private function request($data, $url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_FAILONERROR, 0);
        $response = json_decode(curl_exec($curl), true);
        $error = curl_errno($curl);
        curl_close($curl);
        if ($error > 0) {
            throw new \RuntimeException(sprintf('cURL returned with the following error code: "%s"', $error));
        }
        return $response;
    }

    public function uploadKrakenImageToGCP($parameters = array())
    {
        $type = $parameters['type'];

        $params     =   ["wait" => true, "lossy" => true];
        $photo      =   Config::get('kraken.' . $type . '_photo');
        $resize     =   Config::get('kraken.' . $type);

        array_set($params, 'resize', $resize);

        Log::info('uploadKrakenImageToGCP parameters : ', $parameters);

        if (!empty($parameters['file'])) {

            Log::info('uploadKrakenImageToGCP file ');

            $img_path   = $parameters['file']->getRealPath();
            $format     = $parameters['file']->getClientOriginalExtension();

//==================================Upload Camera Image[Android/IOs]================================================
//            $rotate_img_result = correctImageOrientation($img_path);

//          if($exif['Orientation'] === 1) print 'rotated clockwise by 0 deg (nothing)';
//          if($exif['Orientation'] === 8) print 'rotated clockwise by 90 deg';
//          if($exif['Orientation'] === 3) print 'rotated clockwise by 180 deg';
//          if($exif['Orientation'] === 6) print 'rotated clockwise by 270 deg';
//          if($exif['Orientation'] === 2) print 'vertical flip, rotated clockwise by 0 deg';
//          if($exif['Orientation'] === 7) print 'vertical flip, rotated clockwise by 90 deg';
//          if($exif['Orientation'] === 4) print 'vertical flip, rotated clockwise by 180 deg';
//          if($exif['Orientation'] === 5) print 'vertical flip, rotated clockwise by 270 deg';

//            if (!empty($rotate_img_result['error']) && $rotate_img_result['error'] == false && !empty($rotate_img_result['filename'])) {
//                $img_path = $rotate_img_result['filename'];
//            }
//==================================Upload Camera Image[Android/IOs]================================================

            array_set($params['convert'], 'format', !empty($format) ? strtolower($format) : 'jpg');
            array_set($params, 'file', $img_path);
            $data = $this->upload($params);  //For Image File

        } else {

            Log::info('uploadKrakenImageToGCP url ');

            $img_url    = (isset($parameters['url'])) ? trim($parameters['url']) : '';
            if ($img_url != '') {
                $img_path   =   parse_url($img_url, PHP_URL_PATH);
                $format     =   strtolower(pathinfo($img_path, PATHINFO_EXTENSION));
                $img_url    =   explode("?", $img_url);

                Log::info("uploadKrakenImageToGCP img_url array - ", $img_url);

                if (isset($img_url[0])) {
                    array_set($params['convert'], 'format', !empty($format) ? strtolower($format) : 'jpg');
                    array_set($params, 'url', $img_url[0]);
                    $data = $this->url($params);  //For Image Url
                }

            }//if
        }

        if ($data && isset($data['success']) && $data['success'] == 1) {


            foreach ($data['results'] as $key => $val) {

                //upload Cover/Thumb/Medium Image to local drive

                $imageName = $val['file_name'] . ".jpg";
                @$kraked_url = file_get_contents($val['kraked_url']);

                if ($key == 'large') {
                    $folder_path = 'uploads/' . $type . '/l/';
                    $fullpath = $folder_path . $imageName;
                    //For GCP
                    $object_upload_path = $type . "/l/" . $imageName;
                }

                if ($key == 'cover') {
                    $folder_path = 'uploads/' . $type . '/c/';
                    $fullpath = $folder_path . $imageName;
                    //For GCP
                    $object_upload_path = $type . "/c/" . $imageName;
                }

                if ($key == 'thumb') {
                    $folder_path = 'uploads/' . $type . '/ct/';
                    $fullpath = $folder_path . $imageName;
                    //For GCP
                    $object_upload_path = $type . "/ct/" . $imageName;
                }

                if ($key == 'medium') {
                    $folder_path = 'uploads/' . $type . '/cm/';
                    $fullpath = $folder_path . $imageName;
                    //For GCP
                    $object_upload_path = $type . "/cm/" . $imageName;
                }

                if (!is_dir(public_path($folder_path))) {
                    File::makeDirectory($folder_path, 0777, true);
                }

                $obj_path = public_path($fullpath);

                Log::info("Upload Kraken folder_path - $folder_path");
                Log::info("Upload Kraken fullpath - $fullpath");
                Log::info("Upload Kraken obj_path - $fullpath");

                $upload = @file_put_contents($obj_path, $kraked_url);
                chmod($obj_path, 0777);

                //upload to gcp
                $object_source_path = $obj_path;
                $params = ['object_source_path' => $object_source_path, 'object_upload_path' => $object_upload_path];
                $uploadToGcp = $this->gcp->localFileUpload($params);
                @unlink($obj_path);

                $photo[$key] = Config::get('gcp.base_url') . Config::get('gcp.default_bucket_path') . $object_upload_path;
                $photo[$key . '_width'] = $val['kraked_width'];
                $photo[$key . '_height'] = $val['kraked_height'];
            }

//            if (!empty($rotate_img_result['error']) && $rotate_img_result['error'] == true && !empty($rotate_img_result['filename'])) {
//                $photo['exif_read_data'] = $rotate_img_result;
//            }

        }

        return $photo;
    }


    public function uploadKrakenImageToGCPbk($parameters = array())
    {
        $type           = $parameters['type'];
        $add_watermark  = !empty($parameters['add_watermark']) ? $parameters['add_watermark'] : '';
        $artist_id      = !empty($parameters['artist_id']) ? $parameters['artist_id'] : '';

//        $params = array(
//            "wait" => true,
//            "lossy" => true,
//        );
//
//        $photo = Config::get('kraken.' . $type . '_photo');
//
//        $resize = Config::get('kraken.' . $type);
//
//        array_set($params, 'resize', $resize);
//
//        Log::info('uploadKrakenImageToGCP parameters : ', $parameters);
//
//        if (!empty($parameters['file'])) {
//            Log::info('uploadKrakenImageToGCP file ');
//
//            $img_path = $parameters['file']->getRealPath();
//            $format = $parameters['file']->getClientOriginalExtension();
//
////==================================Upload Camera Image[Android/IOs]================================================
////            $rotate_img_result = correctImageOrientation($img_path);
//
////          if($exif['Orientation'] === 1) print 'rotated clockwise by 0 deg (nothing)';
////          if($exif['Orientation'] === 8) print 'rotated clockwise by 90 deg';
////          if($exif['Orientation'] === 3) print 'rotated clockwise by 180 deg';
////          if($exif['Orientation'] === 6) print 'rotated clockwise by 270 deg';
////          if($exif['Orientation'] === 2) print 'vertical flip, rotated clockwise by 0 deg';
////          if($exif['Orientation'] === 7) print 'vertical flip, rotated clockwise by 90 deg';
////          if($exif['Orientation'] === 4) print 'vertical flip, rotated clockwise by 180 deg';
////          if($exif['Orientation'] === 5) print 'vertical flip, rotated clockwise by 270 deg';
//
////            if (!empty($rotate_img_result['error']) && $rotate_img_result['error'] == false && !empty($rotate_img_result['filename'])) {
////                $img_path = $rotate_img_result['filename'];
////            }
////==================================Upload Camera Image[Android/IOs]================================================
//
////            array_set($params['convert'], 'format', !empty($format) ? strtolower($format) : 'jpg');
//            array_set($params['convert'], 'format', !empty($format) ? strtolower($format) : 'jpg');
//
//            array_set($params, 'file', $img_path);
//
//            $data = $this->upload($params);  //For Image File
//
//        } else {
//            Log::info('uploadKrakenImageToGCP url ');
//            $img_url = (isset($parameters['url'])) ? trim($parameters['url']) : '';
//
//            if ($img_url != '') {
//
//                $img_path = parse_url($img_url, PHP_URL_PATH);
//                $format = strtolower(pathinfo($img_path, PATHINFO_EXTENSION));
//
//                $img_url = explode("?", $img_url);
//
//                Log::info("uploadKrakenImageToGCP img_url array - ", $img_url);
//
//                if (isset($img_url[0])) {
//                    array_set($params['convert'], 'format', !empty($format) ? strtolower($format) : 'jpg');
//                    array_set($params, 'url', $img_url[0]);
//
//                    $data = $this->url($params);  //For Image Url
//                }
//            }
//        }

        $data = Array
        (
            'results' => Array
            (
                'cover' => Array
                (
                    'file_name' => 'a0.jpg',
                    'original_size' => 2612,
                    'kraked_size' => 5226,
                    'saved_bytes' => 0,
                    'kraked_url' => 'https://dl.kraken.io/api/ef/11/2f/d997a378b521de3e16be041d91/a0.jpg',
                    'original_width' => 128,
                    'original_height' => 128,
                    'kraked_width' => 128,
                    'kraked_height' => 128
                ),
                'medium' => Array
                (
                    'file_name' => 'a0.jpg',
                    'original_size' => 2612,
                    'kraked_size' => 5226,
                    'saved_bytes' => 0,
                    'kraked_url' => 'https://dl.kraken.io/api/ef/11/2f/d997a378b521de3e16be041d91/a0.jpg',
                    'original_width' => 128,
                    'original_height' => 128,
                    'kraked_width' => 128,
                    'kraked_height' => 128
                ),
                'thumb' => Array
                (
                    'file_name' => 'a0.jpg',
                    'original_size' => 2612,
                    'kraked_size' => 5226,
                    'saved_bytes' => 0,
                    'kraked_url' => 'https://dl.kraken.io/api/ef/11/2f/d997a378b521de3e16be041d91/a0.jpg',
                    'original_width' => 128,
                    'original_height' => 128,
                    'kraked_width' => 128,
                    'kraked_height' => 128
                ),
            ),
            'success' => 1
        );

        if ($data && isset($data['success']) && $data['success'] == 1) {

//-------------------------------------Original Image-----------------------------------------------------------------
//------------------------------------upload Original Image to local drive--------------------------------------------
            // if (!empty($parameters['file'])) {
            //     //File Upload to Local Drive
            //     $folder_path = public_path('uploads/contents/o/');
            //     $imageName = time() . '_' . str_slug($parameters['file']->getRealPath()) . '.' . $parameters['file']->getClientOriginalExtension();
            //     $fullpath = $folder_path . $imageName;
            //     $parameters['file']->move($folder_path, $imageName);


            //     chmod($folder_path, 0777);
            //     chmod($fullpath, 0777);

            // } else {
            //     //URL to Local Drive
            //     $imageName = $data['results']['cover']['file_name'] . ".jpg";
            //     @$kraked_url = file_get_contents($parameters['url']);

            //     $fullpath = public_path('uploads/contents/o/' . $imageName);

            //     $folder_path = public_path('uploads/contents/o/');


            //     if (!is_dir($folder_path)) {
            //         mkdir($folder_path);
            //         chmod($folder_path, 0777);
            //     }

            //     $upload = file_put_contents($fullpath, $kraked_url);

            //     chmod($fullpath, 0777);
            // }
//------------------------------------end upload Original Image to local drive-------------------------------------

//------------------------------------upload Original Image to GCP-------------------------------------

            //upload to gcp
            // $object_source_path = $fullpath;
            // $object_upload_path = "contents/o/" . $data['results']['cover']['file_name'] . ".jpg";
            // $params = ['object_source_path' => $object_source_path, 'object_upload_path' => $object_upload_path];
            // $uploadToGcp = $this->gcp->localFileUpload($params);
            // @unlink($fullpath);

//------------------------------------end upload to GCP-------------------------------------

//            $photo['original'] = Config::get('gcp.base_url') . Config::get('gcp.default_bucket_path') . $object_upload_path;
//            $photo['original_width'] = $data['results']['cover']['original_width'];
//            $photo['original_height'] = $data['results']['cover']['original_height'];

//-------------------------------------------End Original Image------------------------------------------------------------

            foreach ($data['results'] as $key => $val) {

                //upload Cover/Thumb/Medium Image to local drive

//                $imageName = $val['file_name'] . ".jpg";
                @$kraked_url = file_get_contents($val['kraked_url']);

                $imageName = '';


                //resource for water mark     -   http://programmerblog.net/watermark-images-using-php/

//                $kraken_url = !empty($val['kraked_url']) ? $val['kraked_url'] : '';
                $kraken_url = 'https://s3.eu-west-3.amazonaws.com/cnahackathon/Photo/HISAEL000001Z01_traite.jpg';
                if (!empty($add_watermark) && !empty($artist_id) && !empty($kraken_url)) {
                    $imageName = addTextWatermarkToAnImage($kraken_url, $artist_id);
                }

                if ($key == 'large') {
                    $folder_path = 'uploads/' . $type . '/l/';
                    $fullpath = $folder_path . $imageName;
                    //For GCP
                    $object_upload_path = $type . "/l/" . $imageName;
                }

                if ($key == 'cover') {
                    $folder_path = 'uploads/' . $type . '/c/';
                    $fullpath = $folder_path . $imageName;
                    //For GCP
                    $object_upload_path = $type . "/c/" . $imageName;
                }

                if ($key == 'thumb') {
                    $folder_path = 'uploads/' . $type . '/ct/';
                    $fullpath = $folder_path . $imageName;
                    //For GCP
                    $object_upload_path = $type . "/ct/" . $imageName;
                }

                if ($key == 'medium') {
                    $folder_path = 'uploads/' . $type . '/cm/';
                    $fullpath = $folder_path . $imageName;
                    //For GCP
                    $object_upload_path = $type . "/cm/" . $imageName;
                }

                if (!is_dir(public_path($folder_path))) {
//                    mkdir($folder_path);
                    File::makeDirectory($folder_path, 0777, true);

                }

                $obj_path = public_path($fullpath);

                Log::info("Upload Kraken folder_path - $folder_path");
                Log::info("Upload Kraken fullpath - $fullpath");
                Log::info("Upload Kraken obj_path - $fullpath");

//                $upload = @file_put_contents($obj_path, $kraked_url);
//                chmod($obj_path, 0777);

                //upload to gcp
                $object_source_path = $obj_path;
                $params = ['object_source_path' => $object_source_path, 'object_upload_path' => $object_upload_path];
//                print_b($params);
                $uploadToGcp = $this->gcp->localFileUpload($params);
//                @unlink($obj_path);

                $photo[$key] = Config::get('gcp.base_url') . Config::get('gcp.default_bucket_path') . $object_upload_path;
                $photo[$key . '_width'] = $val['kraked_width'];
                $photo[$key . '_height'] = $val['kraked_height'];
                print_b($photo);
            }

//            if (!empty($rotate_img_result['error']) && $rotate_img_result['error'] == true && !empty($rotate_img_result['filename'])) {
//                $photo['exif_read_data'] = $rotate_img_result;
//            }

        }
        print_b($photo);
        return $photo;
    }

}