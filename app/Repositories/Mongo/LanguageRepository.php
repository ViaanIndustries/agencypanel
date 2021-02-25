<?php

namespace App\Repositories\Mongo;

/**
 * RepositoryName : Language.
 *
 *
 * @author      Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since       2019-06-25
 * @link        http://bollyfame.com/
 * @copyright   2019 BOLLYFAME
 * @license     http://bollyfame.com/license
 */

use App\Repositories\Contracts\LanguageInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Language;
use Config;
use DB;

class LanguageRepository extends AbstractRepository implements LanguageInterface
{
    protected $modelClassName = 'App\Models\Language';

    public function index($requestData, $perpage = NULL) {
        $results        = [];
        $perpage        = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
        $name           = (isset($requestData['name']) && $requestData['name'] != '')  ? $requestData['name'] : '';
        $status         = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';
        $list_order_by  = (isset($requestData['list_order_by']) && $requestData['list_order_by'] != '') ? $requestData['list_order_by'] : 'name';

        $appends_array  = array('name' => $name, 'status' => $status);

        $query          = $this->model;

        if($name != '') {
            $query->where('name', 'LIKE', '%'. $name . '%');
        }

        if($status != ''){
            $query->where('status', $status);
        }
        else {
            //$query->whereNotIn('status', ['deleted']);
            //$query->where('status', '<>', 'active');
        }

        $results['languages']       = $query->orderBy($list_order_by)->paginate($perpage);
        $results['appends_array']   = $appends_array;

        return $results;
    }


    public function list($requestData, $perpage = NULL) {
        $results = [];
        $lang_arr= [];
        $data = $this->index($requestData, $perpage);

        if($data) {
            $languages = isset($data['languages']) ? $data['languages'] : [];
            if($languages) {
                $lang_arr   = $languages->toArray();
                if($lang_arr) {
                    $results['list']    = isset($lang_arr['data']) ? $lang_arr['data'] : [];
                    $results['paginate_data']['total']          = (isset($lang_arr['total'])) ? $lang_arr['total'] : 0;
                    $results['paginate_data']['per_page']       = (isset($lang_arr['per_page'])) ? $lang_arr['per_page'] : 0;
                    $results['paginate_data']['current_page']   = (isset($lang_arr['current_page'])) ? $lang_arr['current_page'] : 0;
                    $results['paginate_data']['last_page']      = (isset($lang_arr['last_page'])) ? $lang_arr['last_page'] : 0;
                    $results['paginate_data']['from']           = (isset($lang_arr['from'])) ? $lang_arr['from'] : 0;
                    $results['paginate_data']['to']             = (isset($lang_arr['to'])) ? $lang_arr['to'] : 0;
                }
            }
        }

        return $results;
    }


    public function forceDelete($id) {
        $recodset = $this->model->findOrFail($id);
        return $recodset->delete();
    }


    /**
     * Returns Labels/Names By
     *
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-06
     */
    public function labelsBy($by = 'code_3') {
        $ret = [];

        $ret = $this->model->active()->orderBy('name')->lists('name', $by);

        return $ret;
    }


    /**
     * Return active record
     *
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-12
     */
    public function findActiveBy($key_value, $by = 'code_3') {
        $ret = $this->model->active()->where($by, $key_value)->first();

        return $ret;
    }

}
