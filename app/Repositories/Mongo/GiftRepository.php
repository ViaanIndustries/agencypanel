<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\GiftInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Gift as Gift;
use Carbon, Log, DB, Config;
use App\Services\Jwtauth;
use Request;
use MongoDB\BSON\UTCDateTime;
use App\Services\CachingService;


class GiftRepository extends AbstractRepository implements GiftInterface
{
    protected $caching;
    protected $modelClassName = 'App\Models\Gift';

    public function __construct(Jwtauth $jwtauth, CachingService $caching)
    {
        $this->jwtauth = $jwtauth;
        $this->caching = $caching;
        parent::__construct();
    }

    public function lists($requestData)
    {
        $results = [];

        $artist_id      = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $type           = (isset($requestData['type']) && $requestData['type'] != '') ? $requestData['type'] : 'paid';
        $live_type      = (isset($requestData['live_type']) && $requestData['live_type'] != '') ? $requestData['live_type'] : 'general';
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';

        if($live_type != '' && $live_type == 'stickers'){
            $query = \App\Models\Gift::orderBy('_id', 'desc')->where('status', 'active');
        }else{
            $query = \App\Models\Gift::orderBy('coins', 'desc')->where('status', 'active');
        }


        if ($artist_id != '') {
            $query->whereIn('artists', [$artist_id]);
        }

        if ($type != '') {
            $query->where('type', $type);
        }

        if ($platform != '') {
//            var_dump($platform);
            $query->whereIn('platforms', [$platform]);
        }

        if ($live_type != '') {
            $query->where('live_type', $live_type);
        }


        $results = $query->get(['_id', 'type', 'free_limit', 'name', 'coins', 'photo', 'live_type', 'xp','platforms'])->toArray();

//        print_pretty($results);print_pretty($requestData);exit;

        return $results;
    }

