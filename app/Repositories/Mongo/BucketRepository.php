<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\BucketInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use Config;
use App\Services\CachingService;
use App\Services\ArtistService;

class BucketRepository extends AbstractRepository implements BucketInterface
{
    protected $caching;
    protected $artistservice;
    protected $modelClassName = 'App\Models\Bucket';

    public function __construct(CachingService $caching, ArtistService $artistservice)
    {
        $this->caching = $caching;
        $this->artistservice = $artistservice;
        parent::__construct();
    }

    public function listing($artist_id, $perpage = NULL)
    {
        $perpage = ($perpage == NULL) ? \Config::get('app.perpage') : intval($perpage);
        return $this->model->where('artist_id', $artist_id)->with(['bucketlanguage' => function ($query) {
            $query->where('is_default_language', true);
        }])->orderBy('ordering')->paginate($perpage);
    }


    public function rootBuckets($artist_id)
    {
        $result = $this->model->active()->where('artist_id', $artist_id)->where('parent_id', 'root')->orderBy('name')->lists('name', '_id');

        return ($result) ? $result->toArray() : [];
    }


    public function lists($requestData)
    {
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $visiblity = (isset($requestData['visiblity']) && $requestData['visiblity'] != '') ? $requestData['visiblity'] : 'customer';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : 'active';
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : 'android';
        $perpage = (isset($requestData['perpage']) && $requestData['perpage'] != '') ? intval($requestData['perpage']) : 10;
        $language_code2 = (isset($requestData['lang']) && $requestData['lang'] != '') ? $requestData['lang'] : '';

        $language_ids = [];
        $default_lang_id = "";
        $requested_lang_id = "";

        $config_language_data = $this->artistservice->getConfigLanguages($artist_id);        

        if($language_code2) {
            foreach ($config_language_data as $key => $lang) {
                if($lang['code_2'] == $language_code2) {
                    $requested_lang_id = $lang['_id'];
                    array_push($language_ids, $lang['_id']);
                }

                if($lang['is_default'] == true) {
                    $default_lang_id = $lang['_id'];
                    array_push($language_ids, $lang['_id']);
                }
            }
        }

        $language_ids = array_unique($language_ids);

        $appends_array = [
            'visiblity' => $visiblity,
            'status' => $status,
            'artist_id' => $artist_id
        ];

        $query = $this->model->with(['bucketlanguage' => function($query) use($language_ids) {
                    $query->whereIn('language_id', $language_ids)->project(['bucket_id' => 1, 'language_id' => 1, 'title' => 1, 'caption' => 1, 'slug' => 1]);
                }])->where('status', $status);

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        if ($visiblity != '') {
            $query->whereIn('visiblity', [$visiblity]);
        }

        if ($platform != '') {
            $query->whereIn('platforms', [$platform]);
        }

        $bucket = $query->orderBy('ordering')->paginate($perpage);
        

        $bucketArr = ($bucket) ? $bucket->toArray()['data'] : [];

        $bucketListArr = [];
        
        foreach ($bucketArr as $key => $bucketdata) {

            $bucket_language = $bucketdata['bucketlanguage'];

            $is_found = false;
            foreach ($bucket_language as $key => $bucket_lang) {

                if(in_array($requested_lang_id, $bucket_lang)) {

                    $bucketdata['caption'] = (isset($bucket_lang['caption']) && $bucket_lang['caption'] != '') ? trim($bucket_lang['caption']) : '';
                    $bucketdata['name'] = (isset($bucket_lang['title']) && $bucket_lang['title'] != '') ? trim($bucket_lang['title']) : '';
                    $bucketdata['slug'] = (isset($bucket_lang['slug']) && $bucket_lang['slug'] != '') ? trim($bucket_lang['slug']) : '';

                    $is_found = true;
                }
            }

            if($is_found == false) {

                foreach ($bucket_language as $key => $bucket_lang) {

                    if(in_array($default_lang_id, $bucket_lang)) {

                        $bucketdata['caption'] = (isset($bucket_lang['caption']) && $bucket_lang['caption'] != '') ? trim($bucket_lang['caption']) : '';
                        $bucketdata['name'] = (isset($bucket_lang['title']) && $bucket_lang['title'] != '') ? trim($bucket_lang['title']) : '';
                        $bucketdata['slug'] = (isset($bucket_lang['slug']) && $bucket_lang['slug'] != '') ? trim($bucket_lang['slug']) : '';
                    }
                }
            }

            unset($bucketdata['bucketlanguage']);

            array_push($bucketListArr, $bucketdata);

        }

        $responeData = [];
        $responeData['list'] = $bucketListArr;
        $responeData['paginate_data']['total'] = (isset($bucket['total'])) ? $bucket['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($bucket['per_page'])) ? $bucket['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($bucket['current_page'])) ? $bucket['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($bucket['last_page'])) ? $bucket['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($bucket['from'])) ? $bucket['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($bucket['to'])) ? $bucket['to'] : 0;

        return $responeData;
    }


    public function getArtistBucketListing($artist_id, $visiblity, $perpage = NULL)
    {

        $artist_id = trim($artist_id);
        $perpage = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
        $data = $this->model->active()->where('artist_id', $artist_id)->whereIn('visiblity', [$visiblity])->orderBy('ordering')->paginate($perpage)->toArray();

        $responeData = [];
        $responeData['list'] = (isset($data['data'])) ? $data['data'] : [];
        $responeData['paginate_data']['total'] = (isset($data['total'])) ? $data['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($data['current_page'])) ? $data['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($data['last_page'])) ? $data['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($data['from'])) ? $data['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($data['to'])) ? $data['to'] : 0;

        $artist = \App\Models\Cmsuser::find($artist_id, ['first_name', 'last_name', 'email']);
        $responeData['artist'] = $artist;

        return $responeData;
    }


    public function store($postData)
    {
        $data = array_except($postData, ['cover_image', '_token']);

        if (!isset($data['name'])) {
            array_set($data, 'name', '');
        }

        if (!isset($data['type'])) {
            array_set($data, 'type', 'photo');
        }

        if (!isset($data['ordering'])) {
            array_set($data, 'ordering', 0);
        }

        if (!isset($data['status'])) {
            array_set($data, 'status', 'active');
        }

        array_set($data, 'level', intval($data['level']));
        array_set($data, 'stats', Config::get('app.stats'));
         $recodset = new $this->model($data);
        $recodset->save();

        return $recodset;
    }


    public function update($postData, $id)
    {
        $data = $postData;
        array_set($data, 'level', intval($data['level']));
        $recodset = $this->model->findOrFail($id);



        $recodset->update($data);

        $artistConfig = \App\Models\Artistconfig::where('artist_id', $recodset['artist_id'])->first();
        if($artistConfig) {
            $artist_data['last_updated_buckets'] = new \MongoDB\BSON\UTCDateTime(strtotime(date('Y-m-d H:i:s')) * 1000);
            $artistConfig->update($artist_data);
        }
        $artist_id = $recodset['artist_id'];
        $cachetag_name = 'artistconfigs';
        $env_cachetag = env_cache_tag_key($cachetag_name);  //  ENV_artistconfigs
        $cachetag_key = $artist_id;                         //  ARTISTID
        $this->caching->flushTagKey($env_cachetag, $cachetag_key);

        return $recodset;
    }

    public function artistBucketList($artist_id)
    {
        $buckets         = $this->model->where('artist_id', $artist_id)->orderBy('ordering')->get()->pluck('name', '_id');
        return $buckets;
    }
}

