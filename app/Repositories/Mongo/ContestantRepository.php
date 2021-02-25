<?php

namespace App\Repositories\Mongo;

use Config;
use App\Repositories\Contracts\ContestantInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Contestant;
use App\Services\ArtistService;

class ContestantRepository extends AbstractRepository implements ContestantInterface
{

    protected $modelClassName = 'App\Models\Contestant';
    protected $artistservice;

    public function __construct(ArtistService $artistservice) {

        $this->artistservice = $artistservice;
        parent::__construct();
    }

    public function index($requestData, $perpage = NULL)
    {
        $results        = [];
        $perpage        = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
        $name           = (isset($requestData['name']) && $requestData['name'] != '')  ? $requestData['name'] : '';
        $status         = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';
        $approval_status= (isset($requestData['approval_status']) && $requestData['approval_status'] != '') ? $requestData['approval_status'] : '';

        $appends_array  = array('name' => $name, 'status' => $status, 'approval_status' => $approval_status);

        $query          = \App\Models\Contestant::orderBy('approved_at', 'desc');

        $query->orderBy('updated_at', 'desc');

        if($name != ''){
            $query->where(function($q) use ($name) {
                $q->orWhere('first_name', 'LIKE', '%' . $name . '%')->orWhere('last_name', 'LIKE', '%' . $name . '%');
            });
        }

        if($status != ''){
            $query->where('status', $status);
        }

        if($approval_status != ''){
            $query->where('approval_status', $approval_status);
        }

        $results['contestants']     = $query->paginate($perpage);
        $results['appends_array']   = $appends_array;

        return $results;
    }

    /**
     * Returns Contestant Artist Brief Details
     *
     * @param   string $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-24
     */
    public function artistBriefDetail($artist_id)
    {
        $ret = array();
        $artist_ret_att = array(
                            '_id',
                            'first_name',
                            'last_name',
                            'about_us',
                            'photo',
                            'contest_paid_content_bucket_id',
                            'stats',
                        );

        $artist = \App\Models\Cmsuser::where('_id', '=', $artist_id)->get()->first();
        if($artist) {
            $ret = array_only($artist->toArray(), $artist_ret_att);
            $ret['full_name']   = $artist->full_name;
        }

        return $ret;
    }

    /**
     * Returns Contestant Artist other contestants IDs
     *
     * @param   string $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-24
     */
    public function artistOtherContentantIds($artist_id, $limit = 5)
    {
        $ret = array();

        $artists = \App\Models\Cmsuser::where('_id', '!=', $artist_id)->where('status', 'active')->where('is_contestant', 'true')->limit($limit)->get(['_id'])->toArray();
        if($artists) {
            $ret = array_pluck($artists, '_id');
        }
        return $ret;
    }

