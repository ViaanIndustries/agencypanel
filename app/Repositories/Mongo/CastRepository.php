<?php

namespace App\Repositories\Mongo;

/**
 * RepositoryName : Cast.
 *
 *
 * @author      Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since       2019-05-13
 * @link        http://bollyfame.com
 * @copyright   2019 BOLLYFAME
 * @license     http://bollyfame.com//license
 */

use App\Repositories\Contracts\CastInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Cast;
use Config;
use DB;

class CastRepository extends AbstractRepository implements CastInterface
{
    protected $modelClassName = 'App\Models\Cast';

    public function index($requestData, $perpage = NULL)
    {
        $results        = [];
        $perpage        = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
        $name           = (isset($requestData['name']) && $requestData['name'] != '')  ? $requestData['name'] : '';
        $status         = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';

        $appends_array  = array('name' => $name, 'status' => $status);

        $query          = \App\Models\Cast::orderBy('first_name')->orderBy('last_name');

        if($name != '') {
            $query->where(function($q) use ($name) {
                $q->orWhere('first_name', 'LIKE',  $name . '%')->orWhere('last_name', 'LIKE', $name . '%');
            });
        }

        if($status != ''){
            $query->where('status', $status);
        }

        $results['casts']           = $query->paginate($perpage);
        $results['appends_array']   = $appends_array;

        return $results;
    }

    public function list($requestData, $perpage = NULL) {
        $results = [];
        $records_arr= [];
        $data = $this->index($requestData, $perpage);

        if($data) {
            $records = isset($data['casts']) ? $data['casts'] : [];
            if($records) {
                $records_arr   = $records->toArray();
                if($records_arr) {
                    $results['list']                            = isset($records_arr['data']) ? $records_arr['data'] : [];
                    $results['paginate_data']['total']          = (isset($records_arr['total'])) ? $records_arr['total'] : 0;
                    $results['paginate_data']['per_page']       = (isset($records_arr['per_page'])) ? $records_arr['per_page'] : 0;
                    $results['paginate_data']['current_page']   = (isset($records_arr['current_page'])) ? $records_arr['current_page'] : 0;
                    $results['paginate_data']['last_page']      = (isset($records_arr['last_page'])) ? $records_arr['last_page'] : 0;
                    $results['paginate_data']['from']           = (isset($records_arr['from'])) ? $records_arr['from'] : 0;
                    $results['paginate_data']['to']             = (isset($records_arr['to'])) ? $records_arr['to'] : 0;
                }
            }
        }

        return $results;
    }


    public function artistBucketList($artist_id)
    {
        $buckets         = $this->model->where('artist_id', $artist_id)->orderBy('ordering')->get()->pluck('name', '_id');
        return $buckets;
    }

    public function activelists()
    {
        $artistsArr = [];
        $artists = $this->model->active()->orderBy('first_name')->orderBy('last_name')->get(['first_name', 'last_name', '_id'])->toArray();

        foreach ($artists as $artist) {
            $name   = $artist['first_name'] . ' ' . $artist['last_name'];
            $id     = $artist['_id'];
            $artistsArr[$id] = $name;
        }

        return $artistsArr;
    }


    /**
     * Return List Query
     *
     * @param   array   $data
     * @param   array   $return_fields
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-22
     */
    public function listQuery($data, $return_fields = []) {
        $query = null;

        $name       = (isset($data['name']) && $data['name'] != '') ? $data['name'] : '';
        $status     = (isset($data['status']) && $data['status'] != '') ? $data['status'] : '';
        $sort_by    = (isset($data['sort_by']) && $data['sort_by'] != '') ? $data['sort_by'] : 'first_name';

        $query      = \App\Models\Cast::orderBy($sort_by);

        if($return_fields) {
            $query->select($return_fields);
        }

        if($name != '') {
            $query->where(function($q) use ($name) {
                $q->orWhere('first_name', 'LIKE',  $name . '%')->orWhere('last_name', 'LIKE', $name . '%');
            });
        }

        if($status != ''){
            $query->where('status', $status);
        }

        if($sort_by) {
            if(is_array($sort_by)) {
                foreach ($sort_by as $key => $value) {
                    $query->orderBy($key, $value);
                }
            }
            else {
                switch (strtolower($sort_by)) {
                    case 'name':
                    case 'first_name':
                        $query->orderBy('last_name');
                        break;
                    default:
                        # code...
                        break;
                }
            }
        }

        return $query;
    }


    /**
     * Return Search Result With Pagination Info
     *
     * @param   array   $data
     * @param   array   $return_fields
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-22
     */
    public function search($data, $return_fields = []) {
        $results    = [];
        $records_arr= [];
        $perpage    = (isset($data['per_page'])  && $data['per_page'])  ? $data['per_page'] : Config::get('app.perpage');
        $perpage    = intval($perpage);

        $query      = $this->listQuery($data, $return_fields);
        $records    = $query->paginate($perpage);

        if($records) {
            $records_arr   = $records->toArray();
            if($records_arr) {
                $results['list']                            = isset($records_arr['data']) ? $records_arr['data'] : [];
                $results['paginate_data']['total']          = (isset($records_arr['total'])) ? $records_arr['total'] : 0;
                $results['paginate_data']['per_page']       = (isset($records_arr['per_page'])) ? $records_arr['per_page'] : 0;
                $results['paginate_data']['current_page']   = (isset($records_arr['current_page'])) ? $records_arr['current_page'] : 0;
                $results['paginate_data']['last_page']      = (isset($records_arr['last_page'])) ? $records_arr['last_page'] : 0;
                $results['paginate_data']['from']           = (isset($records_arr['from'])) ? $records_arr['from'] : 0;
                $results['paginate_data']['to']             = (isset($records_arr['to'])) ? $records_arr['to'] : 0;
            }
        }

        return $results;
    }

}
