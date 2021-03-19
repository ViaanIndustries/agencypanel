<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\ArtistInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use Config;
use Session;

class ArtistRepository extends AbstractRepository implements ArtistInterface
{

    protected $modelClassName = 'App\Models\Cmsuser';

    public function index($requestData)
    {

         $results= [];
        $perpage= ($requestData['perpage'] == NULL) ? Config::get('app.perpage') : intval($requestData['perpage']);
        $name   = (isset($requestData['name']) && $requestData['name'] != '') ? $requestData['name'] : '';
        $email  = (isset($requestData['email']) && $requestData['email'] != '') ? $requestData['email'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';
        $sort = (isset($requestData['sort']) && $requestData['sort'] != '') ? $requestData['sort'] : '';
        $agency_id =Session::get('agency_id');
        $appends_array = array('name' => $name, 'email' => $email, 'status' => $status, 'sort'=>$sort);
        $query = $this->model::with('artistconfig');//->orderBy('first_name')->orderBy('last_name');
        $query->where('agency', $agency_id);

        if ($name != '') {
            $query->where(function($q) use ($name) {
                $q->orWhere('first_name', 'LIKE', '%' . $name . '%')->orWhere('last_name', 'LIKE', '%' . $name . '%');
            });
        }

        if ($email != '') {
            $query->where('email', 'LIKE', '%' . $email . '%');
        }

        if ($status != '') {
            $query->where('status', $status);
        }
	 if ($sort != '') {
            if($sort =='coins') //Most Popular
            $query->orderBy('stats.coins' , 'desc');
            if($sort =='name')
            $query->orderBy('first_name' , 'asc');
            if($sort =='followers')
            $query->orderBy('stats.followers' , 'desc');
        }

        $results['artists']         = $query->paginate($perpage);
        $results['appends_array']   = $appends_array;
          return $results;
    }

    public function paginate($perpage = NULL)
    {
        $perpage = ($perpage == NULL) ? \Config::get('app.perpage') : intval($perpage);
        $artist_role_ids = \App\Models\Role::where('slug', 'artist')->pluck('_id');
        $artist_role_ids = ($artist_role_ids) ? $artist_role_ids->toArray() : [];
        $artists = $this->model->whereIn('roles', $artist_role_ids)->with('artistconfig')->orderBy('first_name')->orderBy('last_name')->paginate($perpage);
        return $artists;
    }

    public function askToArtist($requestData)
    {
        $results = [];
        $perpage = ($requestData['perpage'] == NULL) ? Config::get('app.perpage') : intval($requestData['perpage']);

        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $customer_name = (isset($requestData['customer_name']) && $requestData['customer_name'] != '') ? $requestData['customer_name'] : '';
        $type = (isset($requestData['type']) && $requestData['type'] != '') ? $requestData['type'] : '';

        //        $created_at = (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        //            $created_at_end = (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';

        $created_at = ((isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '');
        $created_at_end = ((isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '');

        $appends_array = array(
            'customer_name' => $customer_name,
            'artist_id' => $artist_id,
            'created_at' => $created_at,
            'created_at_end' => $created_at_end,
        );

        $query = \App\Models\Asktoartist::with('artist', 'customer')->orderBy('created_at', 'desc');

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        if ($created_at != '') {
            $query->where("created_at", '>', mongodb_start_date($created_at));
        }

        if ($created_at_end != '') {
            $query->where("created_at", '<', mongodb_end_date($created_at_end));
        }

        $query->GenuineCustomers($customer_name);

        if (empty($requestData['data_report'])) {
            $results['questions'] = $query->paginate($perpage);
        } else {
            $results['questions'] = $query->get()->toArray();
        }

        $results['appends_array'] = $appends_array;

        return $results;
    }

    public function showArtistConfig($artist_id)
    {
        $artistConfig = \App\Models\Artistconfig::where('artist_id', '=', $artist_id)->first();
        return $artistConfig;
    }


    public function artistList($request)
    {
        $agency_id = $request->session()->get('agency_id');
        $artist_role_ids = \App\Models\Role::where('slug', 'artist')->pluck('_id');
        $artist_role_ids = ($artist_role_ids) ? $artist_role_ids->toArray() : [];
        $artists         = \App\Models\Cmsuser::where('agency',$agency_id)->whereIn('roles', $artist_role_ids)->get()->pluck('full_name', '_id');
        return $artists;
    }

    public function activeArtistList()
    {
        $artist_role_ids = \App\Models\Role::where('slug', 'artist')->pluck('_id');
        $artist_role_ids = ($artist_role_ids) ? $artist_role_ids->toArray() : [];
        $artists = \App\Models\Cmsuser::where('status', '=', 'active')->whereIn('roles', $artist_role_ids)->get()->pluck('full_name', '_id');
        return $artists;
    }



    public function updateArtistConfig($postdata)
    {
        $error_messages = [];
        $data = array_except($postdata, []);

        if (empty($data['18_plus_age_content_ios'])) {
            array_set($data, '18_plus_age_content_ios', 'true');
        }

        if (empty($data['18_plus_age_content_android'])) {
            array_set($data, '18_plus_age_content_android', 'true');
        }

        $artist_id = trim($postdata['artist_id']);
        $artistConfig = \App\Models\Artistconfig::where('artist_id', trim($artist_id))->first();
        if ($artistConfig) {
            $artistConfig->update($data);
        } else {
            $artistConfig = new \App\Models\Artistconfig($data);
            $artistConfig->save();
        }


        $artistConfig = \App\Models\Artistconfig::where('artist_id', trim($artist_id))->first();
        return $artistConfig;
    }


    public function activelists()
    {
        $artistsArr = [];
        $artist_role_ids = \App\Models\Role::where('slug', 'artist')->pluck('_id');
        $artist_role_ids = ($artist_role_ids) ? $artist_role_ids->toArray() : [];
        $artists = $this->model->active()->whereIn('roles', $artist_role_ids)->orderBy('first_name')->get(['first_name', 'last_name', '_id'])->toArray();

        foreach ($artists as $artist) {
            $name = $artist['first_name'] . ' ' . $artist['last_name'];
            $id = $artist['_id'];
            $obj = [$id => $name];
            array_push($artistsArr, $obj);
        }
        return $artistsArr;
    }

    public function create_activity($postdata)
    {
        foreach ($postdata as $key => $val) {
            $activityData = \App\Models\Artistactivity::where('artist_id', $val['artist_id'])->where('activity_id', $val['activity_id'])->first();

            if ($activityData) {
                $activity = $activityData->update($val);
            } else {
                $activity = new \App\Models\Artistactivity($val);
                $activity->save();
            }
        }
        return $activity;
    }

    public function getDailyStats($requestData)
    {

        $results = [];
        $perpage = 10;
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';

        $query = \App\Models\Dailystats::orderBy('stats_at', 'desc');

        $appends_array = array(
            'artist_id' => $artist_id,
            'status' => $status
        );

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }
        if ($status != '') {
            $query->where('status', $status);
        }

        if (empty($requestData['dailystats_download'])) {
            $results['dailystats'] = $query->paginate($perpage);
        } else {
            $results['dailystats'] = $query->get()->toArray();
        }

        $results['appends_array'] = $appends_array;

        return $results;
    }

    public function storeDailyStats($postData)
    {
        $dailystats = new \App\Models\Dailystats($postData);
        $dailystats->save();
        return $dailystats;
    }

    public function updateDailyStats($data, $id)
    {
        $dailystats = \App\Models\Dailystats::findOrFail($id);
        $dailystats->update($data);
        return $dailystats;
    }

    public function assignChannel($customer_id, $artist_id)
    {

        if ($customer_id != '' && $artist_id != '') {

            $artistconfig = \App\Models\Artistconfig::where('artist_id', $artist_id)->first();

            if ($artistconfig) {

                $comments_channel_limit = 10000;

                $gifts_channel_limit = '';

                $channel_namespace = (isset($artistconfig['channel_namespace']) && $artistconfig['channel_namespace'] != '') ? trim(strtolower($artistconfig['channel_namespace'])) : '';

                $last_assigned_comments_channelno = (isset($artistconfig['last_assigned_comments_channelno']) && $artistconfig['last_assigned_comments_channelno'] != '') ? intval($artistconfig['last_assigned_comments_channelno']) : 1;

                $last_assigned_gifts_channelno = (isset($artistconfig['last_assigned_gifts_channelno']) && $artistconfig['last_assigned_gifts_channelno'] != '') ? intval($artistconfig['last_assigned_gifts_channelno']) : 1;

                if ($channel_namespace != '') {

                    $total_comment_channel_no = \App\Models\Customerartist::where('artist_id', $artist_id)->where('comment_channel_no', $last_assigned_comments_channelno)->count();

                    if ($total_comment_channel_no < $comments_channel_limit) {
                        $comment_channel_no = $last_assigned_comments_channelno;
                    } else {
                        $comment_channel_no = $last_assigned_comments_channelno + 1;
                        $assigned_comments['last_assigned_comments_channelno'] = $comment_channel_no;
                        $artistconfig->update($assigned_comments);
                    }

                    $artistconfig->push('comment_channel_no', intval($comment_channel_no), true);
                    $comments_name['comment_channel_no'] = intval($comment_channel_no);
                    $update_comment_channel_no = \App\Models\Customerartist::where('customer_id', $customer_id)->where('artist_id', $artist_id)->update($comments_name);
                }
            }//$Artistconfig
        }
    }

    public function artistChannelNamespace($requestData)
    {

        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';

        $appends_array = array('artist_id' => $artist_id);

        if (!empty($artist_id)) {
            $artists = \App\Models\Cmsuser::where('status', '=', 'active')->where('_id', $artist_id)->get(['first_name', 'last_name', '_id'])->toArray();
            $artist_ids = array($artist_id);
        } else {
            $artist_role_ids = \App\Models\Role::where('slug', 'artist')->pluck('_id');
            $artist_role_ids = ($artist_role_ids) ? $artist_role_ids->toArray() : [];
            $artists = \App\Models\Cmsuser::where('status', '=', 'active')->whereIn('roles', $artist_role_ids)->get(['first_name', 'last_name', '_id'])->toArray();
            $artist_ids = array_pluck($artists, '_id');
        }

        $comment_channel_no = \App\Models\Artistconfig::whereIn("artist_id", $artist_ids)
            ->project(['_id' => 0])// Removing _id
            ->get(['artist_id', 'comment_channel_no'])
            ->toArray();

        foreach ($comment_channel_no as $key => $val) {
            if(isset($val['comment_channel_no'])) {
            foreach ($val['comment_channel_no'] as $subKey => $subval) {
                $res[$key]['artist_id'] = $val['artist_id'];
                $res[$key]['info'][$subKey]['Comment_channel_no'] = $subval;
                $res[$key]['info'][$subKey]['total_customers'] = \App\Models\Customerartist::where('comment_channel_no', $subval)->where('artist_id', $val['artist_id'])->count();
            }
            }
        }
        $results['channel_reports'] = !empty($res) ? $res : [];
        $results['appends_array'] = $appends_array;

        return $results;


    }

    public function artistInfoRechargeWise($requestData)
    {

        $artist_info = \App\Models\Cmsuser::where('recharge_web_status', 'active')
            ->where('status', 'active')
            ->get(['first_name', 'last_name', 'email', 'photo', 'about_us', 'recharge_web_status', 'last_visited', 'recharge_web_cover']);

        return $artist_info;

    }

    public function packageArtistWise($requestData)
    {
        $artist_id = $requestData['artist_id'];
        $platform = $requestData['platform'];

        $package_info = \App\Models\Package::where('status', '=', 'active')->whereIn('artists', [$artist_id])->whereIn('platforms', [$platform])->orderBy('coins')->get();
        $artist_info = \App\Models\Cmsuser::where('_id', $artist_id)->first(['first_name', 'last_name', 'email', 'photo', 'recharge_web_cover', 'about_us', 'recharge_web_status', 'last_visited']);

        $result['package'] = $package_info;
        $result['artist'] = $artist_info;

        return $result;
    }


    /**
     * Returns Contestant Artist Brief Details
     *
     * @param   string $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-24
     */
    public function bio($artist_id)
    {

        $ret = array();
        $artist_config_data = [];
        $artist_ret_att = array(
                            '_id',
                            'first_name',
                            'last_name',
                            'about_us',
                            'photo',
                            'contest_paid_content_bucket_id',
                        );

        $artist_config_ret_att = array(
                                    '_id',
                                    'fb_page_url',
                                    'instagram_page_url',
                                    'twitter_page_url',
                                    'android_app_download_link',
                                    'direct_app_download_link',
                                    'ios_app_download_link',
                                );

        $artist = \App\Models\Cmsuser::where('_id', '=', $artist_id)->get()->first();

        if($artist) {
            $ret['artist'] = array_only($artist->toArray(), $artist_ret_att);
            $ret['artist']['full_name']   = $artist->full_name;

            $artist_config = \App\Models\Artistconfig::where('artist_id', '=', $artist_id)->first();

            if($artist_config) {
                $artist_config_data = array_only($artist_config->toArray(), $artist_config_ret_att);
            }

            $ret['artist']['config'] = $artist_config_data;
        }


        return $ret;
    }

    public function getArtistQuery()
    {
        $artistsArr = [];
        $artist_role_ids = \App\Models\Role::where('slug', 'artist')->pluck('_id');
        $artist_role_ids = ($artist_role_ids) ? $artist_role_ids->toArray() : [];
        $agency_id = Session::get('agency_id');
        $query = $this->model->whereIn('roles', $artist_role_ids)->where('agency',$agency_id);
        return $query;
    }
}
