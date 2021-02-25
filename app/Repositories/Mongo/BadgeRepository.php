<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\BadgeInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Badge as Badge;
use Config;

class BadgeRepository extends AbstractRepository implements BadgeInterface
{

    protected $modelClassName = 'App\Models\Badge';


    public function index($requestData, $perpage = NULL)
    {
        $results = [];
        $perpage = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
//        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $name = (isset($requestData['name']) && $requestData['name'] != '') ? $requestData['name'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';

        $appends_array = array('name' => $name, 'status' => $status);

        $query = \App\Models\Badge::orderBy('name');

//        if ($artist_id != '') {
//            $query->where('artist_id', $artist_id);
//        }

        if ($name != '') {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }

        if ($status != '') {
            $query->where('status', $status);
        }


//        $results['badges'] = $query->with('artist')->paginate($perpage);
        $results['badges'] = $query->paginate($perpage);
        $results['appends_array'] = $appends_array;

        return $results;
    }

    public function showBadgesArtistWise($requestData)
    {

        $results = [];
        $artist_id = (isset($requestData) && $requestData != '') ? $requestData : '';
        $query = \App\Models\Badge::with('artist')->orderBy('name');
        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }
        $results['badges'] = $query->get();
        return $results;
    }

    public function updateArtistWise($requestData)
    {
        $badgeData = \App\Models\Artistbadge::where('artist_id', $requestData['artist_id'])->where('badge_id', $requestData['badge_id'])->first();

        if ($badgeData) {
            $badge = $badgeData->update($requestData);
        } else {
            $badge = new \App\Models\Artistbadge($requestData);
            $badge->save();
        }
        return $badge;
    }

    public function showArtistBadgeList($artistId)
    {
        $artistBadgeList = \App\Models\Artistbadge::where('artist_id', $artistId)->get()->toArray();
        return $artistBadgeList;
    }

    public function badgesName($artistId)
    {
        $artsitBadges = \App\Models\Artistbadge::where('artist_id', $artistId)->lists("name", "badge_id")->toArray();
        $masterBadges = \App\Models\Badge::select("name")->get()->lists("name", "_id")->toArray();
        $array_diff_result = array_diff($masterBadges, $artsitBadges);
        return $array_diff_result;
    }

    public function createBadgeArtistWise($data)
    {
        if (!empty($data['artistbadges'])) {
            foreach ($data['artistbadges'] as $key => $val) {
                $badge_info = \App\Models\Badge::where("_id", $val)->first()->toArray();

                $data = array(
                    "name" => $badge_info['name'],
                    "artist_id" => $data['artist_id'],
                    "badge_id" => $badge_info['_id'],
                    "ordering" => $badge_info['ordering'],
                    "xp_per_level" => $badge_info['xp_per_level'],
                    "levels" => $badge_info['levels'],
                    "status" => $badge_info['status'],
                    "slug" => $badge_info['slug'],
                    "icon" => !empty($badge_info['icon']['thumb']) ? $badge_info['icon']['thumb'] : '',
                );

                array_set($data, 'xp_per_level', !empty($badge_info['xp_per_level']) ? $badge_info['xp_per_level'] : 1);
                array_set($data, 'levels', !empty($badge_info['levels']) ? $badge_info['levels'] : 1);
                array_set($data, 'ordering', !empty($badge_info['ordering']) ? $badge_info['ordering'] : 1);

                $badge = new \App\Models\Artistbadge($data);
                $badge->save();
            }
        }
        if (!empty($data['badge_id'])) {

            array_set($data, 'xp_per_level', !empty($data['xp_per_level']) ? $data['xp_per_level'] : 1);
            array_set($data, 'levels', !empty($data['levels']) ? $data['levels'] : 1);
            array_set($data, 'ordering', !empty($data['ordering']) ? $data['ordering'] : 1);

            $badgeData = \App\Models\Artistbadge::where('artist_id', $data['artist_id'])->where('badge_id', $data['badge_id'])->first();

            if ($badgeData) {
                $badge = $badgeData->update($data);
            }
        }

        return !empty($badge) ? $badge : '';
    }
}




