<?php

namespace App\Services\Sms;

/**
 * Customer SMS Service class.
 *
 *
 * @author 		Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since 		2019-06-18
 * @link 		http://bollyfame.com/
 * @copyright 	2019 BOLLYFAME Media Pvt. Ltd
 * @license 	http://bollyfame.com/license/
 */


use App\Services\Sms\Checkmobi as Sms;

class CustomerSmsService extends Sms {

    /**
     * Validate Customer mobile no.
     *
     * "id": "SMS-09BB940E-4D1E-4DD5-9F48-5D3E291A7D7C",
     * "type": "sms",
     * "validation_info": {
     *		"country_code": 91,
     *		"country_iso_code": "IN",
     *		"carrier": "Tata Docomo",
     *		"is_mobile": true,
     * 		"e164_format": "+918097987829",
     *		"formatting": "+91 80979 87829"
     * }
     *
     * @param   array $data
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-18
     */
    public function requestOtp($data)
    {
        $ret = true;
        $error_message = '';
        $params 		= [];
        $mobile 		= isset($data['mobile']) ? trim($data['mobile']) : '';
        $country_code 	= isset($data['mobile_country_code']) ? trim($data['mobile_country_code']) : '';
        $platform 		= isset($data['platform']) ? trim($data['platform']) : 'web'; // ios, android, web, desktop

        // Extact Only Numbers
        $country_code   = (int) filter_var($country_code, FILTER_SANITIZE_NUMBER_INT);
        $mobile         = (int) filter_var($mobile, FILTER_SANITIZE_NUMBER_INT);

        $params['number'] =  '+' . $country_code . $mobile;

		if($platform) {
			$params['platform'] = $platform;
		}

		try {
			$ret = $this->sendOtp($params);
		}
        catch (Exception $e) {
            $error_message = $e->getMessage();
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Validate Customer mobile no.
     *
     * @param   array $data
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-18
     */
    public function validateOtp($data)
    {
        $ret = true;
        $error_message = '';
        $params 		= [];
        $country_code 	= isset($data['country_code']) ? trim($data['country_code']) : '';
        $mobile 		= isset($data['mobile']) ? trim($data['mobile']) : '';
        $mobile_otp_id  = isset($data['mobile_otp_id']) ? trim($data['mobile_otp_id']) : '';
        $otp            = isset($data['otp']) ? trim($data['otp']) : '';
        $platform 		= isset($data['platform']) ? trim($data['platform']) : 'web'; // ios, android, web, desktop

        $params['mobile_otp_id'] = $mobile_otp_id;
        $params['otp'] = $otp;

		try {
			$ret = $this->verifyOtp($params);
		}
        catch (Exception $e) {
            $error_message = $e->getMessage();
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }

    /**
     * Validate Customer mobile no.
     *
     * @param   array $data
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-18
     */
    public function getCountriesList($data = '')
    {
        $ret = true;
        $error_message = '';
        $params         = [];

        try {
            $ret = $this->getCountries($params);
        }
        catch (Exception $e) {
            $error_message = $e->getMessage();
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }
}