    /**
     * Returns Contestant Artist Detail
     *
     * @param   string $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-24
     */
    public function artistDetail($artist_id, $language = 'en')
    {
        $ret = array();
        $content_ret_att = array(
                                '_id',
                                'name',
                                'slug',
                                'caption',
                                'type',
                                'commercial_type',
                                'is_album',
                                'photo',
                                'video',
                                'audio',
                                'coins',
                                'stats',
                            );
        $paidContent        = [];
        $otherContestants   = [];

        $language_ids = [];
        $default_lang_id = "";
        $requested_lang_id = "";
        $language_code2 = $language;

        $config_language_data = $this->artistservice->getConfigLanguages($artist_id);

        if($language_code2) {
            foreach ($config_language_data as $key => $lang) {
                if($lang['code_2'] == $language_code2) {
                    $requested_lang_id = $lang['_id'];
                    array_push($language_ids, $lang['_id']);
                }

                if($lang['is_default'] == true) {
                    $default_lang_id = $lang['_id'];
                    array_push($language_ids, $lang['_id']);
                }
            }
        }

        $language_ids = array_unique($language_ids);

        $artist = $this->artistBriefDetail($artist_id);
        if($artist) {
            $ret['contestant'] = $artist;
            unset($ret['contestant']['contest_paid_content_bucket_id']);

            // Get Artist Paid Content
            $paid_content_bucket_id = isset($artist['contest_paid_content_bucket_id']) ? $artist['contest_paid_content_bucket_id'] : '';
            if($paid_content_bucket_id) {
                $paidContents = \App\Models\Content::where('bucket_id', $paid_content_bucket_id)->with(['contentlanguages' => function($query) use($language_ids) { $query->whereIn('language_id', $language_ids)->project(['content_id' => 1, 'bucket_id' => 1, 'language_id' => 1, 'name' => 1, 'caption' => 1, 'slug' => 1]); }])->where('status', 'active')->orderby('ordering')->get();
                if($paidContents) {
                    foreach ($paidContents as $key => $value) {
                        $content = $value->toArray();

                        $content_language = $content['contentlanguages'];

                        foreach($content_language as $lang_data) {

                            if(in_array($requested_lang_id, $lang_data)) {

                                $content['caption'] = (isset($lang_data['caption']) && $lang_data['caption'] != '') ? trim($lang_data['caption']) : '';
                                $content['name'] = (isset($lang_data['name']) && $lang_data['name'] != '') ? trim($lang_data['name']) : '';
                                $content['slug'] = (isset($lang_data['slug']) && $lang_data['slug'] != '') ? trim($lang_data['slug']) : '';

                                continue;

                            } else {

                                if(in_array($default_lang_id, $lang_data)) {

                                    $content['caption'] = (isset($lang_data['caption']) && $lang_data['caption'] != '') ? trim($lang_data['caption']) : '';
                                    $content['name'] = (isset($lang_data['name']) && $lang_data['name'] != '') ? trim($lang_data['name']) : '';
                                    $content['slug'] = (isset($lang_data['slug']) && $lang_data['slug'] != '') ? trim($lang_data['slug']) : '';
                                }
                            }

                        }

                        unset($content['contentlanguages']);

                        $genres = [];
                        if(isset($content['genres'])) {
                            $genres = \App\Models\Genre::whereIn('_id', $content['genres'])->where('status', 'active')->get(['name'])->toArray();
                            $content['genres'] = $genres;
                        }

                        if($content['type'] == 'video' && isset($content['casts']) && !empty($content['casts'])) {
                            $content_casts = [];
                            $content_casts = \App\Models\Cast::whereIn('_id', $content['casts'])->where('status', 'active')->get(['first_name', 'last_name', 'photo'])->toArray();
                            $content['casts'] = $content_casts;
                        }

                        $paidContent[] = array_only($content, $content_ret_att);
                    }
                }
            }

            $ret['contestant']['paid_content'] = $paidContent;
        }

        return $ret;
    }

    /**
     * Returns Contestant Artist Info
     *
     * @param   string $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-24
     */
    public function artistInfo($artist_id, $other_contentants_required = true, $language = 'en')
    {
        $ret = array();
        $otherContestants   = [];

        $artist_detail = $this->artistDetail($artist_id, $language);
        if($artist_detail) {
            $ret = $artist_detail;

            // Find Other Contestant Artist Brief Info
            if($other_contentants_required) {
                $other_contentant_ids = $this->artistOtherContentantIds($artist_id);
                if($other_contentant_ids) {
                    foreach ($other_contentant_ids as $key => $other_contentant_id) {
                        $other_contentant = $this->artistDetail($other_contentant_id, $language);
                        if($other_contentant && isset($other_contentant['contestant'])) {
                            $otherContestants[] = $other_contentant['contestant'];
                        }
                    }
                }

                $ret['other_contentants'] = $otherContestants;
            }
        }

        return $ret;
    }

    /**
     * Returns Contestant Artists by name keywords
     *
     * @param   string $keyword
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-24
     */
    public function artistAutoSearch($keyword = '', $limit = 10)
    {
        $ret = array();


        if($keyword != ''){

        $user_model = \App\Models\Cmsuser::select('_id', 'first_name', 'last_name', 'picture')->where('status', 'active')->where('is_contestant', 'true');

            $user_model->where(function($q) use ($keyword) {
                $q->orWhere('first_name', 'LIKE', '%' . $keyword . '%');
                $q->orWhere('last_name', 'LIKE', '%' . $keyword . '%');
                $q->orWhere('email', 'LIKE', '%' . $keyword . '%');
            });

            $user_model->limit(intval($limit));

            $user_model->orderBy('first_name');
            $user_model->orderBy('last_name');

            $artists = $user_model->get();

            if($artists) {
                $ret = $artists->toArray();
            }
        }

        return $ret;
    }

