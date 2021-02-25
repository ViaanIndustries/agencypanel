<?php

namespace App\Services;

use Input;
use Redirect;
use Config;
use Session;

use App\Repositories\Contracts\TemplateInterface;

class TemplateService
{

    protected $repObj;

    public function __construct(TemplateInterface $repObj)
    {
        $this->repObj = $repObj;
    }

    public function index($request)
    {
        $results = $this->repObj->index($request);
        return $results;
    }

    public function find($id)
    {
        $results = $this->repObj->find($id);
        return $results;
    }

    public function store($request)
    {
        $data = $request;
        array_set($data, 'slug', str_slug($data['label']));
        $results = $this->repObj->store($data);
        return $results;
    }


    public function update($request, $id)
    {
        $data = $request;
        array_set($data, 'slug', str_slug($data['label']));
        $results = $this->repObj->update($data, $id);
        return $results;
    }


    public function destroy($id)
    {
        $results = $this->repObj->destroy($id);
        return $results;
    }

}