<?php

namespace App\Services;

/**
 * ServiceName : Feedback.
 * Maintains a list of functions used for Feedback.
 *
 * @author      Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since       2019-08-08
 * @link        http://bollyfame.com
 * @copyright   2019 BOLLYFAME
 * @license     http://bollyfame.com/license
 */

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;

use App\Repositories\Contracts\FeedbackInterface;

class FeedbackService {

    protected $repObj;

    private $cache_expire_time = 600; // 10 minutes in seconds

    public function __construct(FeedbackInterface $repObj) {
        $this->repObj       = $repObj;
    }


    public function index($request) {
        $data       = $request->all();
        $results    = $this->repObj->index($data);

        return $results;
    }


    public function paginate() {
        $error_messages = $results = [];
        $results    = $this->repObj->paginateForApi();

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function list($request) {
        $error_messages = [];
        $results        = [];
        $data           = $request->all();
        $results        = $this->repObj->list($data);

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function activeLists() {
        $error_messages = $results = [];
        $results        = $this->repObj->activelists();

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function find($id) {
        $results = $this->repObj->find($id);
        return $results;
    }


    public function show($id) {
        $error_messages = $results = [];
        if(empty($error_messages)){
            $results['live']    = $this->repObj->find($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function store($request) {
        $data           = $request->all();
        $error_messages = $results = [];
        $live           = null;

        $slug = (isset($data['name'])) ? str_slug($data['name']) : '';
        array_set($data, 'slug', $slug);

        if ($request->hasFile('photo')) {
            $parmas     =   ['file' => $request->file('photo'), 'type' => 'lives'];
            $photo      =   $this->kraken->uploadToAws($parmas);
            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                array_set($data, 'photo', $photo['results']);
            }
        }

        $data = $this->sanitizedData($data);

        if(empty($error_messages)) {
            $results['live'] = $this->repObj->store($data);

            $artist_id          =   (!empty($data) && isset($data['artist_id'])) ? $data['artist_id'] : '';
            $purge_result       =   $this->cache->purgeAllArtistEventListCache(['artist_id' => $artist_id]);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function update($request, $id) {
        $data           = $request->all();
        $error_messages = $results = [];

        $data = $this->sanitizedData($data);

        $artist_id = $data['artist_id'];
        if(empty($error_messages)) {
            $results['live']   = $this->repObj->update($data, $id);

            $purge_result      = $this->cache->purgeAllArtistEventListCache(['artist_id' => $artist_id]);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function destroy($id) {
        $results = $this->repObj->forceDelete($id);
        return $results;
    }
}
