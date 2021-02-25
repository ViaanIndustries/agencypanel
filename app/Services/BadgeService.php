<?php

namespace App\Services;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;

use App\Repositories\Contracts\BadgeInterface;
use App\Models\Badge as Badge;
use App\Services\Gcp;


class BadgeService
{
    protected $repObj;
    protected $badge;

    public function __construct(Badge $badge, BadgeInterface $repObj, Gcp $gcp)
    {
        $this->badge = $badge;
        $this->repObj = $repObj;
        $this->gcp = $gcp;
    }


    public function index($request)
    {
        $error_messages = $results = [];
        $requestData = $request->all();
        $results = $this->repObj->index($requestData);

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function showBadgesArtistWise($request)
    {
        $error_messages = $results = [];
        $results = $this->repObj->showBadgesArtistWise($request);

        return ['error_messages' => $error_messages, 'results' => $results];
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
        if (empty($error_messages)) {
            $results['badge'] = $this->repObj->find($id);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function store($request)
    {
        $data = $request->all();
        $error_messages = $results = [];

        array_set($data, 'slug', str_slug($data['name']));
        array_set($data, 'xp_per_level', !empty($data['xp_per_level']) ? $data['xp_per_level'] : 1);
        array_set($data, 'levels', !empty($data['levels']) ? $data['levels'] : 1);
        array_set($data, 'ordering', !empty($data['ordering']) ? $data['ordering'] : 1);

        if ($request->hasFile('icon')) {
            //upload to local drive
            $upload = $request->file('icon');
            $folder_path = 'uploads/badges/t/';
            $img_path = public_path($folder_path);
            $imageName = time() . '_' . str_slug($upload->getRealPath()) . '.' . $upload->getClientOriginalExtension();
            $fullpath = $img_path . $imageName;
            $upload->move($img_path, $imageName);
            chmod($fullpath, 0777);

            //upload to gcp
            $object_source_path = $fullpath;
            $object_upload_path = 'badges/t/' . $imageName;
            $params = ['object_source_path' => $object_source_path, 'object_upload_path' => $object_upload_path];
            $uploadToGcp = $this->gcp->localFileUpload($params);
            $thumb_url = Config::get('gcp.base_url') . Config::get('gcp.default_bucket_path') . $object_upload_path;

            $photo = ['thumb' => $thumb_url];
            array_set($data, 'icon', $photo);

            @unlink($fullpath);
        }

        if (empty($error_messages)) {
            $results['badge'] = $this->repObj->store($data);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function update($request, $id)
    {
        $data = $request->all();
        $error_messages = $results = [];
        $slug = str_slug($data['name']);
        array_set($data, 'slug', $slug);
        array_set($data, 'xp_per_level', !empty($data['xp_per_level']) ? $data['xp_per_level'] : 1);
        array_set($data, 'levels', !empty($data['levels']) ? $data['levels'] : 1);
        array_set($data, 'ordering', !empty($data['ordering']) ? $data['ordering'] : 1);

        if ($request->hasFile('icon')) {
            //upload to local drive
            $upload = $request->file('icon');
            $folder_path = 'uploads/badges/t/';
            $img_path = public_path($folder_path);
            $imageName = time() . '_' . str_slug($upload->getRealPath()) . '.' . $upload->getClientOriginalExtension();
            $fullpath = $img_path . $imageName;
            $upload->move($img_path, $imageName);
            chmod($fullpath, 0777);

            //upload to gcp
            $object_source_path = $fullpath;
            $object_upload_path = 'badges/t/' . $imageName;
            $params = ['object_source_path' => $object_source_path, 'object_upload_path' => $object_upload_path];
            $uploadToGcp = $this->gcp->localFileUpload($params);
            $thumb_url = Config::get('gcp.base_url') . Config::get('gcp.default_bucket_path') . $object_upload_path;

            $photo = ['thumb' => $thumb_url];
            array_set($data, 'icon', $photo);

            @unlink($fullpath);

        }

        $category_count = $this->repObj->checkUniqueOnUpdate($id, 'slug', $slug);
        if ($category_count > 0) {
            $error_messages[] = 'Badge with name already exist : ' . str_replace("-", " ", ucwords($slug));
        }

        if (empty($error_messages)) {
            $results['badge'] = $this->repObj->update($data, $id);
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function destroy($id)
    {
        $results = $this->repObj->destroy($id);
        return $results;
    }

    public function updateBadgeArtistWise($request)
    {
        $data = array_except($request->all(), ['_token', '_method']);
        array_set($data, 'xp_per_level', !empty($data['xp_per_level']) ? $data['xp_per_level'] : 1);
        array_set($data, 'levels', !empty($data['levels']) ? $data['levels'] : 1);
        array_set($data, 'ordering', !empty($data['ordering']) ? $data['ordering'] : 1);
        if ($request->hasFile('icon')) {
            //upload to local drive
            $upload = $request->file('icon');
            $folder_path = 'uploads/badges/t/';
            $img_path = public_path($folder_path);
            $imageName = time() . '_' . str_slug($upload->getRealPath()) . '.' . $upload->getClientOriginalExtension();
            $fullpath = $img_path . $imageName;
            $upload->move($img_path, $imageName);
            chmod($fullpath, 0777);

            //upload to gcp
            $object_source_path = $fullpath;
            $object_upload_path = 'badges/t/' . $imageName;
            $params = ['object_source_path' => $object_source_path, 'object_upload_path' => $object_upload_path];
            $uploadToGcp = $this->gcp->localFileUpload($params);
            $thumb_url = Config::get('gcp.base_url') . Config::get('gcp.default_bucket_path') . $object_upload_path;

            $photo = ['thumb' => $thumb_url];
            array_set($data, 'icon', $photo);

            @unlink($fullpath);

        }
        $results = $this->repObj->updateArtistWise($data);
        return $results;
    }

    public function showListArtistWise($artist_id)
    {
        $badgeData = \App\Models\Artistbadge::where('artist_id', $artist_id)->get()->toArray();
        return $badgeData;
    }

    public function result($masterBadgesData, $badgesDataArtistWise)
    {

        $badgeArr = [];
        foreach ($masterBadgesData as $key => $value) {
            $badge_id = $value['_id'];
            $master_badge = head(array_where($badgesDataArtistWise, function ($badge_key, $badge_val) use ($badge_id) {
                if ($badge_val['badge_id'] == $badge_id) {
                    return $badge_val;
                }
            }));

            if (!empty($master_badge['icon'])) {
                $icon = $master_badge['icon']['thumb'];
            } elseif (!empty($value['icon'])) {
                $icon = $value['icon']['thumb'];
            } else {
                $icon = '';
            }

            array_set($value, 'artist_id', (!empty($master_badge['artist_id'])) ? $master_badge['artist_id'] : $value['artist_id']);
            array_set($value, 'name', (!empty($master_badge['name'])) ? $master_badge['name'] : $value['name']);
            array_set($value, 'ordering', (!empty($master_badge['ordering'])) ? $master_badge['ordering'] : $value['ordering']);
            array_set($value, 'xp_per_level', (!empty($master_badge['xp_per_level'])) ? $master_badge['xp_per_level'] : $value['xp_per_level']);
            array_set($value, 'levels', (!empty($master_badge['levels'])) ? $master_badge['levels'] : $value['levels']);
            array_set($value, 'status', (!empty($master_badge['status'])) ? $master_badge['status'] : $value['status']);
//            array_set($value, 'icon', (!empty($master_badge['icon']['thumb'])) ? $master_badge['icon']['thumb'] : $value['icon']['thumb']);
            array_set($value, 'icon', $icon);

            array_push($badgeArr, $value);
        }

        return (!empty($badgeArr)) ? $badgeArr : $masterBadgesData;
    }

    public function showListBadgeWise($artistId, $badgeId)
    {
        $badgeData = \App\Models\Artistbadge::where('artist_id', $artistId)->where('badge_id', $badgeId)->first();
        return $badgeData;
    }

    public function showArtistBadgeList($artistId)
    {
        $results = $this->repObj->showArtistBadgeList($artistId);
        return $results;
    }

    public function badgesName($artistId)
    {
        $results = $this->repObj->badgesName($artistId);
        return $results;
    }

    public function createBadgeArtistWise($request)
    {
        $data = array_except($request->all(), ['_token']);

        $results = $this->repObj->createBadgeArtistWise($data);
        return $results;

    }

    public function fanclassifications($artistId)
    {

        $result = \App\Models\Artistbadge::where('artist_id', $artistId)->orderBy('ordering', 'asc')->get()->toArray();

        foreach ($result as $key => $val) {
            $res[$key]['id'] = $val['_id'];
            $res[$key]['icon'] = $val['icon'];
            $res[$key]['name'] = $val['name'];
            $res[$key]['xp_per_level'] = $val['xp_per_level'];
            $res[$key]['levels'] = $val['levels'];
            $res[$key]['ordering'] = $val['ordering'];

            for ($i = 0; $i < $val['levels']; ++$i) {
                $res[$key]['range'][$i]['level'] = $i+1;
                $res[$key]['range'][$i]['start'] = 1 + ($i * $val['xp_per_level']);
                $res[$key]['range'][$i]['end'] = $val['xp_per_level'] * ($i + 1);
            }
        }

        return !empty($res) ? $res : [];
    }
}