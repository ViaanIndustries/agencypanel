<?php

namespace App\Repositories\Mongo;

/**
 * RepositoryName : RewardProgram.
 *
 *
 * @author      Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since       2019-05-29
 * @link        http://bollyfame.com
 * @copyright   2019 BOLLYFAME
 * @license     http://bollyfame.com//license
 */

use App\Repositories\Contracts\RewardProgramInterface;
use App\Repositories\AbstractRepository;
use App\Models\RewardProgram;
use Config;
use DB;
use Carbon;

class RewardProgramRepository extends AbstractRepository implements RewardProgramInterface {

    protected $modelClassName = 'App\Models\RewardProgram';

    /**
     * Return RewardProgram Event List
     *
     * @param   array       $requestData
     * @param   integer     $perpage
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function index($requestData, $perpage = NULL) {
        $results        = [];
        $perpage        = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
        $name           = (isset($requestData['name']) && $requestData['name'] != '')  ? $requestData['name'] : '';
        $status         = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';

        $appends_array  = array('name' => $name, 'status' => $status);

        $query          = $this->model->orderBy('created_at');

        if($name != '') {
           $query->where('name', $name);
        }

        if($status != '') {
           $query->where('status', $status);
        }

        $results['rewardprograms']  = $query->paginate($perpage);
        $results['appends_array']   = $appends_array;

        return $results;
    }


    /**
     * Return RewardProgram Event List with pagination
     *
     * @param   array       $requestData
     * @param   integer     $perpage
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function list($requestData, $perpage = NULL) {
        $results = [];
        $data_arr= [];
        $data = $this->index($requestData, $perpage);

        if($data) {
            $rewardprograms = isset($data['rewardprograms']) ? $data['rewardprograms'] : [];
            if($rewardprograms) {
                $data_arr   = $rewardprograms->toArray();
                if($data_arr) {
                    $results['list']                            = isset($data_arr['data']) ? $data_arr['data'] : [];
                    $results['paginate_data']['total']          = (isset($data_arr['total'])) ? $data_arr['total'] : 0;
                    $results['paginate_data']['per_page']       = (isset($data_arr['per_page'])) ? $data_arr['per_page'] : 0;
                    $results['paginate_data']['current_page']   = (isset($data_arr['current_page'])) ? $data_arr['current_page'] : 0;
                    $results['paginate_data']['last_page']      = (isset($data_arr['last_page'])) ? $data_arr['last_page'] : 0;
                    $results['paginate_data']['from']           = (isset($data_arr['from'])) ? $data_arr['from'] : 0;
                    $results['paginate_data']['to']             = (isset($data_arr['to'])) ? $data_arr['to'] : 0;
                }
            }
        }

        return $results;
    }


    /**
     * Return List Query
     *
     * @param   array       $requestData
     * @param   integer     $perpage
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function listQuery($requestData) {
        $query = null;
        $platform   = (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $artist_id  = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $sort_by    = (isset($requestData['sort_by']) && $requestData['sort_by'] != '') ? $requestData['sort_by'] : '';
        $query = \App\Models\RewardProgram::where('status', '=', 'active');


        if ($platform != '') {
            $query->whereIn('platforms', [$platform]);
        }

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        if($sort_by) {
            if(is_array($sort_by)) {
                foreach ($sort_by as $key => $value) {
                    $query->orderBy($key, $value);
                }
            }
            else {

            }
        }

        return $query;
    }


    public function listQueryPagination($requestData, $perpage = '') {
        $ret = null;

        $query      = $this->listQuery($requestData);
        $results    = $query->paginate($perpage);

        if($results) {
            $ret = $results->toArray();
        }

        return $ret;
    }

    /**
     * Create/Store new record in database
     *
     * @param   array       $data
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function store($data) {
        $recodset = new $this->model($data);
        $recodset->save();
        return $recodset;
    }

    /**
     * Update existing record in database
     *
     * @param   array       $data
     * @param   string      $id
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-10
     */
    public function update($data, $id) {
        $recodset = $this->model->findOrFail($id);
        $recodset->update($data);
        return $recodset;
    }

    /**
     * Get the Priorities.
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-27
     */
    public function getPriorities() {
        return $this->model->getPriorities();
    }

    /**
     * Get the Events.
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-27
     */
    public function getEvents() {
        return $this->model->getEvents();
    }

    /**
     * Find Record By Event And Artist Id.
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-27
     */
    public function findByEventAndArtist($event, $artist_id = '') {
        $ret = null;

        $query = $this->model->where('event', $event);

        $query->where('artist_id', $artist_id);
        $query->where('status', 'active');

        $ret = $query->first();

        return $ret;
    }

}
