<?php

namespace App\Services\Live\Agora;

/**
 * Checkmobi abstract class.
 *
 *
 * @author      Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since       2019-06-18
 * @link        http://bollyfame.com/
 * @copyright   2019 BOLLYFAME Media Pvt. Ltd
 * @license     http://bollyfame.com/license/
 */

use Config;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;


class AgoraRest {

    const BASE_URL = "https://api.agora.io";
    const APP_ID = "ebd75c6b14a64ec0a3ef1b7e356665ca";
    private $client;
    private $auth_token;

	public function __construct() {

         $base_uri =  self::BASE_URL; 
 		 $headers = [
            // All POST requests arguments must be passed as json with the Content-Type set as application/json.
			'Content-Type' => 'application/json',

            //The secret key is passed to the API as value of a HTTP header called Authorization.
            //'Authorization' => $this->auth_token,
            
            'auth' => [
                'e9fbc9e97fd9450281b8e201beff2885',
                'cc34ba582b1949aea795c761285d44e0'
            ]
		];

		$this->client = new Client([
            'base_uri' 	=> $base_uri,
            'headers'   => $headers
        ]);
        
        
    }

     /**
     * Returns Country List
     *
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-18
     */
    public function getProducerChannels($post) {
        $ret = true;
        $error_message = '';
        $data=[];
        $resource   =  '/dev/v1/channel/user/'. self::APP_ID.'/'.$post['channel_namespace'];
        if(!$error_message) {
            try {
                $response = $this->clientGetRequest($resource, $data);
                
            }
            catch (Exception $e) {
                $error_message = $e->getMessage();
            }
        }
        if($error_message) {
            throw new \Exception($error_message);
        }

        return $response;
    }

	public function clientGetRequest($resource, $params = []) {

        $response = $this->client->request('GET', $resource,['auth' => ['e9fbc9e97fd9450281b8e201beff2885', 'cc34ba582b1949aea795c761285d44e0'] , 'query' => $params, 'exceptions' => true ]);
        $statusCode = $response->getStatusCode();
	    $body = $response->getBody()->getContents();
    	return $body;
	}
}