    public function index($requestData)
    {
        $results = [];
        $perpage = ($requestData['perpage'] == NULL) ? Config::get('app.perpage') : intval($requestData['perpage']);
        $name = (isset($requestData['name']) && $requestData['name'] != '') ? $requestData['name'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $type = (isset($requestData['type']) && $requestData['type'] != '') ? $requestData['type'] : '';
        $live_type = (isset($requestData['live_type']) && $requestData['live_type'] != '') ? $requestData['live_type'] : '';
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        
        $bucket_type = (isset($requestData['bucket_type']) && $requestData['bucket_type'] != '') ? $requestData['bucket_type'] : '';
        $need_confirm = (isset($requestData['need_confirm']) && $requestData['need_confirm'] != '') ? $requestData['need_confirm'] : '';

        $appends_array = array('name' => $name, 'status' => $status, 'artist_id' => $artist_id, 'type' => $type, 'live_type' => $live_type, 'platforms' => [$platform], 'bucket_type'=>$bucket_type,'need_confirm'=>$need_confirm);

        $query = \App\Models\Gift::with('artists')->orderBy('coins');

        if ($name != '') {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }

        if ($status != '') {
            $query->where('status', $status);
        }

        if ($artist_id != '') {
            $query->where('artists', $artist_id);
        }

        if ($type != '') {
            $query->where('type', $type);
        }

        if ($platform != '') {
            $query->where('platforms', [$platform]);
        }

        if ($need_confirm != '') {
            $query->where('need_confirm', [$need_confirm]);
        }

        if ($bucket_type != '') {
            $query->where('bucket_type', [$bucket_type]);
        }



        if ($live_type != '') {
            $query->where('live_type', $live_type);
        }

        $results['gifts'] = $query->paginate($perpage);
        $results['appends_array'] = $appends_array;

        return $results;
    }


    public function getArtistGiftListing($requestData)
    {
        $giftArr = [];
//        $perpage = ($requestData['perpage'] == NULL) ? \Config::get('app.perpage') : intval($requestData['perpage']);
        $perpage = ($requestData['perpage'] == NULL) ? 60 : intval($requestData['perpage']);
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $type = (isset($requestData['type']) && $requestData['type'] != '') ? $requestData['type'] : 'paid';

        $query = $this->model->where('status', 'active')->orderBy('coins');

        if ($artist_id != '') {
            $query->where('artists', $artist_id);
        }

        if ($type != '') {
            $query->where('type', $type);
        }

        $giftlists = $query->paginate($perpage);
        $giftlists->getCollection()->transform(function ($giftlist, $key) use ($artist_id, $type) {

            $giftData = $giftlist;
            $giftData['consumed_coins'] = 0;
            $giftData['available'] = $giftlist['free_limit'];

//            if (Request::header('Authorization') && $type == 'free') {
//                $customer_id = $this->jwtauth->customerIdFromToken();
//                $customerartistObj = \App\Models\Customerartist::where('customer_id', $customer_id)->where('artist_id', $artist_id)->first();
//                if (isset($customerartistObj['send_free_gifts']) && count($customerartistObj['send_free_gifts']) > 0) {
//                    foreach ($customerartistObj['send_free_gifts'] as $key => $value) {
//                        $consumed_coins = ($value['id'] == $giftlist['_id']) ? $value['consumed'] : 0;
//                        $available_coins = ($value['id'] == $giftlist['_id']) ? $giftlist['free_limit'] - $value['consumed'] : $giftlist['free_limit'];
//                        $giftData['consumed_coins'] = $consumed_coins;
//                        $giftData['available'] = $available_coins;
//                    }
//                }
//            }


            return $giftData;
        });

        $data = $giftlists->toArray();
        $gifts = (isset($data['data'])) ? $data['data'] : [];

        $responeData = [];
        $responeData['list'] = $gifts;
        $responeData['paginate_data']['total'] = (isset($data['total'])) ? $data['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($data['current_page'])) ? $data['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($data['last_page'])) ? $data['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($data['from'])) ? $data['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($data['to'])) ? $data['to'] : 0;
        $responeData['quantities'] = Config::get('app.quantities');
        return $responeData;
    }

    public function store($postData)
    {
        $data = $postData;

        if (!isset($postData['coins'])) {
            array_set($data, 'coins', 0);
        }
        if (!isset($postData['xp'])) {
            array_set($data, 'xp', 0);
        }

        if (isset($postData['free_limit'])) {
            array_set($data, 'free_limit', intval($postData['free_limit']));
        }

	  if (isset($postData['comment_limit'])) {
            array_set($data, 'comment_limit', intval($postData['comment_limit']));
        }

        if (!isset($postData['status'])) {
            array_set($data, 'status', 'active');
        }

        array_set($data, 'coins', intval($data['coins']));
        array_set($data, 'xp', intval($data['xp']));
        $gift = new $this->model($data);
        $gift->save();

        $this->syncArtists($postData, $gift);
        return $gift;
    }


    //manage syncArtists
    public function syncArtists($postData, $gift)
    {
        //DB::collection('cmsusers')->whereIn('gifts', [$gift->_id])->pull('gifts', $gift->_id);

        if (!empty($postData['artists'])) {
            $artists = array_map('trim', $postData['artists']);
            $gift->artists()->sync(array());
            foreach ($artists as $key => $value) {
                $gift->artists()->attach($value);
            }
        }
    }

    public function update($postData, $id)
    {
        $data = $postData;
        if (isset($postData['free_limit'])) {
            array_set($data, 'free_limit', intval($postData['free_limit']));
        }

        array_set($data, 'coins', intval($data['coins']));
        array_set($data, 'xp', intval($data['xp']));
	if (isset($postData['comment_limit'])) {
            array_set($data, 'comment_limit', intval($postData['comment_limit']));
        }
        $gift = $this->model->findOrFail($id);

//        $array_difference = array_values(array_diff($gift->artists, $data['artists']));
//
//        foreach ($array_difference as $key => $value) {
//            $gift_details = \App\Models\Cmsuser::findOrFail($value);
//            $artistData = $gift_details->gifts;
//            $find_key_word = array_search($id, $artistData);
//            unset($artistData[$find_key_word]);
//
//            $gift_details->update($artistData);
//           $this->syncGifts($postData, $gift_details);
//        }

        $gift->update($data);
        $this->syncArtists($postData, $gift);
        $artist_data['last_updated_gifts'] = new \MongoDB\BSON\UTCDateTime(strtotime(date('Y-m-d H:i:s')) * 1000);
        $artistConfig = \App\Models\Artistconfig::whereIn('artist_id', $gift['artists'])->update($artist_data);

        $artist_id = $gift['artists'];
        foreach ($artist_id as $artist_val) {
            $cachetag_name = 'artistconfigs';
            $env_cachetag = env_cache_tag_key($cachetag_name);  //  ENV_artistconfigs
            $cachetag_key = $artist_val;                         //  ARTISTID

            $this->caching->flushTagKey($env_cachetag, $cachetag_key);
        }


        return $gift;
    }


}