    /**
     * Returns Contestant Artists list Sorted by given parameter
     *
     * @param   string $keyword
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-24
     */
    public function artistSortBy($sort_by = 'name', $request = null)
    {
        $ret = array();
        $perpage = (isset($request['perpage']) && $request['perpage'] != '') ? intval($request['perpage']) : Config::get('app.perpage');
        $sort_by = strtolower($sort_by);
        $keyword = '';
        $select_fields = ['_id', 'first_name', 'last_name', 'picture', 'stats', 'photo'];

        $user_model = \App\Models\Cmsuser::select($select_fields)->where('status', 'active')->where('is_contestant', 'true');

        if($keyword != ''){
            $user_model->where(function($q) use ($keyword) {
                $q->orWhere('first_name', 'LIKE', '%' . $keyword . '%');
                $q->orWhere('last_name', 'LIKE', '%' . $keyword . '%');
                $q->orWhere('email', 'LIKE', '%' . $keyword . '%');
            });
        }


        switch ($sort_by) {
            case 'hot':
            case 'stat.hot':
                $user_model->orderBy('stats.hot_likes', 'desc');
                break;
            case 'cold':
            case 'stat.cold':
                $user_model->orderBy('stats.cold_likes', 'desc');
                break;
            case 'name':
            default:
                break;
        }

        $user_model->orderBy('first_name');
        $user_model->orderBy('last_name');

        $artists = $user_model->paginate($perpage);
        if($artists) {
            $artist_arr = ($artists) ? $artists->toArray() : [];

            $responeData = [];
            $ret['list']                            = $artist_arr['data'];
            $ret['paginate_data']['total']          = (isset($artist_arr['total'])) ? $artist_arr['total'] : 0;
            $ret['paginate_data']['per_page']       = (isset($artist_arr['per_page'])) ? $artist_arr['per_page'] : 0;
            $ret['paginate_data']['current_page']   = (isset($artist_arr['current_page'])) ? $artist_arr['current_page'] : 0;
            $ret['paginate_data']['last_page']      = (isset($artist_arr['last_page'])) ? $artist_arr['last_page'] : 0;
            $ret['paginate_data']['from']           = (isset($artist_arr['from'])) ? $artist_arr['from'] : 0;
            $ret['paginate_data']['to']             = (isset($artist_arr['to'])) ? $artist_arr['to'] : 0;

        }


        return $ret;
    }


    /**
     * Returns Contestant Artist Other Contestant Artist list Sorted by given parameter
     *
     * @param   string $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-15
     */
    public function otherContentants($artist_id, $request = null, $language)
    {
        $ret = array();
        $perpage = (isset($request['perpage']) && $request['perpage'] != '') ? intval($request['perpage']) : Config::get('app.perpage');
        $sort_by = (isset($request['sort_by']) && $request['sort_by'] != '') ? strtolower($request['sort_by']) : 'name';
        $contestants = [];

        $user_model = \App\Models\Cmsuser::select('_id')->where('_id', '!=', $artist_id)->where('status', 'active')->where('is_contestant', 'true');

        switch ($sort_by) {
            case 'hot':
            case 'stat.hot':
                $user_model->orderBy('stats.hot_likes', 'desc');
                break;
            case 'cold':
            case 'stat.cold':
                $user_model->orderBy('stats.cold_likes', 'desc');
                break;
            case 'name':
            default:
                break;
        }

        $user_model->orderBy('first_name');
        $user_model->orderBy('last_name');

        $artists = $user_model->paginate($perpage);
        if($artists) {
            $artist_arr = ($artists) ? $artists->toArray() : [];
            if(isset($artist_arr['data'])) {
                foreach ($artist_arr['data'] as $key => $contestant) {
                    $contestant_detail = $this->artistDetail($contestant['_id'], $language);
                    if($contestant_detail && isset($contestant_detail['contestant'])  && $contestant_detail['contestant']) {
                        $contestants[] = $contestant_detail['contestant'];
                    }

                }
            }

            $responeData = [];
            $ret['list']                            = $contestants;
            $ret['paginate_data']['total']          = (isset($artist_arr['total'])) ? $artist_arr['total'] : 0;
            $ret['paginate_data']['per_page']       = (isset($artist_arr['per_page'])) ? $artist_arr['per_page'] : 0;
            $ret['paginate_data']['current_page']   = (isset($artist_arr['current_page'])) ? $artist_arr['current_page'] : 0;
            $ret['paginate_data']['last_page']      = (isset($artist_arr['last_page'])) ? $artist_arr['last_page'] : 0;
            $ret['paginate_data']['from']           = (isset($artist_arr['from'])) ? $artist_arr['from'] : 0;
            $ret['paginate_data']['to']             = (isset($artist_arr['to'])) ? $artist_arr['to'] : 0;

        }

        return $ret;
    }
}
