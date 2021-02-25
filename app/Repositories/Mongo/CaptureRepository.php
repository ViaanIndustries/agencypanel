<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\CaptureInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Capture as Capture;

class CaptureRepository extends AbstractRepository implements CaptureInterface
{

    protected $modelClassName = 'App\Models\Capture';


    public function index($requestData)
    {
        $results = [];
        $perpage = ($requestData['perpage'] == NULL) ? Config::get('app.perpage') : intval($requestData['perpage']);
        $name = (isset($requestData['name']) && $requestData['name'] != '') ? $requestData['name'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $email = (isset($requestData['email']) && $requestData['email'] != '') ? $requestData['email'] : '';
        $capture_type = (isset($requestData['capture_type']) && $requestData['capture_type'] != '') ? $requestData['capture_type'] : '';
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';

        $appends_array = array(
            'name' => $name,
            'artist_id' => $artist_id,
            'email' => $email,
            'capture_type' => $capture_type,
            'platform' => $platform,
            'status' => $status
        );

        $query = \App\Models\Capture::with('artist')->orderBy('created_at', 'desc');

        if ($name != '') {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }

        if ($email != '') {
            $query->where('email', 'LIKE', '%' . $email . '%');
        }

        if ($capture_type != '') {
            $query->where('capture_type', $capture_type);
        }

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        if ($platform != '') {
            $query->where('platform', $platform);
        }
        
        if ($status != '') {
            $query->where('status', $status);
        }

        $results['captures'] = $query->paginate($perpage);
        $results['appends_array'] = $appends_array;
        return $results;
    }
}

