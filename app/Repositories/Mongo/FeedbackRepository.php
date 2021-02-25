<?php

namespace App\Repositories\Mongo;

use Config;
use DB;
use Carbon;

use App\Repositories\Contracts\FeedbackInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Feedback;

class FeedbackRepository extends AbstractRepository implements FeedbackInterface
{

    protected $modelClassName = 'App\Models\Feedback';

    /**
     * Return Feedback List
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
        $artist_id 		= (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $type 			= (isset($requestData['type']) && $requestData['type'] != '') ? $requestData['type'] : '';
        $platform 		= (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $status         = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';
        $entity         = (isset($requestData['entity']) && $requestData['entity'] != '') ? $requestData['entity'] : '';

        $appends_array  = [
            'artist_id' => $artist_id,
            'type'      => $type,
            'platform'  => $platform,
            'status'    => $status,
            'entity'=>$entity
        ];

        $query          = $this->model->orderBy('updated_at', 'desc');

        if ($type != '') {
            $query->where('type', $type);
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

        if ($entity != '') {
            $query->where('entity', $entity);
        }

        $results['feedbacks']      	= $query->paginate($perpage);
        $results['appends_array']   = $appends_array;

        return $results;
    }


    /**
     * Return Live Event List with pagination
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
            $feedbacks = isset($data['feedbacks']) ? $data['feedbacks'] : [];
            if($feedbacks) {
                $data_arr   = $feedbacks->toArray();
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


    public function listQuery($requestData) {
        $query = null;
        $platform   = (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $artist_id  = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $sort_by    = (isset($requestData['sort_by']) && $requestData['sort_by'] != '') ? $requestData['sort_by'] : '';
        $query = \App\Models\Feedback::with('customer');

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
                switch (strtolower($sort_by)) {
                    case 'upcoming':
                        $query->where('status', 'active');
                        $query->where('schedule_at', '>', $schedule_at_obj);
                        $query->orderBy('schedule_at');
                        break;

                    case 'past':
                        $query->where('status', 'active');
                        $query->where('schedule_at', '<', $schedule_at_obj);
                        $query->orderBy('schedule_at', 'desc');
                        break;

                    default:
                        # code...
                        break;
                }
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
}

