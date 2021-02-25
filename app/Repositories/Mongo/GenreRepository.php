<?php

namespace App\Repositories\Mongo;

/**
 * RepositoryName : Genre.
 *
 *
 * @author      Ruchi <ruchi.sharma@bollyfame.com>
 * @since       2019-07-23
 * @link        http://bollyfame.com
 * @copyright   2019 BOLLYFAME
 * @license     http://bollyfame.com//license
 */

use App\Repositories\Contracts\GenreInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Genre;
use Config;
use DB;

class GenreRepository extends AbstractRepository implements GenreInterface
{
    protected $modelClassName = 'App\Models\Genre';

    public function index($requestData, $perpage = NULL)
    {
        $results        = [];
        $perpage        = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
        $name           = (isset($requestData['name']) && $requestData['name'] != '')  ? $requestData['name'] : '';
        $status         = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';

        $appends_array  = array('name' => $name, 'status' => $status);

        $query          = \App\Models\Genre::orderBy('name');

       if($name != '') {
            $query->where(function($q) use ($name) {
                $q->orWhere('name', 'LIKE', '%' . $name . '%');
            });
       }

       if($status != '') {
           $query->where('status', $status);
       }

        $results['genres']           = $query->paginate($perpage);
        $results['appends_array']   = $appends_array;

        return $results;
    }

    public function activelists()
    {
        $genresArr = [];
        $genres = $this->model->active()->orderBy('name')->get(['name', '_id'])->toArray();

        foreach ($genres as $genre) {
            $name   = $genre['name'];
            $id     = $genre['_id'];
            $genresArr[$id] = $name;
        }

        return $genresArr;
    }
}
