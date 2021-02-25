<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;


use App\Http\Controllers\Controller;
use App\Http\Requests\ArtistRequest;
use App\Http\Requests\ArtistConfigRequest;
use App\Http\Requests\ArtistSendNotificationRequest;
use App\Services\ArtistService;
use App\Services\Notifications\PushNotification;
use App\Services\Export\AsktoartistExport;
use App\Services\CampaignService;
use App\Services\CustomerService;
use App\Services\ActivityService;
use App\Services\BadgeService;
use App\Http\Requests\BadgeRequest;

class ArtistController extends Controller
{

    protected $artistservice;
    protected $pushnotification;
    protected $asktoartistexport;
    protected $campaignservice;
    protected $customerservice;
    protected $activityservice;
    protected $badgeservice;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function __construct(
        ArtistService $artistservice,
        AsktoartistExport $asktoartistexport,
        CampaignService $campaignservice,
        CustomerService $customerservice,
        ActivityService $activityService,
        BadgeService $badgeservice
    )
    {
        $this->artistservice = $artistservice;
        $this->asktoartistexport = $asktoartistexport;
        $this->campaignservice = $campaignservice;
        $this->customerservice = $customerservice;
        $this->activityservice = $activityService;
        $this->badgeservice = $badgeservice;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $request['perpage'] = 10;
        $viewdata = [];
        $responseData = $this->artistservice->index($request);

        $viewdata['artists']        = $responseData['artists'];
        $viewdata['appends_array']  = $responseData['appends_array'];

        return view('admin.artists.index', $viewdata);
    }

    /**
     * Show a ArtistConfig of the resource.
     *
     * @return Response
     */
    public function showArtistConfig($artist_id)
    {

        $viewdata = [];
        $artist = $this->artistservice->find($artist_id);
        $viewdata['artist'] = $artist;
        $artist_id = $artist->_id;
//        $artistconfig = $this->artistservice->showArtistConfig($artist_id)->toArray();
        $artistConfig = \App\Models\Artistconfig::where('artist_id', '=', $artist_id)->first();
        $viewdata['artistconfig'] = $artistConfig;

        $languages      = [];
        $languages_data = $this->artistservice->getLanguages();
        if($languages_data) {
            $languages = isset($languages_data['languages']) ? $languages_data['languages'] : [];
        }

        $viewdata['languages']    = $languages;

        return view('admin.artists.editconfig', $viewdata);
    }


    public function askToArtist(Request $request)
    {
        if (!empty($request->all()['data_report'])) {
            $export = $this->asktoartistexport->export_asktoartist($request);
            return Redirect::back()->with(array('success_message' => "Exported " . ucwords($export) . " Successfully!"))->withInput();
        }
        $viewdata = [];
        $artists = $this->artistservice->artistList();

        $request['perpage'] = 50;
        $responseData = $this->artistservice->askToArtist($request);
        $viewdata['questions'] = $responseData['questions'];
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['appends_array'] = $responseData['appends_array'];

        return view('admin.asktoartist.index', $viewdata);
    }

    /**
     * Display a Update ArtistConfig of the resource.
     *
     * @return Response
     */
    public function updateArtistConfig(ArtistConfigRequest $request)
    {

        $viewdata = [];
        $response = $this->artistservice->updateArtistConfig($request);
        $artistconfig = $response["results"]["artistconfig"];
        $artist_id = trim($artistconfig['artist_id']);
        $viewdata['artistconfig'] = $artistconfig;

        Session::flash('message', 'Artist Config updated succesfully');
        return Redirect::route('admin.artists.showconfig', ['artistid' => $artist_id]);
    }


    /**
     * Show a Send Notification  of the resource.
     *
     * @return Response
     */
    public function showSendNotifictaion($artist_id)
    {

        $viewdata = [];
        $artist = $this->artistservice->find($artist_id);

        $viewdata['artist'] = $artist;
        $artist_id = $artist->_id;
        $artistconfig = $this->artistservice->showArtistConfig($artist_id);
        $viewdata['artistconfig'] = $artistconfig;
        $viewdata['code'] = \App\Models\Bucket::active()->where('artist_id', $artist_id)->lists('code', 'code')->toArray();

        return view('admin.artists.sendnotification', $viewdata);
    }


    /**
     * Display a Update ArtistConfig of the resource.
     *
     * @return Response
     */
    public function sendNotifictaion(ArtistSendNotificationRequest $request)
    {
        $response = $this->campaignservice->sndCustomNotificationToCustomerByArtist($request);

        Session::flash('message', 'Notification Send succesfully');
        return Redirect::route('admin.artists.index');
    }

