<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;
use Storage;


use App\Http\Controllers\Controller;
use App\Http\Requests\PageRequest;
use App\Services\PageService;
use App\Services\ArtistService;
use App\Services\BucketService;
use App\Services\ContentService;


class PageController extends Controller
{

    protected $captureservice;
    protected $artistservice;

    public function __construct(PageService $pageservice,ArtistService $artistservice, BucketService $bucketservice, ContentService $contentservice)
    {
        $this->pageservice      = $pageservice;
        $this->artistservice    = $artistservice;
        $this->bucketservice    = $bucketservice;
        $this->contentservice   = $contentservice;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $viewdata                   = [];
        $request['perpage']         = 10;
        $responseData               = $this->pageservice->index($request);
        $artists                    = $this->artistservice->artistList();
        $viewdata['section_types']  = $this->pageservice->getSetionTypes();
        $viewdata['artists']        = (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['pages']          = $responseData['pages'];
        $viewdata['appends_array']  = $responseData['appends_array'];
        return view('admin.pages.index', $viewdata);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $viewdata                   = [];
        $buckets                    = $this->bucketservice->artistBucketList(Config::get('app.HOME_PAGE_ARTIST_ID'));
        $viewdata['buckets']        = (isset($buckets['results'])) ? $buckets['results'] : [];
        $viewdata['section_types']  = $this->pageservice->getSetionTypes();
        $viewdata['platforms']      = array_except(Config::get('app.platforms'), ['paytm']) ;

        return view('admin.pages.create',$viewdata);
    }



    /**
     * Store a newly created resource in storage.
     * @param  RoleRequest  $request
     * @return Response
     */
    public function store(PageRequest $request)
    {
        $response = $this->pageservice->store($request);
        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }
        Session::flash('message','page added succesfully');
        return Redirect::route('admin.pages.index');

    }



    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        $viewdata                   = [];
        $page                       = $this->pageservice->find($id);
        $buckets                    = $this->bucketservice->artistBucketList(Config::get('app.HOME_PAGE_ARTIST_ID'));
        $viewdata['buckets']        = (isset($buckets['results'])) ? $buckets['results'] : [];
        $viewdata['section_types']  = $this->pageservice->getSetionTypes();
        $viewdata['platforms']      = Config::get('app.platforms');
        $viewdata['page']           = $page;

        return view('admin.pages.edit', $viewdata);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  RoleRequest  $request
     * @param  int  $id
     * @return Response
     */
    public function update(PageRequest $request, $id)
    {
        // return $request;
        $response = $this->pageservice->update($request, $id);
        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }

        Session::flash('message','page updated succesfully');
        return Redirect::route('admin.pages.index');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $blog = $this->captureservice->destroy($id);
        Session::flash('message','capture deleted succesfully');
        return Redirect::route('admin.captures.index');
    }

    /**
     * Mange the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function manage(Request $request, $id)
    {
        $bucket_id                  = '';

        $viewdata                   = [];
        $page                       = $this->pageservice->find($id);
        if($page) {
            $bucket_id              = (isset($page['bucket_id']) && $page['bucket_id']) ? $page['bucket_id'] : '';
        }

        $buckets                    = $this->bucketservice->artistBucketList('59858df7af21a2d01f54bde2');
        $viewdata['buckets']        = (isset($buckets['results'])) ? $buckets['results'] : [];
        $viewdata['section_types']  = $this->pageservice->getSetionTypes();
        $artists                    = $this->artistservice->artistList();
        $viewdata['artists']        = (isset($artists['results'])) ? $artists['results'] : [];
        $contents                   = $this->pageservice->getBucketContents($bucket_id);
        $viewdata['contents']       = (isset($contents['results'])) ? $contents['results'] : [];
        $viewdata['appends_array']  = [];
        $viewdata['banner_types']   = ['' => 'Select', 'webview' => 'Webview', 'photo' => 'Photo', 'video' => 'Video'];
	 $viewdata['platforms']      = Config::get('app.platforms');
        $viewdata['page']           = $page;
    //    dd($viewdata);
        return view('admin.pages.manage', $viewdata);

    }

    /**
     * Add Items the specified resource in storage.
     *
     * @param  RoleRequest  $request
     * @param  int  $id
     * @return Response
     */
    public function additems(Request $request, $id)
    {
        $requestData= array_except($request->all(),['_token']);
        $page       = $this->pageservice->find($id);

        $type = isset($requestData['type']) ? $requestData['type'] : '';

        if($type) {
            switch ($type) {
                case 'artist':
                    $pagedata   = array_except($page, array('artists'));
                    uasort($requestData['artists'], 'sort_by_order');
                    if($requestData['artists']) {
                        foreach ($requestData['artists'] as $key => $artist) {
                            $requestData['artists'][$key]['order'] = (int) $requestData['artists'][$key]['order'];
                            if(isset($artist['artist_id']) && $artist['artist_id']) {

                            }
                            else {
                                $response['error_messages'] = 'Please select Artist';
                                return Redirect::back()->withErrors(['', $response['error_messages']]);
                            }
                        }
                    }
                    break;

                case 'banner':
                    $pagedata   = array_except($page, array('banners'));
                    uasort($requestData['banners'], 'sort_by_order');
                    foreach ($requestData['banners'] as $key => $banner) {
			    $requestData['banners'][$key]['order'] = (int) $requestData['banners'][$key]['order'];
			     $requestData['banners'][$key]['platforms'] = isset($requestData['banners'][$key]['platforms']) ? $requestData['banners'][$key]['platforms'] :["android"];
                        if(isset($banner['name']) && $banner['name']) {

                        }
                        else {
                            $response['error_messages'] = $key. ' Banner Name is required';
                            return Redirect::back()->withErrors(['', $response['error_messages']]);
                        }
                    }
                    break;

                case 'content':
                    $pagedata   = array_except($page, array('contents'));
                    uasort($requestData['contents'], 'sort_by_order');
                    foreach ($requestData['contents'] as $key => $content) {
			    $requestData['contents'][$key]['order'] = (int) $requestData['contents'][$key]['order'];
			     $requestData['contents'][$key]['platforms'] = isset($requestData['contents'][$key]['platforms']) ? $requestData['contents'][$key]['platforms'] :["android"];
                        if(isset($content['content_id']) && $content['content_id']) {

                        }
                        else {
                            $response['error_messages'] = $key. ' Content is required';
                            return Redirect::back()->withErrors(['', $response['error_messages']]);
                        }
                    }
                    break;
                default:
                    # code...
                    break;
            }
        }

        $response = $this->pageservice->updateItems($requestData, $id);

        if(!empty($response['error_messages'])) {
            return Redirect::back()->withInput();
        }

        Session::flash('message','Page Section updated succesfully');
        return Redirect::route('admin.pages.manage', $page->_id);
    }
}
