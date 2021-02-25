<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\CampaignInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use Config;
use App\Services\Jwtauth;


class CampaignRepository extends AbstractRepository implements CampaignInterface
{

    protected $modelClassName = "App\Models\Campaign";


    public function __construct(Jwtauth $jwtauth)
    {
        $this->jwtauth = $jwtauth;
    }


    public function paginate($perpage = NULL)
    {
        $perpage = ($perpage == NULL) ? \Config::get('app.perpage') : intval($perpage);
        return $this->model->with('roles')->orderBy('id', 'desc')->paginate($perpage);
    }


    public function store($postData)
    {
        $error_messages = array();
        $data = array_except($postData, ['']);

        $user = new $this->model($data);
        $user->save();
        $this->syncRoles($postData, $user);
        return $user;
    }


    public function update($postData, $id)
    {
        $error_messages = array();
        $data = array_except($postData, []);
        $user = $this->model->findOrFail(trim($id));
        $user->update($data);
        return $user;
    }


    public function sndCustomNotificationToCustomerByArtist($postData)
    {
        $error_messages = [];
        $data       = array_except($postData, []);
        $campaign   = new \App\Models\Campaign($data);
        $campaign->save();

        return $campaign;
    }


}