    public function showArtistActivities(Request $request, $artistid)
    {
        $viewdata = [];
        $masterActivitiesData = $this->activityservice->activity_list($request, $artistid);
        $viewdata['activities'] = $masterActivitiesData;
        $viewdata['artist_id'] = $artistid;
        return view('admin.artistactivities.index', $viewdata);
    }

    public function createActivities(Request $request)
    {
        $updateQueryData = $request->all()['artistactivities'];
        $updateQueryResult = $this->artistservice->create_activity($updateQueryData);

        $viewdata = [];
        $viewdata['activities'] = $updateQueryResult['results'];
        $viewdata['artist_id'] = $updateQueryData[0]['artist_id'];

        return view('admin.artistactivities.index', $viewdata);
    }

    public function showArtistBadges(Request $request, $artistid)
    {
        $viewdata = [];

        $artistBadgesList = $this->badgeservice->showArtistBadgeList($artistid);
        $artists = $this->artistservice->find($artistid);
        $badgesName = $this->badgeservice->badgesName($artistid);

        $viewdata['artists'] = (isset($artists)) ? $artists : [];
        $viewdata['badges_name'] = $badgesName;
        $viewdata['badges'] = $artistBadgesList;
        $viewdata['artist_id'] = $artistid;
        return view('admin.artistbadges.index', $viewdata);
    }

    public function updateArtistBadges(Request $request, $artistId, $badgeId)
    {
        $viewdata = [];

        $badge = $this->badgeservice->showListBadgeWise($artistId, $badgeId);
        $artists = $this->artistservice->artistList();
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results'] : [];
        $viewdata['artist_id'] = $artistId;
        $viewdata['badge_id'] = $badgeId;

        $viewdata['badge'] = $badge;

        return view('admin.artistbadges.edit', $viewdata);
    }

//    public function update(BadgeRequest $request, $artistId)
//    {
//        $updateBadgeArtistWise = $this->badgeservice->updateBadgeArtistWise($request);
//
//        if (!empty($response['error_messages'])) {
//            return Redirect::back()->withInput();
//        }
//        $artistid = $request['artist_id'];
//
//        $masterBadgesData = $this->badgeservice->showBadgesArtistWise($artistid);
//        $masterBadgesData = $masterBadgesData['results']['badges']->toArray();
//        $badgesDataArtistWise = $this->badgeservice->showListArtistWise($artistid);
//        $response = $this->badgeservice->result($masterBadgesData, $badgesDataArtistWise);
//
//        $viewdata['badges'] = $response;
//        $viewdata['artist_id'] = $request['artist_id'];
//
//        return view('admin.artistbadges.index', $viewdata);
//    }
    public function create(Request $request, $artistid)
    {
        $viewdata = [];
        $artists = $this->artistservice->find($artistid);

        $badgesName = $this->badgeservice->badgesName($artistid);

        $viewdata['artists'] = (isset($artists)) ? $artists : [];
        $viewdata['badges_name'] = $badgesName;
        $viewdata['artist_id'] = $artistid;

        return view('admin.artistbadges.create', $viewdata);

    }

    public function createArtistBadges(Request $request, $artistid)
    {
        $createBadgeArtistWise = $this->badgeservice->createBadgeArtistWise($request);
        return Redirect::route('admin.artistbadges.index', ['artist_id' => $artistid]);
    }

    public function fanclassifications(Request $request, $artist_id)
    {
        $viewdata = [];
        $fanclassifications = $this->badgeservice->fanclassifications($artist_id);
        $artists = $this->artistservice->find($artist_id);
        $viewdata['fanclassifications'] = $fanclassifications;
        $viewdata['artist_id'] = $artist_id;
        $viewdata['artists'] = (isset($artists)) ? $artists : [];
        return view('admin.artistbadges.fanclassification', $viewdata);
    }

    public function channelreports(Request $request)
    {
        $viewdata = [];
        $artist_info = $this->artistservice->artistChannelNamespace($request);
        $viewdata['channel_reports'] = $artist_info['results']['channel_reports'];
        $viewdata['appends_array'] = $artist_info['results']['appends_array'];

        $artists = $this->artistservice->artistList();
        $viewdata['artists'] = (isset($artists['results'])) ? $artists['results']->toArray() : [];

        return view('admin.reports.channelreport', $viewdata);
    }


}
