<?php

namespace App\Services;

/**
 * ServiceName : Account.
 * Maintains a list of functions used for Account.
 *
 * @author      Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since       2019-06-11
 * @link        http://bollyfame.com
 * @copyright   2019 BOLLYFAME
 * @license     http://bollyfame.com/license
 */

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;
use Hash;

use Carbon\Carbon;

use App\Models\Customer;
use App\Services\Jwtauth;
use App\Services\Image\Kraken;
use App\Services\Mailers\CustomerMailer;
use App\Services\Sms\CustomerSmsService;
use App\Services\Cache\AwsElasticCacheRedis;
use App\Services\ArtistService;
use App\Services\CustomerActivityService;

class AccountService
{
    protected $redisClient;
    protected $jwtauth;
    protected $kraken;
    protected $mailer;
    protected $customersms;
    protected $cache;
    protected $customer;
    protected $service_artist;
    protected $service_customer_activity;

    private $cache_expire_time = 600; // 10 min in seconds

    public function __construct(Jwtauth $jwtauth, Kraken $kraken, CustomerMailer $mailer, CustomerSmsService $customersms, AwsElasticCacheRedis $cache, Customer $customer, ArtistService $service_artist, CustomerActivityService $service_customer_activity) {
        $this->jwtauth          = $jwtauth;
        $this->kraken           = $kraken;
        $this->mailer           = $mailer;
        $this->customersms      = $customersms;
        $this->cache            = $cache;
        $this->customer         = $customer;
        $this->service_artist   = $service_artist;
        $this->service_customer_activity = $service_customer_activity;
    }


    /**
     * Returns mobile number in E.164 format excluding + sign
     *
     * @param   string $mobile Mobile Number
     *
     * @return  boolean
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    static public function isValidMobileNumber($mobile, $mobile_country_code = 91) {
        $ret = '';
        $ret = preg_match('/^[0-9]{10}+$/', $mobile);
        return $ret;
    }


    /**
     * Returns mobile number in E.164 format excluding + sign
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    static public function formattedMobileNumber($mobile, $mobile_country_code = 91) {
        $ret = '';

        // Extact Only Numbers
        $mobile_country_code= (int) filter_var($mobile_country_code, FILTER_SANITIZE_NUMBER_INT);
        $mobile             = (int) filter_var($mobile, FILTER_SANITIZE_NUMBER_INT);

        $ret = $mobile_country_code . $mobile;

        return $ret;
    }


    /**
     * Return sanitized data
     * Just trims data of keys that are present in rules
     *
     * @param   array   $data
     * @param   array   $rules
     *
     * @return  array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-29
     */
    public function sanitizedUserData($data) {
        $ret = [];

        $data_keys = [
            'artist_id',
            'device_id',
            'email',
            'facebook_id',
            'first_name',
            'fcm_id',
            'identity',
            'last_name',
            'picture',
            'platform',
            'segment_id',
            'mobile',
            'dob',
        ];

        foreach ($data as $key => $value) {
            if(in_array($key, $data_keys)) {
                $data[$key] = trim($value);
            }
        }

        $ret = $data;

        return $ret;
    }

    /**
     * Return sanitized Customer data
     *
     * @param   object  $customer
     *
     * @return  array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-26
     */
    public function sanitizedCustomerData($customer) {
        $ret = [];

        $exclude_atts = [
            'artists',
            'password',
        ];

        if(is_object($customer)) {
            $ret = array_except($customer->toArray(), $exclude_atts);
        }

        if(is_array($customer)) {
            $ret = array_except($customer, $exclude_atts);
        }

        return $ret;
    }

    /**
     * Return  Customer data exclude attributes
     *
     * @param   array  $include_atts // Forcefully Include Attributes
     *
     * @return  array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-26
     */
    public function getCustomerDataExcludeAttributes($include_atts = []) {
        $ret = [
            'password',
            'password_confirmation',
            'artist_id',
            'mobile_otp_id',
            'otp',
            'platform',
            'email',  // Email Id will never updated
        ];

        if($include_atts) {
            $ret = array_diff($ret, $include_atts);
        }

        return $ret;
    }


