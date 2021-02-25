<?php

namespace App\Services\Sms;

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


abstract class Checkmobi {

    const BASE_URL = "https://api.checkmobi.com";
    const API_VERSION = "v1";

    private $http_client;
    private $auth_token;

	public function __construct($auth_token = '') {

		if(!$auth_token) {
			$auth_token = Config::get('app.checkmobi.secret_api_key');
		}

		$this->auth_token = $auth_token;


        // Set client option as per checkmobi requirements

        // API Request
        // The base URL for all API requests is https://api.checkmobi.com/v1/
        $base_uri = self::BASE_URL . '/' . self::API_VERSION . '/';

        // All arguments required in all resources using POST are sent using key/value JSON encoding payload.

        // Authentication
        // All requests to CheckMobi API are authenticated using your secret key.
        // The secret key is passed to the API as value of a HTTP header called Authorization.

        // Content Type
        // CheckMobi only accepts input of the type application/json.
        // All POST requests arguments must be passed as json with the Content-Type set as application/json.


		$headers = [
            // All POST requests arguments must be passed as json with the Content-Type set as application/json.
			'Content-Type' => 'application/json',

            //The secret key is passed to the API as value of a HTTP header called Authorization.
			'Authorization' => $this->auth_token,
		];

		$this->http_client = new Client([
			'base_uri' 	=> $base_uri,
			'headers'	=> $headers,
		]);

    }


    /**
     * Send Sms
     *
     * @param   array $data
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-18
     */
    public function sendSms($data)
    {
        $ret = true;
        $error_message = '';

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Send OTP
     *
     * @param   array $data
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-18
     */
    public function sendOtp($mobile_data) {
        $ret = true;
        $error_message = '';
        $data = [];
        $resource   = 'validation/request';

        $mobile_number  = isset($mobile_data['number']) ? $mobile_data['number'] : '';
        $platform       = isset($mobile_data['platform']) ? $mobile_data['platform'] : 'web';

        if($mobile_number) {
            $data['number']     = $mobile_number;
            $data['type']       = 'sms';
            $data['platform']   = $platform;

            try {
                $response = $this->clientPostRequest($resource, $data);
                if($response && isset($response['status']) && $response['status'] == 200) {
                    if(isset($response['results'])) {

                        if(isset($response['results']['id'])) {
                            $ret = $response['results']['id'];
                        }

                        /*
                        if(isset($response['results']['validation_info']) && isset($response['results']['validation_info']['is_mobile'])) {
                            if($response['results']['validation_info']['is_mobile'] == false) {
                                $error_message = 'Given number is not a mobile number';
                            }
                        }
                        */
                    }
                }
            }
            catch (Exception $e) {
                $error_message = $e->getMessage();
            }
        }
        else {
            $error_message = 'Mobile number is missing';
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }



    /**
     * Verify OTP
     *
     * @param   array $data
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-18
     */
    public function verifyOtp($otp_data) {
        $ret = true;
        $error_message = '';
        $data = [];
        $resource   = 'validation/verify';

        $mobile_otp_id  = isset($otp_data['mobile_otp_id']) ? $otp_data['mobile_otp_id'] : '';
        $pin            = isset($otp_data['otp']) ? $otp_data['otp'] : '';

        if($mobile_otp_id) {
            $data['id'] = $mobile_otp_id;
        }
        else {
            $error_message = 'Mobile OTP Validation request id is missing';
        }

        if($pin) {
            $data['pin'] = $pin;
        }
        else {
            $error_message = 'Mobile OTP pin is missing';
        }

        if(!$error_message) {
            try {
                $response = $this->clientPostRequest($resource, $data);

                if($response && isset($response['status']) && $response['status'] == 200) {
                    if(isset($response['results'])) {

                        if(isset($response['results']['validated'])) {
                            if($response['results']['validated'] == false) {
                                $error_message = 'The OTP you have entered is incorrect. Please check and try again.';
                                $ret = false;
                            }

                            if($response['results']['validated'] == true) {
                                $ret = true;
                            }
                        }
                    }
                }
            }
            catch (Exception $e) {
                $error_message = $e->getMessage();
            }
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Returns Country List
     *
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-18
     */
    public function getCountries() {
        $ret = true;
        $error_message = '';
        $data = [];
        $resource   = 'countries';

        if(!$error_message) {
            try {
                $response = $this->clientGetRequest($resource, $data);

                if($response && isset($response['status']) && $response['status'] == 200) {
                    if(isset($response['results'])) {
                        $ret = $response['results'];
                    }
                }
            }
            catch (Exception $e) {
                $error_message = $e->getMessage();
            }
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


	/**
	 * Client Get Request
	 *
	 *
	 * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
	 * @since   2019-06-18
	 */
	public function clientGetRequest($resource, $params = []) {
		$return = [];

		try {
            $request = $this->http_client->get($resource, $params);
            $response = $request->getBody()->getContents();
            $return = [
                'status' => $request->getStatusCode(),
                'reason' => $request->getReasonPhrase(),
                'results' => json_decode($response, true),
            ];
        }
        catch (RequestException $e) {
            $error = $e->getResponse();
            $ret_status = 400;
            $ret_reason = 'RequestException error';
            if ($error) {
                $ret_status = $error->getStatusCode();
                $ret_reason = $error->getReasonPhrase();
            }
            $return = [
                'status' => $ret_status,
                'reason' => $ret_reason
            ];
        }
        catch (Exception $e) {
            $message = array(
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            );
            $return = [
                'status' => 400,
                'reason' => $message['type'] . ' : ' . $message['message'] . ' in ' . $message['file'] . ' on ' . $message['line']
            ];
        }

        return $return;
	}


	/**
	 * Client Post Request
	 *
	 *
	 * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
	 * @since   2019-06-18
	 */
	public function clientPostRequest($resource, $params = []) {
		$return = [];

        // All arguments required in all resources using POST are sent using key/value JSON encoding payload.
        $parameters = [];

        if($params) {
            $parameters['json'] = $params;
        }

		try {
            $request = $this->http_client->post($resource, $parameters);

            $response = $request->getBody()->getContents();
            $return = [
                'status' => $request->getStatusCode(),
                'reason' => $request->getReasonPhrase(),
                'results' => json_decode($response, true),
            ];
        }
        catch (RequestException $e) {
            $error = $e->getResponse();
            $ret_status = 400;
            $ret_reason = 'RequestException error';
            if ($error) {
                $ret_status = $error->getStatusCode();
                $ret_reason = $error->getReasonPhrase();
            }
            $return = [
                'status' => $ret_status,
                'reason' => $ret_reason
            ];
        }
        catch (Exception $e) {
            $message = array(
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            );
            $return = [
                'status' => 400,
                'reason' => $message['type'] . ' : ' . $message['message'] . ' in ' . $message['file'] . ' on ' . $message['line']
            ];
        }

        return $return;
	}
}
