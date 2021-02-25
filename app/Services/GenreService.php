<?php

namespace App\Services;

/**
 * ServiceName : Genre.
 * Maintains a list of functions used for Genre.
 *
 * @author Ruchi <ruchi.sharma@bollyfame.com>
 * @since 2019-07-23
 * @link http://bollyfame.com/
 * @copyright 2019 BOLLYFAME
 * @license http://bollyfame.com//license/
 */

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;

use App\Repositories\Contracts\GenreInterface;
use App\Models\Genre;

use App\Services\Image\Kraken;

class GenreService
{
    protected $repObj;
    protected $genre;
    protected $kraken;


    public function __construct(Genre $genre, GenreInterface $repObj, Kraken $kraken)
    {
        $this->genre    = $genre;
        $this->repObj   = $repObj;
        $this->kraken   = $kraken;
    }


    public function index($request)
    {
        $requestData= $request->all();
        $results    = $this->repObj->index($requestData);

        return $results;
    }


    public function paginate()
    {
        $error_messages = $results = [];
        $results = $this->repObj->paginateForApi();

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function activeLists()
    {
        $error_messages = $results = [];
        $results = $this->repObj->activelists();

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function find($id)
    {
        $results = $this->repObj->find($id);
        return $results;
    }


    public function show($id)
    {
        $error_messages = $results = [];
        if(empty($error_messages)){
            $results['genre']    = $this->repObj->find($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function store($request)
    {
        $data           = $request->all();
        $error_messages = $results = [];

        if(empty($error_messages)){
            $results['genre']    = $this->repObj->store($data);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function update($request, $id)
    {
        $data           = $request->all();
        $error_messages = $results = [];

        if(empty($error_messages)){
            $results['genre']   = $this->repObj->update($data, $id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function destroy($id)
    {
        $results = $this->repObj->destroy($id);
        return $results;
    }
}
