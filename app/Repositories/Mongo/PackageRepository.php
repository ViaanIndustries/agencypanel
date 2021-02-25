<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\PackageInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Package as Package;
use Carbon, Log, DB;
use App\Services\CachingService;

class PackageRepository extends AbstractRepository implements PackageInterface
{
    protected $caching;
    protected $modelClassName = 'App\Models\Package';

    public function __construct(CachingService $caching)
    {
        $this->caching = $caching;
        parent::__construct();
    }


    public function lists($requestData)
    {
        $results = [];
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? trim($requestData['platform']) : 'android';

        $query = \App\Models\Package::orderBy('coins')->where('status', 'active');

        if ($artist_id != '') {
            $query->whereIn('artists', [$artist_id]);
        }
        if ($platform != '') {
            $query->whereIn('platforms', [$platform]);
        }

        $results = $query->get(['_id', 'name', 'coins', 'price', 'xp', 'slug', 'sku']);

        return $results;
    }


    public function pyatmpackages($requestData)
    {
        $results = [];
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? trim($requestData['platform']) : 'android';

        $query = \App\Models\Package::orderBy('coins')->where('status', 'active');

        if ($artist_id != '') {
            $query->whereIn('artists', [$artist_id]);
        }
        if ($platform != '') {
            $query->whereIn('platforms', ['paytm']);
        }

        $results = $query->get(['_id', 'name', 'coins', 'price', 'xp', 'slug', 'sku']);

        return $results;
    }

    public function index($requestData)
    {

        $results = [];
        $artist_id = [];
        $perpage = ($requestData['perpage'] == NULL) ? Config::get('app.perpage') : intval($requestData['perpage']);
        $name = (isset($requestData['name']) && $requestData['name'] != '') ? $requestData['name'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';

        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $platforms = (isset($requestData['platforms']) && $requestData['platforms'] != '') ? $requestData['platforms'] : '';

        $appends_array = array('name' => $name, 'status' => $status, 'artist_id' => $artist_id, 'platforms' => $platforms);

        $query = \App\Models\Package::with('artists')->orderBy('coins');

        if ($name != '') {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }

        if ($status != '') {
            $query->where('status', $status);
        }

        if ($artist_id != '') {
            $query->whereIn('artists', [$artist_id]);
        }
        if ($platforms != '') {
            $query->where('platforms', $platforms);
        }

        $results['packages'] = $query->paginate($perpage);
        $results['appends_array'] = $appends_array;
        return $results;
    }


    public function getPackageListing($requestData)
    {

        $responseArr = [];
        $perpage = ($requestData['perpage'] == NULL) ? \Config::get('app.perpage') : intval($requestData['perpage']);
        $platform = $requestData['platform'];
        $artist_id = $requestData['artist_id'];
        $query = $this->model->with('artists')->where('status', 'active')->orderBy('coins');//->paginate($perpage)->toArray();


        if ($artist_id != '') {
            $query->whereIn('artists', [$artist_id]);
        }
        if ($platform != '') {
            $query->whereIn('platforms', [$platform]);
        }

        $data = $query->paginate($perpage)->toArray();
        $packages = (isset($data['data'])) ? $data['data'] : [];
        // print_r($packages);exit;
        foreach ($packages as $package) {
            foreach ($package['artists'] as $artist) {
                $package['artist'][] = (isset($artist)) ? array_only($artist, ['first_name', 'last_name']) : [];

            }

            $package = array_except($package, ['photo', 'slug', 'artists']);
            array_push($responseArr, $package);
        }

        //return $data;

        $responeData = [];
        $responeData['list'] = $responseArr;
        $responeData['paginate_data']['total'] = (isset($data['total'])) ? $data['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($data['current_page'])) ? $data['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($data['last_page'])) ? $data['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($data['from'])) ? $data['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($data['to'])) ? $data['to'] : 0;

        return $responeData;
    }


    public function store($postData)
    {

        $data = $postData;

        if (!isset($postData['coins'])) {
            array_set($data, 'coins', 0);
        }

        if (!isset($postData['price'])) {
            array_set($data, 'price', 0);
        }

        if (!isset($postData['xp'])) {
            array_set($data, 'xp', 0);
        }

        if (!isset($postData['status'])) {
            array_set($data, 'status', 'active');
        }

        $package = new $this->model($data);
        $package->save();
        $this->syncArtists($postData, $package);
        return $package;
    }

    //manage syncArtists
    public function syncArtists($postData, $package)
    {
        //DB::collection('cmsusers')->whereIn('packages', [$package->_id])->pull('packages', $package->_id);

        if (!empty($postData['artists'])) {
            $artists = array_map('trim', $postData['artists']);
            $package->artists()->sync(array());
            foreach ($artists as $key => $value) {
                $package->artists()->attach($value);
            }
        }
    }

    public function update($postData, $id)
    {
        $data = $postData;

        array_set($data, 'coins', intval($data['coins']));
        array_set($data, 'price', float_value($data['price']));
        array_set($data, 'xp', intval($data['xp']));

        if (isset($data['artists'])) {
            array_set($data, 'artists', $data['artists']);
        }

        $package = $this->model->findOrFail($id);
        $package->update($data);

        $this->syncArtists($postData, $package);


        $artist_data['last_updated_packages'] = new \MongoDB\BSON\UTCDateTime(strtotime(date('Y-m-d H:i:s')) * 1000);
        $artistConfig = \App\Models\Artistconfig::whereIn('artist_id', $package['artists'])->update($artist_data);

        $artist_id = $package['artists'];
        foreach ($artist_id as $artist_val) {
            $cachetag_name = 'artistconfigs';
            $env_cachetag = env_cache_tag_key($cachetag_name);  //  ENV_artistconfigs
            $cachetag_key = $artist_val;                         //  ARTISTID

            $this->caching->flushTagKey($env_cachetag, $cachetag_key);
        }

        return $package;

    }


}