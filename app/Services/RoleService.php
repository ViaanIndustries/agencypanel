<?php 

namespace App\Services;

use Input;
use Redirect;
use Config;
use Session;
use App\Repositories\Contracts\RoleInterface;
use App\Models\Role as Role;


class RoleService
{
    protected $repObj;
    protected $role;

    public function __construct(Role $role, RoleInterface $repObj)
    {
        $this->role = $role;
        $this->repObj = $repObj;
    }


    public function index($request)
    {
        $results = $this->repObj->index($request);
        return $results;
    }



    public function paginate()
    {
        $error_messages     =   $results = [];
        $results = $this->repObj->paginateForApi();

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function activeLists()
    {
        $error_messages     =   $results = [];
        $results = $this->repObj->activeLists();
         return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function find($id)
    {
        $results = $this->repObj->find($id);
        return $results;
    }


    public function show($id)
    {
        $error_messages     =   $results = [];
        if(empty($error_messages)){
            $results['role']    =   $this->repObj->find($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function store($request)
    {
        $data               =   $request->all();
        $error_messages     =   $results = [];

        array_set($data, 'slug', str_slug($data['name']));
        if(empty($error_messages)){
            $results['role']    =   $this->repObj->store($data);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function update($request, $id)
    { 
        $data               =   $request->all();
        $error_messages     =   $results = [];
        $slug               =   str_slug($data['name']);
        array_set($data, 'slug', $slug);
        $category_count = $this->repObj->checkUniqueOnUpdate($id, 'slug', $slug);
        if ($category_count > 0) {
            $error_messages[] = 'Role with name already exist : ' . str_replace("-", " ", ucwords($slug));
        }

        if(empty($error_messages)){
            $results['role']   = $this->repObj->update($data, $id);
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function destroy($id)
    {
        $results = $this->repObj->destroy($id);
        return $results;
    }


}