<?php

namespace App\Repositories\Mongo;

use Config;
use App\Repositories\Contracts\PageInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Page as Page;

class PageRepository extends AbstractRepository implements PageInterface
{

    protected $modelClassName = 'App\Models\Page';

    public function index($requestData)
    {
        $results            = [];
        $perpage            = ($requestData['perpage'] == NULL) ? Config::get('app.perpage') : intval($requestData['perpage']);
        $name               = (isset($requestData['name']) && $requestData['name'] != '')  ? $requestData['name'] : '';
        $type               = (isset($requestData['type']) && $requestData['type'] != '')  ? $requestData['type'] : '';

        $appends_array      = array('name' => $name,'type'=>$type);

        $query              = \App\Models\Page::orderBy('ordering');

        if($name != ''){
            $query->where('name', 'LIKE', '%'. $name .'%');
        }

        if($type != ''){
            $query->where('type', 'LIKE', '%'. $type .'%');
        }

        $results['pages']           = $query->paginate($perpage);
        $results['appends_array']   = $appends_array;

        //print_pretty($results);exit;
        //print_pretty($query->paginate($perpage)->toArray());exit;
        return $results;
    }

    public function updateItems($data, $id)
    {
        $page = $this->model->findOrFail($id);

        $type = $data['type'] . 's';

        array_set($data, $type, $data[$type]);

        if(isset($data['artists']) && $data['artists']) {
            $artist_collection = collect($data['artists'])->map(function ($artist) {
                return (object) $artist;
            });

            $data['artists'] = $artist_collection->values()->all();
        }

        if(isset($data['banners']) && $data['banners']) {
            $banner_collection = collect($data['banners'])->map(function ($banner) {
                return (object) $banner;
            });

            $data['banners'] = $banner_collection->values()->all();
        }

         if(isset($data['contents']) && $data['contents']) {
            $content_collection = collect($data['contents'])->map(function ($content) {
                return (object) $content;
            });

            $data['contents'] = $content_collection->values()->all();
        }

        $page->update($data);

        return $page;
    }

    public function homepage($requestData)
    {
        $results    = [];
        $responeData= [];
        $perpage    = 5;
        $platforms  = (isset($requestData['platforms']) && $requestData['platforms'] != '') ? $requestData['platforms'] : '';

        // Find distinct Artist Ids
        $artist_ids         = [];
        $artist_ids_deatils = [];
        $artist_columns     = ['first_name', 'last_name', 'identity', 'photo'];

        // Find distinct Content Ids
        $content_ids        = [];
        $content_ids_details= [];
        $content_columns    = ['name', 'caption', 'photo', 'video'];

        // Find Home Page Sections order by ORDERING
        $pages  = [];

        $page_query         = \App\Models\Page::with([
            'bucket' => function ($q) {
                $q->select('name', 'code');
            }
        ])->where('page_name', '=', 'home')->where('status', '=', 'active')->orderBy('ordering');
        if ($platforms != '') {
            $page_query->whereIn('platforms', [$platforms]);
        }

        $page_query_result = $page_query->paginate($perpage)->toArray();

        $page_sections = $page_query_result['data'];
        if($page_sections) {
            foreach ($page_sections as $key => $page_section) {
                if(isset($page_section['type'])) {
                    switch ($page_section['type']) {
                        case 'artist':
                            $artists_col = collect($page_section['artists']);
                            foreach ($artists_col as $key => $value) {
                                if(!in_array($value['artist_id'], $artist_ids)) {
                                    $artist_ids[] = $value['artist_id'];
                                }
                            }
                            break;

                        case 'content':
                            $contents_col = collect($page_section['contents']);
                            foreach ($contents_col as $key => $value) {
                                if(!in_array($value['content_id'], $content_ids)) {
                                    $content_ids[] = $value['content_id'];
                                }
                            }
                            break;
                        default:
                            # code...
                            break;
                    }
                }
            }
        }

        // If Artist Ids exits then find all artists details
        if($artist_ids) {
            $artist_ids_deatils_obj = \App\Models\Cmsuser::where('status', 'active')->whereIn('_id', $artist_ids)->orderBy('_id', 'desc')->get($artist_columns);
            if($artist_ids_deatils_obj) {
                $artist_ids_deatils = $artist_ids_deatils_obj->keyBy('_id')->toArray();
            }
        }

        // If Content Ids exits then find all content details
        if($content_ids) {
            $content_ids_deatils_obj = \App\Models\Content::where('status', 'active')->whereIn('_id', $content_ids)->orderBy('_id', 'desc')->get($content_columns);
            if($content_ids_deatils_obj) {
                $content_ids_deatils = $content_ids_deatils_obj->keyBy('_id')->toArray();
            }
        }

        // Prepare final result
        if($page_sections) {
            foreach ($page_sections as $key => $section) {
                $result = array_except($section, ['artists', 'contents']);
                if(isset($result['type'])) {
                    $artists    = [];
                    $contents   = [];
                    switch ($result['type']) {
                        case 'artist':
                            foreach ($section['artists'] as $key => $value) {
                                $artist     = isset($artist_ids_deatils[$value['artist_id']]) ? $artist_ids_deatils[$value['artist_id']] : null;
                                if($artist) {
                                    $artist['order']    = $value['order'];
                                    $artist['artist_id']= $value['artist_id'];
                                    unset($artist['_id']);
                                    $artists[]  = $artist;
                                }

                            }
                            $result['artists'] = $artists;
                            break;

                        case 'content':
                            foreach ($section['contents'] as $key => $value) {
                                $content    = isset($content_ids_deatils[$value['content_id']]) ? $content_ids_deatils[$value['content_id']] : null;
                                if($content) {
                                    $content['order']        = $value['order'];
                                    $content['content_id']   = $value['content_id'];
                                    unset($content['_id']);
                                    $contents[] = $content;
                                }
                            }
                            $result['contents'] = $contents;
                            break;
                        default:
                            # code...
                            break;
                    }
                }
                $results[] = $result;
            }
        }

        $responeData['list']                            = $results;
        $responeData['paginate_data']['total']          = (isset($page_query_result['total'])) ? $page_query_result['total'] : 0;
        $responeData['paginate_data']['per_page']       = (isset($page_query_result['per_page'])) ? $page_query_result['per_page'] : 0;
        $responeData['paginate_data']['current_page']   = (isset($page_query_result['current_page'])) ? $page_query_result['current_page'] : 0;
        $responeData['paginate_data']['last_page']      = (isset($page_query_result['last_page'])) ? $page_query_result['last_page'] : 0;
        $responeData['paginate_data']['from']           = (isset($page_query_result['from'])) ? $page_query_result['from'] : 0;
        $responeData['paginate_data']['to']             = (isset($page_query_result['to'])) ? $page_query_result['to'] : 0;
        return $responeData;
    }
}