    /**
     * Countries.
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function countries($request){
        $error_messages = [];
        $results        = [];
        $data           = $request->all();
        $customer_obj   = null;
        $customer_arr   = [];
        $artist_id      = isset($data['artist_id']) ? trim($data['artist_id']) : '';

        try {
            $countries  = $this->customersms->getCountriesList($data);
            if($countries) {
                $results['list'] = $countries;
            }
        }
        catch (\Exception $e) {
            $error_messages[] = $e->getMessage();
        }

        if($error_messages) {
            $results['status_code'] = 200;
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Customer registration.
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function customerRegister($request){
        $error_messages = [];
        $results        = [];
        $data           = $request->all();
        $customer_obj   = null;
        $customer_arr   = [];
        $artist_id      = isset($data['artist_id']) ? trim($data['artist_id']) : '';
        $identity       = isset($data['identity']) ? strtolower(trim($data['identity'])) : 'email';
        $email          = isset($data['email']) ? strtolower(trim($data['email'])) : '';
        $mobile         = isset($data['mobile']) ? strtolower(trim($data['mobile'])) : '';
        $mobile_country_code= isset($data['mobile_country_code']) ? strtolower(trim($data['mobile_country_code'])) : '';

        $customer_id        = null;
        $customer_profile   = [];
        $coinsxp            = [];
        $metaids            = [];
        $new_user           = false;

        $customer_arr = array_except($data, ['password_confirmation', 'image_url']);

        // Extact Only Numbers
        $mobile_country_code= (int) filter_var($mobile_country_code, FILTER_SANITIZE_NUMBER_INT);
        $mobile             = (int) filter_var($mobile, FILTER_SANITIZE_NUMBER_INT);

        try  {

            switch (strtolower($identity)) {
                case 'facebook':
                case 'google':
                case 'twitter':
                case 'social':
                    $customer_arr['email_verified']     = 'true';
                    $customer_arr['mobile_verified']    = 'false';
                    $customer_arr['status']             = 'active';
                    break;
                case 'mobile':
                    $customer_arr['mobile_verified']    = 'true';
                    $customer_arr['status']             = 'active';
                    $customer_arr['email_verified']     = 'false';
                    $customer_arr['email_otp']          = rand(100000, 999999);
                    $data['email_otp_generated_at']     = Carbon::now();
                    break;
                case 'email':
                    $customer_arr['email_verified']     = 'false';
                    $customer_arr['email_otp']          = rand(100000, 999999);
                    $data['email_otp_generated_at']     = Carbon::now();
                    $customer_arr['mobile_verified']    = 'false';
                    $customer_arr['status']             = 'unverifed';
                default:
                    break;
            } // End switch $identity


            // Check whether customer email already registered or not
            $email_registered = $this->isCustomerEmailNotRegistered($email);
            if($mobile) {
                // Check whether customer mobile already registered or not
                $mobile_registered = $this->isCustomerMobileNotRegistered($mobile, $mobile_country_code);
            }

            // Save Customer details

            // Customer Profile Photo
            if ($request->hasFile('photo')) {
                $parmas = ['file' => $request->file('photo'), 'type' => 'customerprofile'];
                $photo  = $this->kraken->uploadToAws($parmas);
                if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                    array_set($customer_arr, 'photo', $photo['results']);
                    array_set($customer_arr, 'picture', $photo['results']['cover']);
                }
            }
            else {
                if(!isset($customer_arr['picture'])) {
                    if (empty($customer_arr['first_name'])) {
                        array_set($customer_arr, 'picture',  Config::get('product.' . env('PRODUCT') . '.s3.base_urls.photo') . 'default/customersprofile/default.png');
                    }
                    else {
                        array_set($customer_arr, 'picture', Config::get('product.' . env('PRODUCT') . '.s3.base_urls.photo') . 'default/customersprofile/' . strtolower(substr($customer_arr['first_name'], 0, 1)) . '.png');
                    }
                }
            }

            // Sanitize data
            $customer_arr = $this->sanitizedUserData($customer_arr);

            if($customer_arr) {
                $customer   = $this->saveCustomer($customer_arr);
                if($customer) {
                    // Customer status is active then login that customer
                    // This will happen in case of social registration
                    if($customer->status == 'active') {
                        // Send Welcome Mail
                        $this->sendWelcomeMailToCustomer($customer_arr, $artist_id);

                        $results['customer']   = apply_cloudfront_url($this->sanitizedCustomerData($customer));
                        $results['token']      = $this->jwtauth->createLoginToken($customer_arr);
                    }
                    else {
                        switch ($identity) {
                            case 'email':
                            case 'mobile':
                                $results['info']    = 'Email verification pending';
                                $email_otp_data = [];
                                // Get Email Default Template Data
                                $email_otp_data = $this->service_artist->getEmailTemplateDefaultData($artist_id);

                                // Generate Email Template specific data
                                $email_otp_data['customer_email']   = $email;
                                $email_otp_data['customer_name']    = generate_fullname($customer_arr);
                                $email_otp_data['otp']              = $customer_arr['email_otp'];

                                // Sent Email verification mail
                                $send_otp_mail = $this->mailer->sendOtp($email_otp_data);
                                break;

                            default:
                                // No need to send email verification mail in case of social login registaration
                                break;
                        }
                    }
                }
            }
        }
        catch (\Exception $e) {
            $error_messages[] = $e->getMessage();
        }

        if($error_messages) {
            $results['status_code'] = 200;
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Customer login.
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function customerLogin($request){
        $error_messages = [];
        $results        = [];
        $data           = $request->all();
        $customer_obj   = null;
        $customer_arr   = [];
        $artist_id      = isset($data['artist_id']) ? trim($data['artist_id']) : '';
        $identity       = isset($data['identity']) ? strtolower(trim($data['identity'])) : 'email';
        $email          = isset($data['email']) ? strtolower(trim($data['email'])) : '';
        $mobile         = isset($data['mobile']) ? trim($data['mobile']) : '';
        $mobile_country_code    = isset($data['mobile_country_code']) ? trim($data['mobile_country_code']) : '';
        $platform       = isset($data['platform']) ? trim($data['platform']) : '';
        $platform_v     = isset($data['v']) ? trim($data['v']) : '';
        $referrer_customer_id   = isset($data['referrer_customer_id']) ? trim($data['referrer_customer_id']) : '';

        $customer_id        = null;
        $customer_profile   = [];
        $coinsxp            = [];
        $metaids            = [];
        $new_user           = false;
        $reward             = null;

        try  {
            switch (strtolower($identity)) {
                case 'mobile':
                    $customer_id = $this->getCustomerIdByMobile($mobile, $mobile_country_code);

                    // Validate Mobile OTP
                    $otp_validate  = $this->customersms->validateOtp($data);
                    break;

                case 'facebook':
                case 'google':
                case 'twitter':
                case 'social':
                    $customer_id = $this->getCustomerIdByEmail($email, null, $identity);
                    // If customer is not registered then register that user
                    if(!$customer_id) {
                        $registration = $this->customerRegister($request);
                        if($registration && isset($registration['results']) && isset($registration['results']['customer']) ) {
                            $customer_id = isset($registration['results']['customer']['_id']) ? $registration['results']['customer']['_id'] : null;
                            if($customer_id) {
                                $new_user = true;
                            }
                        }
                    }
                    else {
                        // Update Email Status As verifed
                        // and make customer status as active
                        $customer_social_data = [
                            'email_verified' => 'true',
                            'status' => 'active',
                        ];
                        $this->updateCustomerProfile($customer_id, $customer_social_data);
                    }
                    break;
                case 'email':
                default:
                    $customer_id = $this->getCustomerIdByEmail($email);
                    break;
            } // End switch $identity

            if($customer_id) {
                // If Customer is new then add customer activity which in turn will run reward program
                if($new_user) {
                    $activity_data = [
                        'platform'  => $platform,
                        'v'         => $platform_v,
                        'referrer_customer_id' => $referrer_customer_id,
                    ];
                    $reward =  $this->service_customer_activity->onRegistration($customer_id, $artist_id, $activity_data);

                    if($reward) {
                        if($referrer_customer_id) {

                        }
                    }
                }

                // Get Customer Info by Id
                $customer_arr = $this->getCustomerById($customer_id);

                if($customer_arr) {
                    // Check whether customer is active or not
                    $this->isCustomerActive($customer_arr);

                    // Verify Password
                    if(strtolower($identity) == 'email') {
                        $this->verifyCustomerPassword($data['password'], $customer_arr);
                    }

                    $results['customer']    = apply_cloudfront_url($this->sanitizedCustomerData($customer_arr));
                    $results['token']       = $this->jwtauth->createLoginToken($customer_arr);
                    $results['new_user']    = $new_user;
                    $results['reward']      = $reward;

                    $this->saveCustomer($customer_arr);
                }
            }
        }
        catch (\Exception $e) {
            $error_messages[] = $e->getMessage();
        }

        if($error_messages) {
            $results['status_code'] = 200;
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Customer login via mobile.
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function customerLoginViaMobile($data){
        $error_messages = [];
        $results        = [];
        $customer_obj   = null;
        $customer_arr   = [];
        $artist_id      = isset($data['artist_id']) ? trim($data['artist_id']) : '';
        $mobile_country_code = isset($data['mobile_country_code']) ? trim($data['mobile_country_code']) : '';
        $mobile         = isset($data['mobile']) ? trim($data['mobile']) : '';
        $mobile_otp_id  = isset($data['mobile_otp_id']) ? trim($data['mobile_otp_id']) : '';
        $otp            = isset($data['otp']) ? trim($data['otp']) : '';

        $customer_id        = null;
        $customer_profile   = [];
        $coinsxp            = [];
        $metaids            = [];
        $new_user           = false;

        try  {
            // Validate Mobile OTP
            $otp_validate  = $this->customersms->validateOtp($data);

            // Find customer associated with mobile no.
            $customer_id = $this->getCustomerIdByMobile($mobile, $mobile_country_code, false);

            if($customer_id) {
                // Get Customer Info by Id
                $customer_arr = $this->getCustomerById($customer_id, false);
            }

            if($customer_arr) {
                // Set mobile verfied status
                $customer_arr['mobile_verified'] = 'true';

                if(isset($customer_arr['status']) == 'unverifed') {
                    $customer_arr['status'] = 'active';

                    // Update Customer Data
                    $update_data = [
                        'mobile_verified' => 'true',
                        'status' => 'active',
                    ];
                    $this->updateCustomerProfile($customer_id, $update_data);
                }

                $customer_id = $customer_arr['_id'];

                // Check whether customer is active or not
                $this->isCustomerActive($customer_arr);

                $results['customer']    = apply_cloudfront_url($customer_arr);
                $results['token']       = $this->jwtauth->createLoginToken($customer_arr);
                $results['registered']  = true;
                $results['new_user']    = $new_user;
            }
            else {
                // Customer is not registered
                $results['registered']  = false;
            }
        }
        catch (\Exception $e) {
            $error_messages[] = $e->getMessage();
        }

        if($error_messages) {
            $results['status_code'] = 200;
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Sent OTP to mobile number
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function mobileOtp($data){
        $error_messages = [];
        $results        = [];
        $customer_obj   = null;
        $customer_arr   = [];
        $artist_id      = isset($data['artist_id']) ? trim($data['artist_id']) : '';
        $mobile         = isset($data['mobile']) ? trim($data['mobile']) : '';
        $mobile_country_code = isset($data['mobile_country_code']) ? trim($data['mobile_country_code']) : '';

        try  {
            // Request Mobile OTP
            $results['mobile_otp_id'] = $this->customersms->requestOtp($data);
        }
        catch (\Exception $e) {
            $error_messages[] = $e->getMessage();

            if(isset($error_messages[0]) && $error_messages[0]) {
                // This hack to get Client errors
                if (preg_match("/Client error/i", $error_messages[0])) {
                    $result_arr = explode(':', $error_messages[0]);
                    $result_arr_count = count($result_arr);
                    if($result_arr) {
                        $error_msg = $result_arr[($result_arr_count - 1)];
                        if($error_msg) {
                            $matches = null;
                            preg_match_all('/\"(.*)\"/', $error_msg, $matches);
                            if($matches && isset($matches[1]) && isset($matches[1][0])) {
                                $error_messages     = [];
                                if(preg_match('/^Invalid phone number/i', $matches[1][0])) {
                                    $error_messages[]   = 'You have entered an invalid mobile number.Please enter a vaild mobile number.';
                                }
                                else {
                                    $error_messages[]   = $matches[1][0];
                                }
                            }
                        }
                    }
                }
            }
        }

        if($error_messages) {
            $results['status_code'] = 200;
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Sent OTP to email
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function emailOtp($data){
        $error_messages = [];
        $results        = [];
        $customer_obj   = null;
        $customer_arr   = [];
        $artist_id      = isset($data['artist_id']) ? trim($data['artist_id']) : '';
        $email          = isset($data['email']) ? strtolower(trim($data['email']))  : '';

        try  {
            // Generate Email Otp for customer
            $customer_arr = $this->generateEmailOtp($email);

            $email_otp_data = [];
            // Get Email Default Template Data
            $email_otp_data = $this->service_artist->getEmailTemplateDefaultData($artist_id);

            // Generate Email Template specific data
            $email_otp_data['customer_email']   = $email;
            $email_otp_data['customer_name']    = generate_fullname($customer_arr);
            $email_otp_data['otp']              = $customer_arr['email_otp'];

            // Sent Email verification mail
            $send_otp_mail = $this->mailer->sendOtp($email_otp_data);

            $results = null;
        }
        catch (\Exception $e) {
            $error_messages[] = $e->getMessage();
        }

        if($error_messages) {
            $results['status_code'] = 200;
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    /**
     * Generate Email OTP for customer
     *
     * @param   string  $email
     * @param   integer $otp
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-13
     */
    public function generateEmailOtp($email) {
        $ret = [];
        $data= [];
        $error_message = '';
        $ret_attributes = ['first_name', 'last_name', 'email', 'email_otp'];

        try  {
            // Find customer by email
            $customer_id = $this->getCustomerIdByEmail($email, false);

            $customer_obj = $this->customer->find($customer_id);

            if(!isset($customer_obj->email_verified)) {
                $data['email_verified'] = 'false';
            }

            $data['email_otp'] = rand(100000, 999999);
            $data['email_otp_generated_at'] = Carbon::now();

            //$saved = $customer_obj->update($data);
            $update_customer_obj = $this->updateCustomerProfile($customer_id, $data);

            $ret = array_only($update_customer_obj->toArray(), $ret_attributes);
        }
        catch (\Exception $e) {
            $error_message = $e->getMessage();
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Customer Email Verifivation.
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-12
     */
    public function customerEmailVerification($data){
        $error_messages = [];
        $results        = [];
        $customer_obj   = null;
        $customer_arr   = [];
        $customer_id    = '';
        $artist_id      = isset($data['artist_id']) ? trim($data['artist_id']) : '';
        $email          = isset($data['email']) ? strtolower(trim($data['email'])) : '';
        $otp            = isset($data['otp']) ? trim($data['otp']) : '';
        $platform       = isset($data['platform']) ? trim($data['platform']) : '';
        $platform_v     = isset($data['v']) ? trim($data['v']) : '';
        $referrer_customer_id   = isset($data['referrer_customer_id']) ? trim($data['referrer_customer_id']) : '';
        $new_user       = true;
        $reward         = null;

        try  {
            // Verify Email Otp
            $this->verifyCustomerEmailOtp($email, $otp);

            // Get Custormer info
            $customer_arr = $this->getCustomerByEmail($email);
            if($customer_arr) {
                $customer_id = $customer_arr['_id'];

                // If Customer is new then add customer activity which in turn will run reward program
                if($new_user) {

                    $activity_data = [
                        'platform'  => $platform,
                        'v'         => $platform_v,
                        'referrer_customer_id' => $referrer_customer_id,
                    ];
                    $reward =  $this->service_customer_activity->onRegistration($customer_id, $artist_id, $activity_data);

                    if($reward) {
                        if($referrer_customer_id) {

                        }
                    }
                }
            }
            $results['customer']    = apply_cloudfront_url($customer_arr);
            $results['token']       = $this->jwtauth->createLoginToken($customer_arr);
            $results['new_user']    = $new_user;
            $results['reward']      = $reward;

            // Send Welcome Mail
            $this->sendWelcomeMailToCustomer($customer_arr, $artist_id);
        }
        catch (\Exception $e) {
            $error_messages[] = $e->getMessage();
        }

        if($error_messages) {
            $results['status_code'] = 200;
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Customer Forgot Password.
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function customerForgotPassword($data){
        $error_messages = [];
        $results        = [];
        $customer_obj   = null;
        $customer_arr   = [];
        $artist_id      = isset($data['artist_id']) ? trim($data['artist_id']) : '';
        $email          = isset($data['email']) ? strtolower(trim($data['email'])) : '';
        $customer_id    = '';
        if($email) {
            try  {
                // Get Customer Info
                $customer_arr =  $this->getCustomerByEmail($email, false);
                if($customer_arr) {
                    $customer_id = $customer_arr['_id'];
                }

                // Generate new password
                $new_password = rand(100000, 999999);
                $customer_obj = \App\Models\Customer::where('_id', $customer_id)->first();
                if($customer_obj) {
                    $data = [];
                    $data['password'] = $new_password;
                    $this->updateCustomerProfile($customer_id, $data, ['password']);

                    // Send Forgot Password mail
                    $this->sendForgotPasswordMailToCustomer($customer_arr, $new_password, $artist_id);

                    $results = null;
                }
                else {
                    $error_messages[] = 'Something went wrong while find customer in db';
                }
            }
            catch (\Exception $e) {
                $error_messages[] = $e->getMessage();
            }
        }
        else {
            $error_messages[] = 'Email is required';
        }

        if($error_messages) {
            $results['status_code'] = 200;
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Verify Customer Email OTP
     *
     * @param   string  $email
     * @param   integer $otp
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-13
     */
    public function verifyCustomerEmailOtp($email, $otp) {
        $ret = true;
        $error_message = '';

        $customer_obj = \App\Models\Customer::where('email', $email)->first();
        if($customer_obj) {
            if(isset($customer_obj->email_verified) && ($customer_obj->email_verified == 'true') ) {
                $error_message = 'Email address is already verfied.';
            }
            else {
                if(isset($customer_obj->email_otp)) {
                    if($customer_obj->email_otp != $otp) {
                        $error_message = 'The OTP you have entered is incorrect. Please check and try again.';
                    }
                    else {
                        $customer_obj->email_verified = 'true';
                        $customer_obj->status = 'active';

                        $customer_obj->save();
                    }
                }
            }
        }
        else {
            $error_message = "Couldn't find your account associated with email";
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Save Customer
     *
     * @param   array   $data
     *
     * @return  Object  Customer
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-25
     */
    public function saveCustomer($data) {
        $ret = false;
        $error_message = '';

        $email = isset($data['email']) ? $data['email'] : '';

        if($email) {
            // First check whether customer email already exists or not
            $ret = $this->customer->where('email', $email)->first();
            if(!$ret) {
                $ret = $this->customer->create($data);
            }

            if($ret) {
                $customer_id    = $ret->_id;
                $artist_id      = isset($data['artist_id']) ? $data['artist_id'] : '';

                if($artist_id) {
                    $ret->push('artists', trim(strtolower($artist_id)), true);

                    $this->syncCustomerArtist($customer_id, $artist_id);
                }

                $update_data = [
                    'last_visited' => Carbon::now(),
                ];

                $ret->update($update_data);
            }
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Sync Customer Artist
     *
     * @param   string      $cutomer_id
     * @param   string      $artist_id
     *
     * @return  boolean
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-25
     */
    public function syncCustomerArtist($customer_id, $artist_id) {
        $ret = false;
        $error_message = '';

        $data = [
            'customer_id'   => $customer_id,
            'artist_id'     => $artist_id,
            'xp'            => 0,
            'fan_xp'        => 0,
        ];

        $customerartist = \App\Models\Customerartist::where('customer_id', '=', $customer_id)->where('artist_id', '=', $artist_id)->first();

        if (!$customerartist) {
            $customerartist = new \App\Models\Customerartist($data);
            $saved = $customerartist->save();
            if($saved) {
                $ret = false;
            }
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }



    /**
     * Return Logged In User customer Id
     *
     *
     * @return  string   Customer Id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-25
     */
    public function getCustomerId() {
        $ret = '';
        $error_message  = '';

        try {
            // Find Customer ID from Token
            $ret = $this->jwtauth->customerIdFromToken();
        }
        catch (\Exception $e) {
            $error_messages[] = $e->getMessage();
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }

    /**
     * Customer Profile.
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-12
     */
    public function customerProfile($data){
        $error_messages = [];
        $results        = [];
        $customer_obj   = null;
        $customer_arr   = [];
        $artist_id      = isset($data['artist_id']) ? trim($data['artist_id']) : '';

        try  {
            // Find Customer ID from Token
            $customer_id = $this->jwtauth->customerIdFromToken();

            // Get Customer Profile
            $results = $this->getCustomerProfileForArtist($customer_id, $artist_id);
        }
        catch (\Exception $e) {
            $error_messages[] = $e->getMessage();
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Customer Profile Update.
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function customerUpdateProfile($request){
        $error_messages = [];
        $results        = [];
        $data           = [];
        $customer_obj   = null;
        $customer_arr   = [];
        $customer_id    = null;
        $artist_id      = isset($request['artist_id']) ? trim($request['artist_id']) : '';
        $data           = array_except($request->all(), ['artist_id', 'platform', 'photo', 'password_confirmation', 'email', 'password']);

        // Sanitize data
        $data = $this->sanitizedUserData($data);

        if ($request->hasFile('photo')) {
            $parmas = [
                        'file' => $request->file('photo'),
                        'type' => 'customerprofile'
                    ];
            $photo  = $this->kraken->uploadToAws($parmas);
            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                array_set($data, 'photo', $photo['results']);
                array_set($data, 'picture', $photo['results']['cover']);
            }
        }

        try  {
            // Find Customer ID
            $customer_id = $this->jwtauth->customerIdFromToken();

            // Update Customer Data
            $this->updateCustomerProfile($customer_id, $data);

            // Get Customer Profile
            $results = $this->getCustomerProfileForArtist($customer_id, $artist_id);

            if($results) {
                $results = apply_cloudfront_url($results);
            }

        }
        catch (\Exception $e) {
            $error_messages[] = $e->getMessage();
        }

        if($error_messages) {
            $results['status_code'] = 200;
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Customer Profile Picture Update.
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-1
     */
    public function customerUpdateProfilePicture($request){
        $error_messages = [];
        $results        = [];
        $data           = [];
        $customer_obj   = null;
        $customer_arr   = [];
        $customer_id    = null;
        $artist_id      = isset($request['artist_id']) ? trim($request['artist_id']) : '';

        if ($request->hasFile('photo')) {
            $parmas = [
                        'file' => $request->file('photo'),
                        'type' => 'customerprofile'
                    ];
            $photo  = $this->kraken->uploadToAws($parmas);
            if(!empty($photo) && !empty($photo['success']) && $photo['success'] === true && !empty($photo['results'])){
                array_set($data, 'photo', $photo['results']);
                array_set($data, 'picture', $photo['results']['cover']);
            }
        }

        try  {
            // Find Customer ID
            $customer_id = $this->jwtauth->customerIdFromToken();

            // Update Customer Data
            $this->updateCustomerProfile($customer_id, $data);

            // Get Customer Profile
            $results = $this->getCustomerProfileForArtist($customer_id, $artist_id);

            if($results) {
                $results = apply_cloudfront_url($results);
            }
        }
        catch (\Exception $e) {
            $error_messages[] = $e->getMessage();
        }

        if($error_messages) {
            $results['status_code'] = 200;
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Customer Profile Mobile Update.
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-28
     */
    public function customerUpdateProfileMobile($data){
        $error_messages = [];
        $results        = [];
        $customer_obj   = null;
        $customer_arr   = [];
        $customer_id    = null;
        $artist_id      = isset($data['artist_id']) ? trim($data['artist_id']) : '';
        $update_data    = [];

        try  {
            // First Validate Mobile OTP
            $otp_validate  = $this->customersms->validateOtp($data);

            // Find Customer ID
            $customer_id = $this->jwtauth->customerIdFromToken();

            // Update Customer Data
            $this->updateCustomerProfile($customer_id, $data);

            // Get Customer Profile
            $results = $this->getCustomerProfileForArtist($customer_id, $artist_id);

            if($results) {
                $results = apply_cloudfront_url($results);
            }
        }
        catch (\Exception $e) {
            $error_messages[] = $e->getMessage();
        }

        if($error_messages) {
            $results['status_code'] = 200;
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Update Customer Profile data
     *
     * @param   string  customer_id Customer Id
     * @param   array   data        Customer Profile data
     * @param   array   force_include_atts Forcefully Update attributes
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-28
     */
    public function updateCustomerProfile($customer_id, $data, $force_include_atts = []) {
        $ret = true;
        $error_message  = '';
        $updata_data    = [];
        $exclude_atts   = $this->getCustomerDataExcludeAttributes($force_include_atts);

        // Find Customer By Id
        $customer_obj = \App\Models\Customer::where('_id', trim($customer_id))->first();

        if($customer_obj) {

            if(isset($data['mobile']) || isset($data['mobile_country_code'])) {
                // Incase of updating mobile number check whether new mobile no. is already
                // assocatiated with other account or not
                $mobile                 = $data['mobile'];
                $mobile_country_code    = isset($data['mobile_country_code']) ? $data['mobile_country_code'] : '';

                $this->isCustomerMobileNotAssociatedWithOther($customer_id, $mobile, $mobile_country_code);
                $data['mobile_verified'] = 'true';
            }

            if(isset($data['dob']) && $data['dob'] == ""){
                unset($data['dob']);
            }

            // Update Customer Profile Data in database
            $updata_data = array_except($data, $exclude_atts);
            $is_update = $customer_obj->update($updata_data);


            if($is_update) {
                $ret = $customer_obj;
            }

            // Purge Cache : account_profile
            // Purge Cache : account_by_mobile
            $cache_params   = [];
            $cache_params['customer_id']    = $customer_id;

            $purge_result   = $this->cache->purgeAccountCustomerCache($cache_params);
        }
        else {
            $error_message = 'Customer not found in database';
            $ret = false;
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Customer Meta Ids.
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-20
     */
    public function customerMetaIds($data){
        $error_messages = [];
        $results        = [];
        $customer_obj   = null;
        $customer_arr   = [];
        $artist_id      = isset($data['artist_id']) ? trim($data['artist_id']) : '';

        try  {
            // Find Customer ID from
            $customer_id = $this->jwtauth->customerIdFromToken();

            // Get Customer Meta Ids
            $results = $this->getCustomerMetaIdsForArtist($customer_id, $artist_id);

            if($results) {
                $results = apply_cloudfront_url($results);
            }

        }
        catch (\Exception $e) {
            $error_messages[] = $e->getMessage();
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Check whether customer email is registered in system or not
     *
     * @param   string $email
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function isCustomerEmailNotRegistered($email) {
        $ret = true;
        $error_message = '';

        $customer_obj = \App\Models\Customer::where('email', $email)->first();
        if($customer_obj) {
            if(isset($customer_obj->status)) {
                switch (strtolower($customer_obj->status)) {
                    case 'active':
                        // Customer email is registered and active
                        $error_message = 'Email is already registered';
                        break;
                    case 'unverifed':
                        if(isset($customer_obj->email_verified) && $customer_obj->email_verified == 'false') {
                            $error_message = 'Email verification pending';
                        }
                        else {
                            $error_message = 'Your account associated with email has been suspended temporarily';
                        }
                        break;
                    case 'inactive':
                    default:
                        $error_message = 'Your account associated with email has been suspended temporarily';
                        break;
                }
            }
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Check whether customer mobile no. is registered in system or not
     *
     * @param   integer mobile
     * @param   integer mobile_country_code
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function isCustomerMobileNotRegistered($mobile, $mobile_country_code='') {
        $ret = true;
        $error_message = '';

        $customer_query = \App\Models\Customer::where('mobile', $mobile);

        if($mobile_country_code) {
            $customer_query->where('mobile_country_code', $mobile_country_code);
        }
        $customer_obj   = $customer_query->first();
        if($customer_obj) {
            $ret = false;
            if(isset($customer_obj->status) && $customer_obj->status != 'active') {
                $error_message = 'Your account associated with mobile has been suspended temporarily';
            }
        }
        else {
            $ret = false;
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Check whether customer mobile no. is not associated with other customer
     *
     * @param   string  customer_id
     * @param   integer mobile
     * @param   integer mobile_country_code
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-28
     */
    public function isCustomerMobileNotAssociatedWithOther($customer_id, $mobile, $mobile_country_code='') {
        $ret = true;
        $error_message = '';

        $customer_query = \App\Models\Customer::where('mobile', $mobile);

        $customer_query->where('_id', '<>', $customer_id);

        if($mobile_country_code) {
            $customer_query->where('mobile_country_code', intval($mobile_country_code));
        }

        $customer_obj   = $customer_query->first();

        if($customer_obj) {
            if(isset($customer_obj->status)) {
                switch (trim(strtolower($customer_obj->status))) {
                    case 'active':
                        $ret = false;
                        $error_message = 'Given Mobile number is already associated with other account.';
                        break;

                    case 'inactive':
                        $error_message = '';
                    default:
                        # code...
                        break;
                }
            }
        }
        else {
            $ret = true;
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }

    /**
     * Check whether customer is active or not
     *
     * @param   array $customer
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function isCustomerActive($customer) {
        $ret = true;
        $error_message = '';

        if ($customer && isset($customer['status']) && $customer['status'] != 'active') {
            $error_message = 'Your account has been suspended temporarily';
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Verify customer password
     *
     * @param   string  $password
     * @param   array   $customer
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function verifyCustomerPassword($password, $customer) {
        $ret = true;
        $error_message = '';
        if(isset($customer['password'])) {
            $hash_verified = Hash::check($password, trim($customer['password']));
            if (!$hash_verified) {
                $error_message = 'Invalid credentials. Please try again with different/correct credentials';
            }
        }
        else {
            $error_message = 'Password is not set for customer';
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }

    /**
     * Return customer detial
     *
     * @param   string  $id
     *
     * @return  array   Customer Detail
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-27
     */
    public function getCustomerById($customer_id, $is_active = true) {
        $ret = true;
        $error_message  = '';


        try {
            // First Check in Redis whether mobile no. is assoicated with any account or not
            $cache_params   = [];
            $hash_name      = env_cache(Config::get('cache.hash_keys.account_profile') . $customer_id);
            $hash_field     = 'db';
            $cache_miss     = false;

            $cache_params['hash_name']   =  $hash_name;
            $cache_params['hash_field']  =  (string) $hash_field;
            $cache_params['expire_time'] =  $this->cache_expire_time;

            $ret  = $this->cache->getHashData($cache_params);

            if(!$ret) {
                // Find customer id from database
                $customer_obj = \App\Models\Customer::where('_id', $customer_id)->first();

                if($customer_obj) {
                    if($is_active  && isset($customer_obj->status) && $customer_obj->status != 'active') {
                        $error_message = 'Your account has been suspended temporarily';
                    }
                    else {
                        $ret = $customer_obj->toArray();
                        $ret['password'] = $customer_obj->password;
                        $cache_params['hash_field_value']   = $ret;

                        // Save in customer id and mobile relationship in cache
                        $this->cache->saveHashData($cache_params);

                        if($ret) {
                            $ret = apply_cloudfront_url($ret);
                        }
                    }
                }
                else {
                    $error_message = 'Account does not exists';
                }
            }
        }
        catch (\Exception $e) {
            $error_messages[] = $e->getMessage();
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }

    /**
     * Return customer detial
     *
     * @param   string  $email
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-13
     */
    public function getCustomerByEmail($email, $is_active = true) {
        $ret = true;
        $error_message = '';

        $customer_obj = \App\Models\Customer::where('email', $email)->first();
        if($customer_obj) {
            if($is_active  && isset($customer_obj->status) && $customer_obj->status != 'active') {
                $error_message = 'Your account associated with email has been suspended temporarily';
            }
            else {
                $ret = $customer_obj->toArray();
            }
        }
        else {
            $error_message = "The email address entered doesn't exist";
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Return customer Id  by mobile
     *
     * @param   string  $mobile
     * @param   string  $mobile_country_code
     * @param   boolean $is_active
     *
     * @return  string  Customer Id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-27
     */
    public function getCustomerIdByMobile($mobile, $mobile_country_code = 91, $is_active = true) {
        $ret = true;
        $error_message  = '';
        $customer_id    = null;

        // Extact Only Numbers
        $mobile_country_code= (int) filter_var($mobile_country_code, FILTER_SANITIZE_NUMBER_INT);

        try {
            // Check Mobile number is valid or not
            $isvalid = self::isValidMobileNumber($mobile);

            if($isvalid) {
                // First Check in Redis whether mobile no. is assoicated with any account or not
                $cache_params   = [];
                $hash_name      = env_cache(Config::get('cache.hash_keys.account_by_mobile'));
                $hash_field     = self::formattedMobileNumber($mobile, $mobile_country_code);
                $cache_miss     = false;

                $cache_params['hash_name']   =  $hash_name;
                $cache_params['hash_field']  =  (string) $hash_field;
                $cache_params['expire_time'] =  $this->cache_expire_time;

                $ret  = $this->cache->getHashData($cache_params);

                if(!$ret) {
                    // Find customer id from database
                    $customer_obj = \App\Models\Customer::where('mobile', $mobile)->where('mobile_country_code', $mobile_country_code)->first(['_id', 'status']);

                    if($customer_obj) {
                        if($is_active  && isset($customer_obj->status) && $customer_obj->status != 'active') {
                            $error_message = 'Your account associated with mobile has been suspended temporarily';
                        }
                        else {
                            $ret = $customer_obj->_id;

                            $cache_params['hash_field_value']   = $ret;

                            // Save in customer id and mobile relationship in cache
                            $this->cache->saveHashData($cache_params);
                        }
                    }
                    else {
                        if($is_active) {
                            $error_message = 'Account associated with mobile does not exists';
                        }
                        else {
                            $error_message = '';
                            $ret = false;
                        }
                    }
                }
            }
            else {
                $error_message = 'Mobile number is invalid';
            }
        }
        catch (\Exception $e) {
            $error_messages[] = $e->getMessage();
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Return customer Id by email
     *
     * @param   string  $email
     * @param   boolean $is_active
     *
     * @return  string  Customer Id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-27
     */
    public function getCustomerIdByEmail($email, $is_active = true, $identity = '') {
        $ret = true;
        $error_message  = '';
        $customer_id    = null;

        $email = strtolower(trim($email));
        try {
            // First Check in Redis whether email is assoicated with any account or not
            $cache_params   = [];
            $hash_name      = env_cache(Config::get('cache.hash_keys.account_by_email'));
            $hash_field     = $email;
            $cache_miss     = false;

            $cache_params['hash_name']   =  $hash_name;
            $cache_params['hash_field']  =  (string) $hash_field;
            $cache_params['expire_time'] =  $this->cache_expire_time;

            $ret  = $this->cache->getHashData($cache_params);

            if(!$ret) {
                // Find customer id from database
                $customer_obj = \App\Models\Customer::where('email', $email)->first(['_id', 'status']);

                if($customer_obj) {
                    if($is_active  && isset($customer_obj->status) && $customer_obj->status != 'active') {
                        $error_message = 'Your account associated with email has been suspended temporarily';
                    }
                    else {
                        $ret = $customer_obj->_id;

                        $cache_params['hash_field_value']   = $ret;

                        // Save in customer id and mobile relationship in cache
                        $this->cache->saveHashData($cache_params);
                    }
                }
                else {
                    if($identity) {
                        switch (strtolower($identity)) {
                            case 'email':
                                $error_message = "The email address entered doesn't exist";
                                break;
                            default:
                                $ret = false;
                                break;
                        }
                    }
                    else {
                        $error_message = "The email address entered doesn't exist";
                    }
                }
            }
        }
        catch (\Exception $e) {
            $error_messages[] = $e->getMessage();
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Return customer profile for an artist
     *
     * @param   string  $customer_id
     * @param   string  $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function getCustomerProfileForArtist($customer_id, $artist_id) {
        $ret = array();
        $error_message = '';

        // First Check in Redis
        $cache_params   = [];
        $hash_name      = env_cache(Config::get('cache.hash_keys.account_profile') . $customer_id);
        $hash_field     = 'info';
        $cache_miss     = false;

        $cache_params['hash_name']   =  $hash_name;
        $cache_params['hash_field']  =  (string) $hash_field;
        $cache_params['expire_time'] =  $this->cache_expire_time;

        $ret  = $this->cache->getHashData($cache_params);

        if(!$ret) {
            $customer = \App\Models\Customer::where('_id', '=', $customer_id)->first();
            if(!$customer) {
                $error_message = 'Customer Profile not found in DB';
            }

            try  {
                // Get Customer Coins and XP
                $conis_xps = $this->getCustomerXpForArtist($customer_id, $artist_id);

                foreach ($conis_xps as $key => $value) {
                    $customer[$key] = $value;
                }

                // Get Customer Badges
                $customer['badges'] = $this->getCustomerBadges($customer_id, $artist_id);

                if($customer) {
                    $customer = apply_cloudfront_url($customer);
                }
                $ret['customer'] = $customer;

                $cache_params['hash_field_value'] = $ret;
                $save_to_cache  = $this->cache->saveHashData($cache_params);
                $cache_miss     = true;
                $ret            = $this->cache->getHashData($cache_params);
            }
            catch (\Exception $e) {
                $error_message .= $e->getMessage();
            }
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        $ret['cache']   = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        return $ret;
    }


    /**
     * Return customer badges
     *
     * @param   string  $customer_id
     * @param   string  $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function getCustomerBadges($customer_id, $artist_id) {
        $ret = array();
        $error_message = '';

        $base_photo_url = Config::get('product.' . env('PRODUCT') . '.s3.base_urls.photo');
        $badges = [
            [
                'name' => 'super fan',
                'level' => 1,
                'icon' => $base_photo_url . 'default/badges/super-fan.png',
                'status' => true
            ],
            [
                'name' => 'loyal fan',
                'level' => 2,
                'icon' => $base_photo_url . 'default/badges/loyal-fan.png',
                'status' => false
            ],
            [
                'name' => 'die hard',
                'level' => 3,
                'icon' => $base_photo_url . 'default/badges/die-hard-fan.png',
                'status' => false
            ],
            [
                'name' => 'top fan',
                'level' => 4,
                'icon' => $base_photo_url . 'default/badges/top-fan.png',
                'status' => false
            ]
        ];

        $ret = $badges;

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Return customer XP for an artist
     *
     * @param   string  $customer_id
     * @param   string  $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function getCustomerXpForArtist($customer_id, $artist_id) {
        $ret = [
            'xp' => 0,
            'comment_channel_name' => '',
            'gift_channel_name' => '',
        ];
        $error_message = '';

        $customer_artist = \App\Models\Customerartist::where('customer_id', $customer_id)->where('artist_id', $artist_id)->first([
            'xp', 'fan_xp', 'comment_channel_no', 'gift_channel_no'
        ]);

        if($customer_artist) {
            $ret['xp']                  = isset($customer_artist['xp']) ? $customer_artist['xp'] : 0;
            $ret['comment_channel_name']  = isset($customer_artist['comment_channel_no']) && $customer_artist['comment_channel_no'] ? $artist_id . ".c." . $customer_artist['comment_channel_no'] : $artist_id . '.c.0';
            $ret['gift_channel_name']     = isset($customer_artist['gift_channel_no']) && $customer_artist['gift_channel_no'] ? $artist_id . ".g." . $customer_artist['gift_channel_no'] : $artist_id . '.g.0';
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Return customer coinxp
     *
     * @param   array   $customer
     * @param   string  $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function getCustomerCoinxp($customer, $artist_id) {
        $ret = [];
        $error_message = '';
        $customer_id = isset($customer['_id']) ? $customer['_id'] : '';

        if($customer_id) {
            // Get Customer XP for artist
            try {
                $ret = $this->getCustomerXpForArtist($customer_id, $artist_id);
            }
            catch (\Exception $e) {
                $error_message = $e->getMessage();
            }

            $ret['coins'] = isset($customer['coins']) ? $customer['coins'] : 0;
        }
        else {
            $error_message = 'Something went wrong while find get customer coinxp';
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Return customer meta ids for an artist
     *
     * @param   array   $customer
     * @param   string  $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function getCustomerMetaIdsForArtist($customer_id, $artist_id) {
        $results        = [];
        $error_messages = [];

        // First Check in Redis
        $cache_params   = [];
        $hash_name      = env_cache(Config::get('cache.hash_keys.account_profile') . $customer_id);
        $hash_field     = $artist_id;
        $cache_miss     = false;

        $cache_params['hash_name']   =  $hash_name;
        $cache_params['hash_field']  =  (string) $hash_field;
        $cache_params['expire_time'] =  $this->cache_expire_time;

        $results  = $this->cache->getHashData($cache_params);

        if(!$results) {
            try  {
                // Get Customer Meta Ids
                $items = $this->generateCustomerMetaIdsForArtist($customer_id, $artist_id);
                $cache_params['hash_field_value'] = $items;
                $save_to_cache  = $this->cache->saveHashData($cache_params);
                $cache_miss     = true;
                $results        = $this->cache->getHashData($cache_params);
            }
            catch (\Exception $e) {
                $error_messages[] = $e->getMessage();
            }
        }

        $results['cache']    = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        return $results;
    }


    /**
     * Generates customer meta ids data for an artist
     *
     * @param   array   $customer
     * @param   string  $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-07-16
     */
    public function generateCustomerMetaIdsForArtist($customer_id, $artist_id) {
        $ret = [
            'like_content_ids'      => [],
            'purchase_content_ids'  => [],
            'block_content_ids'     => [],
            'block_comment_ids'     => [],
            'purchase_stickers'     => false,
            'purchase_live_ids'     => [],
        ];

        $error_message = '';

        $customer = $this->getCustomerProfileForArtist($customer_id, $artist_id);

        // Purchase Content Ids
        $purchase_content_ids  = $this->getCustomerPurchaseEntityIdsForArtist($customer_id, $artist_id, 'contents');
        if($purchase_content_ids) {
            $ret['purchase_content_ids'] = $purchase_content_ids;
        }

        // Like Content Ids
        $like_content_ids  = $this->getCustomerLikeContentIdsForArtist($customer_id, $artist_id);
        if($like_content_ids) {
            $ret['like_content_ids'] = $like_content_ids;
        }

        // Purchase Live Ids
        $purchase_live_ids  = $this->getCustomerPurchaseEntityIdsForArtist($customer_id, $artist_id, 'lives');
        if($purchase_live_ids) {
            $ret['purchase_live_ids'] = $purchase_live_ids;
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Return Customer Purchase Entity ids for an artist
     *
     * @param   string  $customer_id
     * @param   string  $artist_id
     * @param   string  $entity     contents | lives
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-11
     */
    public function getCustomerPurchaseEntityIdsForArtist($customer_id, $artist_id, $entity = 'contents') {
        $ret = [];
        $error_message = '';
        $purchase_content_ids = [];
        $passbook_purchase_content_ids = [];

        $purchase_entities = \App\Models\Purchase::where('customer_id', $customer_id)->where('entity', $entity)->where('artist_id', $artist_id)->lists('entity_id');
        if($purchase_entities) {
            $purchase_entity_ids = $purchase_entities->toArray();
        }

        $passbook_purchase_entities = \App\Models\Passbook::where('customer_id', '=', $customer_id)->where('entity', $entity)->where('artist_id', $artist_id)->lists('entity_id');
        if($passbook_purchase_entities) {
            $passbook_purchase_entity_ids = $passbook_purchase_entities->toArray();
        }

        $ret = array_values(
            array_unique(
                array_merge($purchase_entity_ids, $passbook_purchase_entity_ids)
            )
        );

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Return Customer Like content ids for an artist
     *
     * @param   string  $customer_id
     * @param   string  $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-20
     */
    public function getCustomerLikeContentIdsForArtist($customer_id, $artist_id) {
        $ret = [];
        $error_message = '';
        $like_content_ids = [];

        $like_contents = \App\Models\Like::where('customer_id', '=', $customer_id)->where('entity', 'content')->where("status", "active")->where('artist_id', $artist_id)->get(['type', 'entity_id']);
        if($like_contents) {
            foreach ($like_contents as $key => $entity) {
                $ret[] = [
                    'id' => $entity->entity_id,
                    'type' => (isset($entity->type) ? $entity->type : 'normal'),
                ];
            }
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Send Welcome Mail to customer
     *
     * @param   array   $customer_data
     * @param   string  $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-13
     */
    public function sendWelcomeMailToCustomer($customer_data, $artist_id='') {
        $ret = true;
        $error_message = '';
        $email  = isset($customer_data['email']) ? $customer_data['email'] : '';
        $name   = isset($customer_data['first_name']) ? $customer_data['first_name'] : '';
        $name   .= isset($customer_data['last_name']) ? ' '. $customer_data['last_name'] : '';
        $name   = trim($name);

        if($email) {
            $welcome_data = [];

            // Get Email Default Template Data
            $welcome_data = $this->service_artist->getEmailTemplateDefaultData($artist_id);

            // Generate Email Template specific data
            $welcome_data['customer_email'] = $email;
            $welcome_data['customer_name']  = ($name) ? $name : 'user';

            // Add Email Template data
            $welcome_data['email_header_template']  = 'emails.' . env('PRODUCT') . '.common.header';
            $welcome_data['email_body_template']    = 'emails.' . env('PRODUCT') . '.customer.welcome';
            $welcome_data['email_footer_template']  = 'emails.' . env('PRODUCT') . '.common.footer';
            $welcome_data['email_subject']          = 'Welcome to BollyFame World';

            $job_data = [
                'label'     => 'CustomerWelcome',
                'type'      => 'process_email',
                'payload'   => $welcome_data,
                'status'    => 'scheduled',
                'delay'     => 0,
                'retries'   => 0
            ];

            $recodset = new \App\Models\Job($job_data);
            $recodset->save();
            //$send_welcome_mail = $this->mailer->sendWelcome($welcome_data);
        }
        else {
            $error_message = 'Customer email is missing';
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Send Forgot Password Mail to customer
     *
     * @param   array   $customer_data
     * @param   string  $new_password
     * @param   string  $artist_id
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-13
     */
    public function sendForgotPasswordMailToCustomer($customer_data, $new_password, $artist_id='') {
        $ret = true;
        $error_message = '';
        $email  = isset($customer_data['email']) ? $customer_data['email'] : '';
        $name   = isset($customer_data['first_name']) ? $customer_data['first_name'] : '';
        $name   .= isset($customer_data['last_name']) ? ' '. $customer_data['last_name'] : '';
        $name   = trim($name);

        if($email) {
            $send_data = [];

            // Get Email Default Template Data
            $send_data = $this->service_artist->getEmailTemplateDefaultData($artist_id);

            // Generate Email Template specific data
            $send_data['customer_email']    = $email;
            $send_data['customer_name']     = ($name) ? $name : 'user';
            $send_data['password']          = $new_password;
            $send_welcome_mail = $this->mailer->sendForgotPassword($send_data);
        }
        else {
            $error_message = 'Customer email is missing';
        }

        if($error_message) {
            throw new \Exception($error_message);
        }

        return $ret;
    }


    /**
     * Delete Test Customer By Email
     *
     * @return
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-29
     */
    public function deleteTestCustomer($data){
        $error_messages = [];
        $results        = [];
        $customer_obj   = null;
        $customer_arr   = [];
        $artist_id      = isset($data['artist_id']) ? trim($data['artist_id']) : '';
        $email          = isset($data['email']) ? strtolower(trim($data['email']))  : '';
        $passbook_entries_deleted = 0;

        $test_customer_emails = [
            'chandrashekhar.thalkar@bollyfame.com',
            'rd341652@gmail.com',
            'rohit.desai@bollyfame.com',
            'vijay.singh@bollyfame.com',
            'singhvijay9757@gmail.com',
            'ashwini.mhavarkar@bollyfame.com',
            'mhavarkarashwini@gmail.co',
            'akshay.harale@bollyfame.com',
            'kamlesh.suthar@bollyfame.com',
            'shrikant.tiwari@bollyfame.com',
        ];

        try  {
            if(in_array($email, $test_customer_emails)) {
                // Find Customer Id associated with email
                $customer_id = $this->getCustomerIdByEmail($email, false);

                // First Delete all Passbook Entries assocaiated with customer
                $passbook_entries = \App\Models\Passbook::where('customer_id', $customer_id)->get();
                if($passbook_entries) {
                    foreach ($passbook_entries as $key => $passbook) {
                        $passbook->delete();
                        $passbook_entries_deleted++;
                    }
                }

                // Then Delete Customer Data
                $customer = \App\Models\Customer::where('_id', $customer_id)->first();
                if($customer) {
                    $customer->delete();

                    // Delete Cache
                    // Purge Cache : account_profile
                    // Purge Cache : account_by_mobile
                    $cache_params   = [];
                    $cache_params['customer_id']    = $customer_id;

                    $purge_result   = $this->cache->purgeAccountCustomerCache($cache_params);
                }
            }
            else {
                $error_messages[] = 'Provide email is not test customer';
            }

            $results = null;

            if($passbook_entries_deleted) {
                $results['passbook_entries_deleted'] = $passbook_entries_deleted;
            }
        }
        catch (\Exception $e) {
            $error_messages[] = $e->getMessage();
        }

        if($error_messages) {
            $results['status_code'] = 200;
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

}
