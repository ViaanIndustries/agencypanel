<?php

namespace App\Http\Controllers\Admin;

/**
 * ControllerName : Cast.
 * Maintains a list of functions used for Cast.
 *
 * @author Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since 2019-05-13
 * @link http://bollyfame.com/
 * @copyright 2019 BOLLYFAME
 * @license http://bollyfame.com//license/
 */

use Illuminate\Http\Request;

use Input;
use Redirect;
use Config;
use Session;


use App\Http\Controllers\Controller;
use App\Services\ArtistService;

class TestController extends Controller {

    protected $service_artist;

    /**
     * Constructor
     *
     * @return Response
     */
    public function __construct(ArtistService $service_artist){
        $this->service_artist = $service_artist;
    }


    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request) {
        echo "<pre/>";
        echo __METHOD__ . "<br />";
        $artist_id = '5d3ee748929d960e7d388ee2'; // BollyFame

        $artist_config = $this->service_artist->getEmailHeaderData($artist_id);
        dd($artist_config);
    }


    /**
     * Returns Artist Config
     *
     * @return Response
     */
    public function getArtistConfig() {
        $ret = null;
        $artist_id = '5a91386b9353ad33ab15b0d2';

        $ret = $this->service_artist->getArtistConfig($artist_id);

        dd($ret);
        return $ret;
    }

    /**
     * Returns Artist Config
     *
     * @return Response
     */
    public function getArtistConfigLanguages() {
        $ret = null;
        $artist_id = '5a91386b9353ad33ab15b0d2';

        $ret = $this->service_artist->getConfigLanguages($artist_id);

        dd($ret);
        return $ret;
    }

}
