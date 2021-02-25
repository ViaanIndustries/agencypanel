<?php

namespace App\Services;

use Input;
use Redirect;
use Config;
use Session;
use Hash;

use App\Repositories\Contracts\CmsuserInterface;
use App\Models\Cmsuser as Cmsuser;
use App\Services\Image\Kraken;
 
class CmsuserService
{
    protected $repObj;
    protected $cmsuser;
    protected $kraken;

    public function __construct(Cmsuser $cmsuser, CmsuserInterface $repObj, Kraken $kraken )
    {
        $this->cmsuser = $cmsuser;
        $this->repObj = $repObj;
        $this->kraken = $kraken;
     }

    public function index($request)
    {

        $results = $this->repObj->index($request);
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
        $results = $this->repObj->activeLists();

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function find($id)
    {
        $results = $this->repObj->find($id);
        //dd($results);
        return $results;
    }

    public function show($id)
    {
        $error_messages = $results = [];
        if (empty($error_messages)) {
            $results['cmsuser'] = $this->repObj->find($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function store($request)
    {
        $data = $request->all();
        $error_messages = $results = [];

	if (empty($error_messages)) {
	  if ($request->hasFile('picture')) {

                $parmas = ['file' => $request->file('picture'), 'type' => 'artistprofile'];
                $photo  =   $this->kraken->uploadToAws($parmas);
                if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                    array_set($data, 'photo', $photo['results']);
                }
            }
            $featured =  isset($data['is_featured']) ? $data['is_featured'] : 'false';
            $beneficial = isset($data['is_beneficial']) ? $data['is_beneficial'] : 'false';
            $coins = isset($data['coins']) ? (int)$data['coins'] : 0;
            $is_featured = $featured === 'true' ? true : false;
            $is_beneficial= $beneficial === 'true' ? true : false;
            $data['is_featured'] = $is_featured;
            $data['is_beneficial'] = $is_beneficial;
	        $data['coins'] = $coins;

            $results['cmsuser'] = $this->repObj->store($data);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function update($request, $id)
    {
        $data = $request->all();
        $error_messages = $results = [];
 
        //------------------------------------Kraken Image Compression--------------------------------------------
        if ($request->hasFile('picture')) {

            $parmas = ['file' => $request->file('picture'), 'type' => 'artistprofile'];
            $photo  =   $this->kraken->uploadToAws($parmas);
            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                array_set($data, 'photo', $photo['results']);
            }
        }


        if ($request->hasFile('recharge_web_cover')) {
            $parmas = ['file' => $request->file('recharge_web_cover'), 'type' => 'artistrechargeprofile'];
            $photo  =   $this->kraken->uploadToAws($parmas);
            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                array_set($data, 'recharge_web_cover', $photo['results']);
            }
	}

	         $featured =  isset($data['is_featured']) ? $data['is_featured'] : 'false';
            $beneficial = isset($data['is_beneficial']) ? $data['is_beneficial'] : 'false';
            $coins = isset($data['coins']) ? (int)$data['coins'] : 0;
            $is_featured = $featured === 'true' ? true : false;
            $is_beneficial= $beneficial === 'true' ? true : false;
            $data['is_featured'] = $is_featured;
            $data['is_beneficial'] = $is_beneficial;
	         $data['coins'] = $coins;

        //------------------------------------Kraken Image Compression--------------------------------------------

        if (empty($error_messages)) {
            $results['cmsuser'] = $this->repObj->update($data, $id);

            // Update Contestant Info
            if($results['cmsuser'] && isset($results['cmsuser']['is_contestant']) && $results['cmsuser']['is_contestant'] == 'true') {
                $artist_id = $results['cmsuser']['_id'];
                $this->contestantservice->updateArtist($request, $artist_id);
            }
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

 
    public function updatePassword($request, $id)
    {
        $error_messages = $results = [];
        $data = $request->all();
        $cmsuser = \App\Models\Cmsuser::where('_id', '=', $id)->first();
        if (!empty($cmsuser) && isset($data['old_pass']) && $data['old_pass']) {
            if (!Hash::check(trim($data['old_pass']), trim($data['user_pass']))) {
                $error_messages[] = 'Invaild old password';
            }
        }

        if (empty($error_messages)) {
            $passwordData = ['password' => $data['new_pass']];
            $results = $this->repObj->changePassword($passwordData, $id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];

    }

    public function changePassword($request, $id)
    {
        $error_messages = $results = [];
        $data = $request->all();
        $cmsuser = \App\Models\Cmsuser::where('_id', '=', $id)->first();

        if (empty($error_messages)) {
            $passwordData = ['password' => $data['new_pass']];
            $results = $this->repObj->resetPassword($passwordData, $id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];

    }

}
