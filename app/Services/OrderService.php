<?php

namespace App\Services;

use Carbon\Carbon;
use Input;
use Redirect;
use Config;
use Session;
use Cache;
use Log;
use App\Repositories\Contracts\OrderInterface;
use App\Repositories\Contracts\CustomerInterface;
use App\Repositories\Contracts\PackageInterface;
use App\Repositories\Contracts\CustomerActivityInterface;
use App\Models\Order as Order;
use App\Services\Gcp;
use App\Services\Jwtauth;
use ReceiptValidator\GooglePlay\Validator as PlayValidator;
use ReceiptValidator\iTunes\Validator as iTunesValidator;
use ReceiptValidator\iTunes\Response as ValidatorResponse;
use App\Services\CachingService;
use App\Services\RedisDb;
use App\Services\Cache\AwsElasticCacheRedis;

use App\Services\PassbookService;
use App\Services\ArtistService;
use App\Services\Payment\Razorpay;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;


//https://github.com/Paytm-Payments/Paytm_Web_Sample_Kit_PHP/blob/master/PaytmKit/lib/encdec_paytm.php

require_once(public_path("paytm/lib/config_paytm.php"));
require_once(public_path("paytm/lib/encdec_paytm.php"));


class OrderService
{
    protected $gcp;
    protected $jwtauth;
    protected $order;
    protected $customerRep;
    protected $packageRep;
    protected $activityRep;
    protected $orderRep;
    protected $caching;
    protected $redisdb;
    protected $awsElasticCacheRedis;
    protected $passbookService;
    protected $artistService;


    public function __construct(
        Order $order,
        OrderInterface $orderRep,
        Gcp $gcp,
        Jwtauth $jwtauth,
        CustomerInterface $customerRep,
        PackageInterface $packageRep,
        CustomerActivityInterface $activityRep,
        CachingService $caching,
        RedisDb $redisdb,
        AwsElasticCacheRedis $awsElasticCacheRedis,
        PassbookService $passbookService,
        ArtistService $artistService
    )
    {
        $this->gcp = $gcp;
        $this->jwtauth = $jwtauth;
        $this->order = $order;
        $this->orderRep = $orderRep;
        $this->customerRep = $customerRep;
        $this->packageRep = $packageRep;
        $this->activityRep = $activityRep;
        $this->caching = $caching;
        $this->redisdb = $redisdb;
        $this->awsElasticCacheRedis = $awsElasticCacheRedis;
        $this->passbookService = $passbookService;
        $this->artistService = $artistService;
    }


    public function index($request)
    {
        $requestData = $request->all();
        $results = $this->orderRep->index($requestData);
        return $results;
    }


    public function generateTmpOrder($request)
    {
        $error_messages = $results = [];
        $data = $request->all();
        $orderData = array_only($data, ['vendor', 'package_id', 'transaction_price', 'currency_code']);
        $package_id = $request['package_id'];
        $artist_id = $request['artist_id'];
        $platform = strtolower(trim($request['platform']));
        $customer_id = $this->jwtauth->customerIdFromToken();
        $selected_package = $this->packageRep->find($package_id);

        if (empty($error_messages && $customer_id != NULL && $artist_id != NULL && $selected_package != NULL)) {

            $selected_packageXps = (isset($selected_package['xp'])) ? intval($selected_package['xp']) : 0;
            $selected_packageCoins = (isset($selected_package['coins'])) ? float_value($selected_package['coins']) : 0;
            $selected_packagePrice = (isset($selected_package['price'])) ? float_value($selected_package['price']) : 0;
            $selected_packageSku = (isset($selected_package['sku'])) ? trim($selected_package['sku']) : 0;
            $transaction_price = float_value($request['transaction_price']);
            $vendor = strtolower(trim($request['vendor']));
            $currency_code = trim($request['currency_code']);

            array_set($orderData, 'customer_id', $customer_id);
            array_set($orderData, 'artist_id', $artist_id);
            array_set($orderData, 'package_sku', $selected_packageSku);
            array_set($orderData, 'package_coins', $selected_packageCoins);
            array_set($orderData, 'package_xp', $selected_packageXps);
            array_set($orderData, 'package_price', float_value($selected_packagePrice));
            array_set($orderData, 'transaction_price', $transaction_price);
            array_set($orderData, 'currency_code', $currency_code);
            array_set($orderData, 'platform', $platform);
            array_set($orderData, 'vendor', $vendor);
            array_set($orderData, 'order_status', 'pending');
            $orderObj = $this->orderRep->store($orderData);

            $customer_id = $orderData['customer_id'];
            $cachetag_name = $customer_id . "_customerpurchases";
            $env_cachetag = env_cache_tag_key($cachetag_name);              //  ENV_customerpurchases
            $this->caching->flushTag($env_cachetag);

            $results = $orderObj;
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function captureOrderStatus($request)
    {
        $error_messages = [];
        $results = [];
        $data = $request->all();
//        $orderData                      =   array_only($data,['order_id','vendor','vendor_order_id','purchase_key']);
        $orderData = array_only($data, ['order_id', 'vendor_order_id', 'purchase_key']);

        $order_id = $request['order_id'];
        $platform = $request['platform'];
        $vendor_order_id = ($orderData['vendor_order_id']) ? trim($orderData['vendor_order_id']) : '';


        $temp_order = $this->orderRep->find($order_id);

        if (!$temp_order) {
            $error_messages[] = 'Temp order does not exist';
        }


        if ($temp_order && isset($temp_order['order_status']) && $temp_order['order_status'] == 'successful') {
            $error_messages[] = 'Order status already successful';
        }

        $vendor = ($temp_order['vendor']) ? trim($temp_order['vendor']) : '';

        if ($vendor != '' && $vendor == 'google_wallet' && $vendor_order_id != '') {
            //$vendor_order_id    =   "GPA.3321-6500-8214-46523";

            $vendor_order_ARR = explode(".", $vendor_order_id);
            if (count($vendor_order_ARR) != 2) {
                if (empty($error_messages)) {
                    $error_messages[] = 'Invaild vendor order id';
                }
                return ['error_messages' => $error_messages, 'results' => $results];
            }
            $vendor_order_part1 = $vendor_order_ARR[0];
            if ($vendor_order_part1 != 'GPA') {
                if (empty($error_messages)) {
                    $error_messages[] = 'Invaild vendor order id';
                }
                return ['error_messages' => $error_messages, 'results' => $results];
            }
            $vendor_order_part2 = $vendor_order_ARR[1];
            $vendor_order_PART2ARR = explode("-", $vendor_order_part2);
            if (count($vendor_order_PART2ARR) != 4) {
                if (empty($error_messages)) {
                    $error_messages[] = 'Invaild vendor order id';
                }
                return ['error_messages' => $error_messages, 'results' => $results];
            }

            foreach ($vendor_order_PART2ARR as $key => $value) {

                if ($key == 3) {
                    if (strlen($value) != 5) {
                        if (empty($error_messages)) {
                            $error_messages[] = 'Invaild vendor order id';
                        }
                        return ['error_messages' => $error_messages, 'results' => $results];
                    }
                } else {
                    if (strlen($value) != 4) {
                        if (empty($error_messages)) {
                            $error_messages[] = 'Invaild vendor order id';
                        }
                        return ['error_messages' => $error_messages, 'results' => $results];
                    }
                }

            }
        }


        // Can manage for error status here
        if (empty($error_messages) && $temp_order != NULL) {

            $package_id = $temp_order['package_id'];
            $artist_id = trim($temp_order['artist_id']);
            $customer_id = trim($temp_order['customer_id']);
            $order_coins = (isset($temp_order['package_coins'])) ? intval($temp_order['package_coins']) : 0;
            $order_xp = (isset($temp_order['package_xp'])) ? intval($temp_order['package_xp']) : 0;
            $order_package_price = (isset($temp_order['package_price'])) ? intval($temp_order['package_price']) : 0;
            $order_transaction_price = (isset($temp_order['transaction_price'])) ? float_value($temp_order['transaction_price']) : 0;

            if (isset($temp_order['vendor']) && $temp_order['vendor'] == '') {
                array_set($orderData, 'vendor', strtolower(trim($data['vendor'])));
            }

            $customerObj = \App\Models\Customer::where('_id', $customer_id)->first();
            $coins_before_purchase = (isset($customerObj->coins)) ? $customerObj->coins : 0;
            $coins_after_purchase = (isset($customerObj->coins)) ? $customerObj->coins + $order_coins : 0;

            array_set($orderData, 'order_status', 'successful');
            array_set($orderData, 'coins_before_purchase', $coins_before_purchase);
            array_set($orderData, 'coins_after_purchase', $coins_after_purchase);


            $updateOrderObj = $this->orderRep->update($orderData, $order_id);

            $customer_id = $updateOrderObj['customer_id'];
            $cachetag_name = $customer_id . "_customerpurchases";
            $env_cachetag = env_cache_tag_key($cachetag_name);              //  ENV_customerpurchases
            $this->caching->flushTag($env_cachetag);

            if ($updateOrderObj) {
                // Update Customer Coins
                $customerObj = $this->customerRep->coinsDeposit($customer_id, $order_coins);

                // Update Customer XP
                $customerXpObj = $this->customerRep->xpDeposit($customer_id, $artist_id, $order_xp);

                $coins = !empty($customerObj['coins']) ? $customerObj['coins'] : 0;
                $this->redisdb->saveCustomerCoins($customer_id, $coins);

                // Save as CUSTOMER ACTIVITES Event
                $activityData = [
                    'name' => 'purchase_package',
                    'customer_id' => $customer_id,
                    'artist_id' => $artist_id,
                    'entity' => 'orders',
                    'entity_id' => $order_id,
                    'platform' => $platform,
                    'package_id' => $package_id,
                    'package_price' => $order_package_price,
                    'transaction_price' => float_value($order_transaction_price),
                    'coins' => $order_coins,
                    'xp' => $order_xp,
                ];
//                $activityObj                    =   $this->activityRep->store($activityData);
                $order = $this->orderRep->find($order_id);
                $order['available_coins'] = $coins;
                $results = $order;
            }
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function getPurchasePackageHistory($request)
    {
        $error_messages = $results = [];
        $customer_id = $this->jwtauth->customerIdFromToken();
        $request['customer_id'] = $customer_id;
        $results = $this->orderRep->getPurchasePackageHistory($request);

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function getPurchasePackageHistoryLists($request)
    {

        $error_messages = $results = [];
        $customer_id = $this->jwtauth->customerIdFromToken();
        $request['customer_id'] = $customer_id;

        $page = (isset($request['page']) && $request['page'] != '') ? trim($request['page']) : '1';
        $artistid = (isset($request['artist_id']) && $request['artist_id'] != '') ? trim($request['artist_id']) : '';


        $cacheParams    =   [];
        $hash_name      =   env_cache(Config::get('cache.hash_keys.customer_purchase_packages_lists').$customer_id);
        $hash_field     =   $page;
        $cache_miss     =   false;

        $cacheParams['hash_name']   =  $hash_name;
        $cacheParams['hash_field']  =  (string)$hash_field;


        $results = $responses = $this->awsElasticCacheRedis->getHashData($cacheParams);
        if (empty($results)) {
            $responses = $this->orderRep->getPurchasePackageHistoryLists($request);
            $items = ($responses) ? apply_cloudfront_url($responses) : [];
            $cacheParams['hash_field_value'] = $items;
            $saveToCache = $this->awsElasticCacheRedis->saveHashData($cacheParams);
            $cache_miss = true;
            $results  = $this->awsElasticCacheRedis->getHashData($cacheParams);
        }

        $results['cache']    = ['hash_name' => $hash_name, 'hash_field' => $hash_field, 'cache_miss' => $cache_miss];

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function store($request)
    {
        $results = [];
        $error_messages = [];
        $data = $request->all();
        $package_id = $request['package_id'];
        $artist_id = $request['artist_id'];
        $platform = 'admin';
        $customer_id = $request['customer_id'];
        $selected_package = $this->packageRep->find($package_id);

        if (empty($error_messages && $selected_package != NULL)) {

            $selected_packageSku = (isset($selected_package['sku'])) ? ($selected_package['sku']) : 0;
            $selected_packagePrice = (isset($selected_package['price'])) ? float_value($selected_package['price']) : 0;
            $selected_packageCoins = (isset($selected_package['coins'])) ? ($selected_package['coins']) : 0;

            $selected_packageXp = (isset($selected_package['xp'])) ? ($selected_package['xp']) : 0;
            array_set($data, 'customer_id', $customer_id);
            array_set($data, 'artist_id', $artist_id);
            array_set($data, 'platform', $platform);
            array_set($data, 'sku', $selected_packageSku);
            array_set($data, 'price', $selected_packagePrice);
            array_set($data, 'coins', $selected_packageCoins);
            array_set($data, 'xp', $selected_packageXp);
        } else {
            $error_messages[] = 'Wrong Package';
        }

        //print_pretty($data);exit;
        if (empty($error_messages)) {
            $results['order'] = $this->orderRep->store($data);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function destroy($id)
    {
        $results = $this->orderRep->destroy($id);
        return $results;
    }


    public function update_order($requestOrderId)
    {
        $results = $this->orderRep->update_order($requestOrderId);
        return $results;
    }


    public function VadidateIosPurchaseStatus($requestData)
    {
        $error_messages = $results = [];

        $receiptBase64Data = (isset($requestData['receipt']) && $requestData['receipt'] != '') ? $requestData['receipt'] : '';
        $env = (isset($requestData['env']) && $requestData['env'] != '') ? strtolower(trim($requestData['env'])) : 'production';

        if ($env == 'production') {
            $validator = new iTunesValidator(iTunesValidator::ENDPOINT_PRODUCTION); // Or iTunesValidator::ENDPOINT_SANDBOX if sandbox testing
        } else {
            $validator = new iTunesValidator(iTunesValidator::ENDPOINT_SANDBOX); // Or iTunesValidator::ENDPOINT_SANDBOX if sandbox testing
        }

        $response = null;

        try {

            $response = $validator->setReceiptData($receiptBase64Data)->validate();

        } catch (\Exception $e) {
            $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            $results['error'] = $message;
            $results['valid_transaction'] = 0;
            Log::info('VadidateIosPurchaseStatus Error ===>', $message);
        }

        //johnd@gmail.com ios test users

//        var_dump($response->getResultCode());exit;

        if ($response instanceof ValidatorResponse && $response->isValid()) {
//            print_b($response->getPurchases());

//            var_dump($response);var_dump($response->getPurchases());exit;
            // echo 'Receipt is valid.' . PHP_EOL;
            // echo 'getBundleId: ' . $response->getBundleId() . PHP_EOL;
            foreach ($response->getPurchases() as $purchase) {
                $results['env'] = $env;
                $results['bundleId'] = $response->getBundleId();
                $results['product_id'] = $purchase->getProductId();
                $results['transaction_id'] = $purchase->getTransactionId();
                $results['purchase_date'] = $purchase->getPurchaseDate()->toIso8601String();
                $results['valid_transaction'] = 1;
            }
        } else {

            $results['env'] = $env;
            $results['error'] = 'Receipt is not valid.Error code :' . $response->getResultCode();
            $results['valid_transaction'] = 0;

        }

        \Log::info('VadidateIosPurchaseStatus ===> ' . json_encode($results));

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function VadidateAndroidPurchaseStatus($requestData)
    {
        $error_messages = [];
        $results        = [];
        $scope          = ['https://www.googleapis.com/auth/androidpublisher'];
        $artist_id      = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? trim($requestData['artist_id']) : '';
        $ser_acc        = (isset($requestData['ser_acc']) && $requestData['ser_acc'] != '') ? strtolower(trim($requestData['ser_acc'])) : '';
        $configLocation = config_path() . '/razrcorp_service_account.json';

        //PP
        if ($artist_id != '' && $artist_id == '598aa3d2af21a2355d686de2') {
            $configLocation = config_path() . '/poonam_service_account.json';
        }

        //##########    developer@bollyfame.com - Service Account
        // Eg - Sunny
        if ($ser_acc != '' && $ser_acc == 'dev') {
            $configLocation = config_path() . '/armsprime_service_account_developer.json';
        }


        //##########    developer1@bollyfame.com - Service Account
        // Eg - Scarlett Rose, Sherlyn Chopra, Mandana Karimi
        if ($ser_acc != '' && $ser_acc == 'dev1') {
            $configLocation = config_path() . '/armsprime_service_account_developer1.json';
        }

        if ($ser_acc != '' && $ser_acc == 'bfame') {
            $configLocation = config_path() . '/hsworld_service_account_developer.json';
        }


        $client = new \Google_Client();
        $client->setApplicationName('test');
        $client->setAuthConfig($configLocation);
        $client->setScopes($scope);
        $validator      =   new PlayValidator(new \Google_Service_AndroidPublisher($client));
        $purchaseToken  =   (isset($requestData['purchase_key']) && $requestData['purchase_key'] != '') ? $requestData['purchase_key'] : '';
        $productId      =   (isset($requestData['package_sku']) && $requestData['package_sku'] != '') ? $requestData['package_sku'] : '';
        $appPackageName =   (isset($requestData['app_package_name']) && $requestData['app_package_name'] != '') ? $requestData['app_package_name'] : '';

        try {

            // if already used once then ? Need to check.

            $response = $validator->setPackageName($appPackageName)->setProductId($productId)->setPurchaseToken($purchaseToken)->validatePurchase();
            $results['valid_transaction'] = 1;
            $results['consumption_state'] = $response->getConsumptionState();
            $mili_sec = $response->getPurchaseTimeMillis();
            $seconds = $mili_sec / 1000;
            $results['purchase_date'] = date("d-m-Y", $seconds);
            $results['purchase_state'] = $response->getPurchaseState();

            \Log::info('VadidateAndroidPurchaseStatus Success ===> ' . json_encode($results));

        } catch (\Exception $e) {
            $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            $results['error'] = $message;
            $results['valid_transaction'] = 0;
            \Log::info('VadidateAndroidPurchaseStatus Error ===> ' . json_encode($message));
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function VadidatePaytmPurchaseStatus($requestData)
    {
        $error_messages = [];
        $results = [];

        $env = (isset($requestData['env'])) ? strtolower(trim($requestData['env'])) : 'test';
        $package_id = (isset($requestData['package_id']) && $requestData['package_id'] != '') ? trim($requestData['package_id']) : '';
        $postData = $requestData;
        $checkSum = "";
        $PAYTM_MERCHANT_KEY = ($env == 'prod') ? PAYTM_MERCHANT_KEY_PROD : PAYTM_MERCHANT_KEY;
        $PAYTM_MERCHANT_MID = ($env == 'prod') ? PAYTM_MERCHANT_MID_PROD : PAYTM_MERCHANT_MID;
        $paramList = array();
        $paramList["MID"] = $PAYTM_MERCHANT_MID; // 'Razrte15336433536370'; //Provided by Paytm
        $paramList["ORDER_ID"] = $postData["ORDER_ID"];  // ORDERID136408 unique OrderId for every request
        $checkSum = getChecksumFromArray($paramList, $PAYTM_MERCHANT_KEY);
        $paramList["CHECKSUMHASH"] = urlencode($checkSum);
        $data_string = 'JsonData=' . json_encode($paramList);


        $selected_package = $this->packageRep->find($package_id);

        if (!$selected_package) {
            $results['valid_transaction'] = 0;
            $results['message'] = 'Package does not exist';
            return ['error_messages' => $error_messages, 'results' => $results];
        }

        $selected_packagePrice = (isset($selected_package['price'])) ? float_value($selected_package['price']) : 0;

        try {

            $ch = curl_init(); // initiate curl

            if ($env == 'prod') {
                $url = "https://securegw.paytm.in/merchant-status/getTxnStatus "; // where you want to post data
            } else {
                $url = "https://pguat.paytm.com/oltp/HANDLER_INTERNAL/getTxnStatus"; // where you want to post data
            }
            //  $url = "https://pguat.paytm.com/oltp/HANDLER_INTERNAL/getTxnStatus"; // where you want to post data

            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);  // tell curl you want to post something
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string); // define what you want to post
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // return the output in string format
            $headers = array();
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $output = curl_exec($ch); // execute
            $info = curl_getinfo($ch);

            $results = json_decode($output, true);

            if ($results['STATUS'] == 'TXN_SUCCESS') {

                $results['valid_transaction'] = 1;

                if ($selected_packagePrice == float_value($results['TXNAMOUNT'])) {
                    $results['valid_transaction'] = 1;
                } else {
                    $results['valid_transaction'] = 0;
                    $results['message'] = 'selected package price does not match with paytm TXNAMOUNT';
                }
            } else {
                $results['valid_transaction'] = 0;
            }

        } catch (\Exception $e) {
            $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            $results['error'] = $message;
            $results['valid_transaction'] = 0;
            \Log::info('VadidateAndroidPurchaseStatus Error ===> ' . json_encode($message));
        }


        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Validate RazorPay purchase status
     *
     *  Order Payment Status
     *      created     : Payment process not initiate
     *      authorized  : Payment procees completed form end user but not captured by merchant
     *      captured    : Payment process completed
     *      refunded    :
     *      failed      :
     *
     * @param  requestData
     * @return
     *
     * @author Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since 2019-04-11
     */
    public function VadidateRazorpayPurchaseStatus($requestData)
    {
        $error_messages = [];
        $results        = [];

        $env                = (isset($requestData['env'])) ? strtolower(trim($requestData['env'])) : 'test';
        $package_id         = (isset($requestData['package_id']) && $requestData['package_id'] != '') ? trim($requestData['package_id']) : '';
        $vendor_order_id    = (isset($requestData['vendor_order_id']) && $requestData['vendor_order_id'] != '') ? trim($requestData['vendor_order_id']) : '';

        $selected_package = $this->packageRep->find($package_id);

        if (!$selected_package) {
            $results['valid_transaction'] = 0;
            $results['message'] = 'Package does not exist';
            return ['error_messages' => $error_messages, 'results' => $results];
        }

        $selected_packagePrice = (isset($selected_package['price'])) ? float_value($selected_package['price']) : 0;
        try {
            $results['valid_transaction'] = 0;

            $api_key    = ($env == 'prod') ? config('razorpay.RAZORPAY_API_KEY_PROD') : config('razorpay.RAZORPAY_API_KEY');
            $api_secret = ($env == 'prod') ? config('razorpay.RAZORPAY_API_SECRET_PROD') : config('razorpay.RAZORPAY_API_SECRET');

            $api            = new Razorpay($api_key, $api_secret);
            $order          = $api->order->fetch($vendor_order_id);
            $payments       = $api->order->fetch($vendor_order_id)->payments();

            if($payments) {
                if($payments->items && count($payments->items)) {
                    foreach ($payments->items as $key => $payment) {

                        if ($payment['status'] == 'captured') {
                            $results['TXNAMOUNT'] = $payment->amount;
                            if (($selected_packagePrice * 100) == float_value($results['TXNAMOUNT'])) {
                                $results = $payment->toArray();

                                // Save Order Notes
                                if($order->notes) {
                                    $results['notes'] = $order->notes->toArray();
                                }
                                $results['valid_transaction'] = 1;
                                break;
                            }
                            else {
                                $results['valid_transaction'] = 0;
                                $results['message'] = 'selected package price does not match with Razorpay TXNAMOUNT';
                            }
                        }
                        else {
                            $results['valid_transaction'] = 0;
                            $results['message'] = 'Razorpay transaction not captured.';
                        }
                    }
                }
                else {
                    $results['valid_transaction'] = 0;
                    $results['message'] = 'Payment transaction not completed.';
                }
            }
            else {
                $results['valid_transaction'] = 0;
                $results['message'] = 'Payment transaction process not initiate';
            }
        }
        catch (\Exception $e) {
            $message                        = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            $results['error']               = $message;
            $results['valid_transaction']   = 0;
            \Log::info(__METHOD__ . ' Error ===> ' . json_encode($message));
        }

        //print_pretty($results);exit(__METHOD__ . ' ====> ' . __LINE__);
        if($results['valid_transaction'] != 1) {
            $error_messages[] = $results['message'];
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    /**
     * Validate Paypal purchase status
     *
     *
     * @param  requestData
     * @return
     *
     * @author Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since 2019-06-20
     */
    public function VadidatePaypalPurchaseStatus($requestData) {
        $error_messages = [];
        $results        = [];
        $message        = '';
        $response_results= [];
        $paypal_response= [];

        $env                = (isset($requestData['env'])) ? strtolower(trim($requestData['env'])) : 'test';
        $package_id         = (isset($requestData['package_id']) && $requestData['package_id'] != '') ? trim($requestData['package_id']) : '';
        $vendor_order_id    = (isset($requestData['vendor_order_id']) && $requestData['vendor_order_id'] != '') ? trim($requestData['vendor_order_id']) : '';
        $currency_code      = (isset($requestData['currency_code']) && $requestData['currency_code'] != '') ? trim($requestData['currency_code']) : 'USD';

        $selected_package = $this->packageRep->find($package_id);

        if (!$selected_package) {
            $results['valid_transaction'] = 0;
            $results['message'] = 'Package does not exist';
            return ['error_messages' => $error_messages, 'results' => $results];
        }

        // Depending on currency_code set package price
        switch (trim(strtoupper($currency_code))) {
            case 'INR':
                $selected_packagePrice = (isset($selected_package['price'])) ? float_value($selected_package['price']) : 0;
                break;
            case 'USD':
            default:
                $selected_packagePrice = (isset($selected_package['price_usd'])) ? float_value($selected_package['price_usd']) : 0;
                break;
        }

        try {
            $results['valid_transaction'] = 0;

            $base_uri = '';
            if ($env == 'prod') {
                $base_uri = 'https://api.paypal.com';
            }
            else {
                $base_uri = 'https://api.sandbox.paypal.com';
            }

            $resource   = 'v2/payments/captures/' . $vendor_order_id;
            $url        = $base_uri . '/' . $resource;

            $headers = [
                // All POST requests arguments must be passed as json with the Content-Type set as application/json.
                'Content-Type' => 'application/json',
            ];

            // Get Access Token
            // Get captured payment details
            $http_client = new Client([
                                        'base_uri'  => $base_uri,
                                        'headers'   => $headers,
                                        ]);

            $paypal_client_id   = config('paypal.CLIENT_ID');
            $paypal_secret      = config('paypal.SECRET');

            try {
                $request = $http_client->request('GET', $resource, ['auth' => [$paypal_client_id, $paypal_secret]]);
                $response = $request->getBody()->getContents();
                $paypal_response = [
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
                $paypal_response = [
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
                $paypal_response = [
                    'status' => 400,
                    'reason' => $message['type'] . ' : ' . $message['message'] . ' in ' . $message['file'] . ' on ' . $message['line']
                ];
            }

            if($paypal_response) {
                \Log::info(__METHOD__ . ' PAYPAL API ===>  paypal_response' . json_encode($paypal_response));
                if(isset($paypal_response['status']) && $paypal_response['status'] == 200) {
                    if(isset($paypal_response['results'])) {
                        $results = $paypal_response['results'];

                        if(isset($results['status'])) {
                            switch ($results['status']) {
                                case 'COMPLETED':
                                    if(isset($results['amount']) && isset($results['amount']['value'])) {
                                        if ($selected_packagePrice == float_value($results['amount']['value'])) {
                                            $results['valid_transaction'] = 1;
                                        }
                                        else {
                                            $results['valid_transaction'] = 0;
                                            $results['message'] = 'selected package price does not match with paypal TXNAMOUNT';
                                        }
                                    }
                                    break;

                                case 'DECLINED':
                                    $results['message'] = 'The funds could not be captured.';
                                    break;

                                case 'PARTIALLY_REFUNDED':
                                    $results['message'] = "An amount less than this captured payment's amount was partially refunded to the payer.";
                                    break;

                                case 'PENDING':
                                    $results['message'] = " The funds for this captured payment was not yet credited to the payee's PayPal account. For more information";
                                    break;

                                case 'REFUNDED':
                                    $results['message'] = "An amount greater than or equal to this captured payment's amount was refunded to the payer.";
                                    break;
                                default:
                                    # code...
                                    break;
                            }

                        }
                    }
                }
            }
        }
        catch (\Exception $e) {
            $message                        = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            $results['error']               = $message;
            $results['valid_transaction']   = 0;
            \Log::info(__METHOD__ . ' Error ===> ' . json_encode($message));
        }


        if($results['valid_transaction'] != 1) {
            //$error_messages[] = $results['message'];
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function captureOrder($requestData){

        $error_messages = [];
        $results = [];

        $customer_id            =   $this->jwtauth->customerIdFromToken();
        $artist_id              =   (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? trim($requestData['artist_id']) : '';
        $platform               =   (isset($requestData['platform']) && $requestData['platform'] != '') ? strtolower(trim($requestData['platform'])) : '';
        $vendor                 =   (isset($requestData['vendor']) && $requestData['vendor'] != '') ? trim($requestData['vendor']) : '';
        $transaction_price      =   (isset($requestData['transaction_price']) && $requestData['platform'] != '') ? float_value($requestData['transaction_price']) : float_value(0);
        $package_id             =   (isset($requestData['package_id']) && $requestData['package_id'] != '') ? trim($requestData['package_id']) : '';
        $currency_code          =   (isset($requestData['currency_code']) && $requestData['currency_code'] != '') ? trim($requestData['currency_code']) : '';

        $vendor_order_id        =   (isset($requestData['vendor_order_id']) && $requestData['vendor_order_id'] != '') ? trim($requestData['vendor_order_id']) : '';
        $purchase_payload       =   (isset($requestData['purchase_payload']) && $requestData['purchase_payload'] != '') ? $requestData['purchase_payload'] : '';
        $package_sku            =   (isset($requestData['package_sku']) && $requestData['package_sku'] != '') ? trim($requestData['package_sku']) : '';
        $product_name           =   (isset($requestData['product_name']) && $requestData['product_name'] != '') ? trim($requestData['product_name']) : '';
        $valid_transaction      =   (isset($requestData['purchase_payload']) && isset($requestData['purchase_payload']['valid_transaction']) && $requestData['purchase_payload']['valid_transaction'] != '') ? intval($requestData['purchase_payload']['valid_transaction']) : 0;

        $receipt                =   (isset($requestData['receipt']) && $requestData['receipt'] != '') ? trim($requestData['receipt']) : '';
        $purchase_key           =   (isset($requestData['purchase_key']) && $requestData['purchase_key'] != '') ? trim($requestData['purchase_key']) : '';
        $failed_payload         =   (!empty($requestData['failed_payload'])) ? $requestData['failed_payload'] : [];
        $pending_retry         =   (!empty($requestData['pending_retry']) && $requestData['pending_retry'] == '1') ? '1' : '';
        $parent_order_id        =   '';
        $referral_domain        =   (!empty($requestData['referral_domain']) && $requestData['referral_domain']) ? trim($requestData['referral_domain']) : '';

        if ($vendor == 'apple_wallet') {
            $purchase_key       = $receipt;
        }

        if (in_array($vendor, array('paytm', 'razorpay', 'paypal'))) {
            $purchase_key       = $vendor_order_id;
        }

        $orderData = [
            'customer_id' => $customer_id,
            'artist_id' => $artist_id,
            'platform' => $platform,
            'vendor' => $vendor,
            'transaction_price' => $transaction_price,
            'currency_code' => $currency_code,
            'vendor_order_id' => $vendor_order_id,
            'package_sku' => $package_sku,
            'product_name' => $product_name,
            'purchase_key' => $purchase_key
        ];

        if($pending_retry != ''){
            $orderData['pending_retry'] = $pending_retry;
        }

        if($referral_domain != ''){
            $orderData['referral_domain'] = $referral_domain;
        }

        if ($valid_transaction == 1) {
            $orderAlreadyExist = \App\Models\Order::where('vendor', $vendor)->where('purchase_key', $purchase_key)->first();
            if ($orderAlreadyExist) {
                $error_messages[] = 'Order already exists';
                return ['error_messages' => $error_messages, 'results' => $results];
            }

            //CHECK VENDOR ORDER ID UNIQUNESS SINCE GOOGLE PURCHASE TOKEN OR APPLE RECIPT CAN BE UNIQUE BUT VENDOR ORDER ID MIGHT ALREADY EXIST
            $vendorOrderIdAlreadyExist = \App\Models\Order::where('vendor', $vendor)->where('vendor_order_id', trim($vendor_order_id))->first();
            if($vendorOrderIdAlreadyExist){
                $parent_order_id        = (isset($vendorOrderIdAlreadyExist['_id'])) ? trim($vendorOrderIdAlreadyExist['_id']) : 'NOT_FIND';
                $selected_packageSku    = (isset($vendorOrderIdAlreadyExist['sku'])) ? ($vendorOrderIdAlreadyExist['sku']) : 0;
                $selected_packagePrice  = (isset($vendorOrderIdAlreadyExist['price'])) ? float_value($vendorOrderIdAlreadyExist['price']) : 0;
                $selected_packageCoins  = (isset($vendorOrderIdAlreadyExist['coins'])) ? ($vendorOrderIdAlreadyExist['coins']) : 0;
                $selected_packageXp     = (isset($vendorOrderIdAlreadyExist['xp'])) ? ($vendorOrderIdAlreadyExist['xp']) : 0;
            }
        }


        if($valid_transaction == 1){
            $order_status = ($parent_order_id == '') ? 'successful' : 'duplicate';
        }else{
            $order_status = 'failed';
        }

        $selected_package       =   $this->packageRep->find($package_id);
        $selected_packageSku    =   (!empty($selected_package) && isset($selected_package['sku'])) ? ($selected_package['sku']) : 0;
        $selected_packagePrice  =   (!empty($selected_package) && isset($selected_package['price'])) ? float_value($selected_package['price']) : 0;
        $selected_packageCoins  =   (!empty($selected_package) && isset($selected_package['coins'])) ? ($selected_package['coins']) : 0;
        $selected_packageXp     =   (!empty($selected_package) && isset($selected_package['xp'])) ? ($selected_package['xp']) : 0;

        if(!empty($failed_payload)){
            array_set($orderData, 'failed_payload', $failed_payload);
        }

        if ($parent_order_id != '') {
            array_set($orderData, 'parent_order_id', $parent_order_id);
        }

        if (empty($selected_package)) {
            $error_messages[] = 'Wrong Package';
        }




        $customerObj = \App\Models\Customer::where('_id', $customer_id)->first();
        if($order_status == 'successful'){
            $coins_before_purchase = (isset($customerObj->coins)) ? $customerObj->coins : 0;
            $coins_after_purchase = (isset($customerObj->coins)) ? $customerObj->coins + $selected_packageCoins : $selected_packageCoins;
        }else{
            $coins_before_purchase = (isset($customerObj->coins)) ? $customerObj->coins : 0;
            $coins_after_purchase = $coins_before_purchase;
        }


        array_set($orderData, 'package_id', $package_id);
        array_set($orderData, 'package_sku', $selected_packageSku);
        array_set($orderData, 'package_price', $selected_packagePrice);
        array_set($orderData, 'package_coins', $selected_packageCoins);
        array_set($orderData, 'package_xp', $selected_packageXp);
        array_set($orderData, 'order_status', $order_status);
        array_set($orderData, 'coins_before_purchase', $coins_before_purchase);
        array_set($orderData, 'coins_after_purchase', $coins_after_purchase);
        array_set($orderData, 'purchase_payload', $purchase_payload);
        array_set($orderData, 'artist_id', $artist_id);
        array_set($orderData, 'passbook_applied', true);

        //print_pretty($orderData);exit;


        if (empty($error_messages)) {
            $results['order']               =   $this->orderRep->store($orderData);
            $results['valid_transaction']   =   $valid_transaction;
            $results['available_coins']     =   $coins_after_purchase;

            if ($order_status == 'successful') {

                //UPDATE CUSTOMER CONIS ON SUCCESSFUL ORDER
                $customerObj = $this->customerRep->coinsDeposit($customer_id, $selected_packageCoins);

                //UPDATE CUSTOMER XP ON SUCCESSFUL ORDER
                $customerXpObj = $this->customerRep->xpDeposit($customer_id, $artist_id, $selected_packageXp);

                //UPDATE CUSTOMER CONIS ON CACHE/REDIS SUCCESSFUL ORDER
                $this->redisdb->saveCustomerCoins($customer_id, $coins_after_purchase);


                //########### PURGE CACHE
                $purge_result = $this->awsElasticCacheRedis->purgeCustomerPurchasePackagesListsCache(['customer_id' => $customer_id]);


                //########### EMAIL PROCCES ON SUCCESSFUL ORDER SART

                $non_emailer_artist = ['598aa3d2af21a2355d686de2'];

                if(!in_array($artist_id, $non_emailer_artist)){

                    $order_id   = $results['order']['_id'];
                    $order_info = \App\Models\Order::with('package', 'customer', 'artist')->where('_id', $order_id)->first();
                    $order_info = $order_info ? $order_info->toArray() : [];

                    /*
                    // Old Code
                    $celeb_direct_app_download_link = '';
                    $celeb_ios_app_download_link = '';
                    $celeb_android_app_download_link = '';
                    $celeb_recharge_wallet_link = '';

                    if($artist_id != ''){
                        $artist_config_info = \App\Models\Artistconfig::where('artist_id', $artist_id)->first();
                        $celeb_android_app_download_link = ($artist_config_info && !empty($artist_config_info['android_app_download_link'])) ? trim($artist_config_info['android_app_download_link']) : '';
                        $celeb_ios_app_download_link = ($artist_config_info && !empty($artist_config_info['ios_app_download_link'])) ? trim($artist_config_info['ios_app_download_link']) : '';
                        $celeb_direct_app_download_link = ($artist_config_info && !empty($artist_config_info['direct_app_download_link'])) ? trim($artist_config_info['direct_app_download_link']) : '';

                        $artistname                 =  strtolower(@$order_info['artist']['first_name']).''.strtolower(@$order_info['artist']['last_name']);
                        $celeb_recharge_wallet_link = "https://recharge.bollyfame.com/wallet-recharge/$artistname/$artist_id";
                    }

                    $celebname          =   Ucfirst(@$order_info['artist']['first_name']) . ' ' . Ucfirst(@$order_info['artist']['last_name']);
                    $customer_name      =   Ucfirst(@$order_info['customer']['first_name']) . ' ' . Ucfirst(@$order_info['customer']['last_name']);
                    $subject_line       =   "Your " . Config::get('product.' . env('PRODUCT') . '.app_name') . " order id $order_id for $celebname has been completed";

                    $payload = Array(
                        'celeb_name' => $celebname,
                        'celeb_photo' => @$order_info['artist']['photo'],

                        'celeb_android_app_download_link' => $celeb_android_app_download_link,
                        'celeb_ios_app_download_link' => $celeb_ios_app_download_link,
                        'celeb_direct_app_download_link' => $celeb_direct_app_download_link,
                        'celeb_recharge_wallet_link' => $celeb_recharge_wallet_link,

                        'customer_email' => $order_info['customer']['email'],
                        'customer_coins' => @$coins_after_purchase,
                        'customer_name' => $customer_name,

                        'package_name' => $order_info['package']['name'],

                        'transaction_id' => $order_info['vendor_order_id'],
                        'currency_code' => $order_info['currency_code'],
                        'vendor' => $order_info['vendor'],
                        'transaction_price' => $order_info['transaction_price'],
                        'transaction_date' => Carbon::parse($order_info['created_at'])->format('M j\\, Y h:i A'),

                        'email_header_template' => 'emails.' . env('PRODUCT') .'.common.header',
                        'email_body_template' => 'emails.' . env('PRODUCT') .'.customer.order',
                        'email_footer_template' => 'emails.' . env('PRODUCT') .'.common.footer',
                        'email_subject' => $subject_line,
                        'bcc_emailids' => Config::get('mail.bcc_email_ids')
                    );
                    */

                    // New Code to send email

                    $celebname      = '';
                    $customer_name  = generate_fullname($order_info['customer']);
                    $customer_email = strtolower(trim($order_info['customer']['email']));

                    $payload = [];
                    // Get Email Default Template Data
                    if($artist_id) {
                        $payload = $this->artistService->getEmailTemplateDefaultData($artist_id);
                        if($payload) {
                            $celebname = isset($payload['celeb_name']) ? $payload['celeb_name'] : '';
                        }
                    }

                    $subject_line   = "Your BOLLYFAME order id $order_id for $celebname has been completed";
                    if(env('PRODUCT') && (trim(strtolower(env('PRODUCT'))) == 'hsw')) {
                        $subject_line   = "Your BollyFame order id $order_id has been completed";
                    }

                    // Generate Email Template specific data
                    //$payload['']  = '';
                    $payload['customer_email']      = $customer_email;
                    $payload['customer_name']       = $customer_name;
                    $payload['customer_coins']      = @$coins_after_purchase;

                    $payload['package_name']        = $order_info['package']['name'];
                    $payload['transaction_id']      = $order_info['vendor_order_id'];
                    $payload['currency_code']       = $order_info['currency_code'];
                    $payload['vendor']              = $order_info['vendor'];
                    $payload['transaction_price']   = $order_info['transaction_price'];
                    $payload['transaction_date']    = Carbon::parse($order_info['created_at'])->format('M j\\, Y h:i A');

                    $payload['email_header_template']   = 'emails.hotshot.common.header';
                    $payload['email_body_template']     = 'emails.hotshot.customer.customerorder';
                    $payload['email_footer_template']   = 'emails.hotshot.common.footer';
                    $payload['email_subject']           =  $subject_line;
                    $payload['user_email']              =  $customer_email;
                    $payload['user_name']               =  $customer_name;
                    $payload['bcc_emailids']            =  Config::get('product.' . env('PRODUCT') . '.mail.bcc_for_transaction');
                    $payload['send_from']               =  Config::get('product.' . env('PRODUCT') . '.mail.from_for_transaction');

                    $jobData = [
                        'label' => 'CustomerOrderConfirm',
                        'type' => 'process_email',
                        'payload' => $payload,
                        'status' => "scheduled",
                        'delay' => 0,
                        'retries' => 0
                    ];

                    $recodset = new \App\Models\Job($jobData);
                    $recodset->save();

                }

                //########### EMAIL PROCCES FOR SUCCESSFUL ORDER SART

            }

        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }





    public function passbookCaptureOrder($requestData){
        \Log::info(__METHOD__ . ' START ', []);

        $error_messages = [];
        $results = [];

        $customer_id            =   $this->jwtauth->customerIdFromToken();
        $artist_id              =   (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? trim($requestData['artist_id']) : '';
        $platform               =   (isset($requestData['platform']) && $requestData['platform'] != '') ? strtolower(trim($requestData['platform'])) : '';
        $platform_version       =   (!empty($requestData['v'])) ? strtolower(trim($requestData['v'])) : '';
        $vendor                 =   (isset($requestData['vendor']) && $requestData['vendor'] != '') ? trim($requestData['vendor']) : '';
        $transaction_price      =   (isset($requestData['transaction_price']) && $requestData['platform'] != '') ? float_value($requestData['transaction_price']) : float_value(0);
        $package_id             =   (isset($requestData['package_id']) && $requestData['package_id'] != '') ? trim($requestData['package_id']) : '';
        $selected_package       =   (!empty($requestData['selected_package'])) ? $requestData['selected_package'] : [];
        $currency_code          =   (isset($requestData['currency_code']) && $requestData['currency_code'] != '') ? trim($requestData['currency_code']) : '';

        $vendor_order_id        =   (isset($requestData['vendor_order_id']) && $requestData['vendor_order_id'] != '') ? trim($requestData['vendor_order_id']) : '';
        $purchase_payload       =   (isset($requestData['purchase_payload']) && $requestData['purchase_payload'] != '') ? $requestData['purchase_payload'] : '';
        $package_sku            =   (isset($requestData['package_sku']) && $requestData['package_sku'] != '') ? trim($requestData['package_sku']) : '';
        $product_name           =   (isset($requestData['product_name']) && $requestData['product_name'] != '') ? trim($requestData['product_name']) : '';
        $valid_transaction      =   (isset($requestData['purchase_payload']) && isset($requestData['purchase_payload']['valid_transaction']) && $requestData['purchase_payload']['valid_transaction'] != '') ? intval($requestData['purchase_payload']['valid_transaction']) : 0;

        $service_account        =   (isset($requestData['ser_acc']) && $requestData['ser_acc'] != '') ? trim($requestData['ser_acc']) : '';
        $receipt                =   (isset($requestData['receipt']) && $requestData['receipt'] != '') ? trim($requestData['receipt']) : '';
        $purchase_key           =   (isset($requestData['purchase_key']) && $requestData['purchase_key'] != '') ? trim($requestData['purchase_key']) : '';
        $failed_payload         =   (!empty($requestData['failed_payload'])) ? $requestData['failed_payload'] : [];
        $pending_retry         =   (!empty($requestData['pending_retry']) && $requestData['pending_retry'] == '1') ? '1' : '';
        $parent_order_id        =   '';
        $referral_domain        =   (!empty($requestData['referral_domain']) && $requestData['referral_domain']) ? trim($requestData['referral_domain']) : '';

        if ($vendor == 'apple_wallet') {
            $purchase_key       = $receipt;
        }

        if ($vendor == 'paytm') {
            $purchase_key       = $vendor_order_id;
        }

        $orderData = [
            'customer_id' => $customer_id,
            'artist_id' => $artist_id,
            'platform' => $platform,
            'platform_version' => $platform_version,
            'vendor' => $vendor,
            'transaction_price' => $transaction_price,
            'currency_code' => $currency_code,
            'vendor_order_id' => $vendor_order_id,
            'package_sku' => $package_sku,
            'product_name' => $product_name,
            'purchase_key' => $purchase_key
        ];

        if($pending_retry != ''){
            $orderData['pending_retry'] = $pending_retry;
        }

        if($referral_domain != '') {
            $orderData['referral_domain'] = $referral_domain;
        }

        if ($valid_transaction == 1) {
            $orderAlreadyExist = \App\Models\Passbook::where("txn_meta_info.vendor", $vendor)->where("txn_meta_info.vendor_txn_token", $purchase_key)->first();
            if ($orderAlreadyExist) {
                $error_messages[] = 'Order already exists';
                return ['error_messages' => $error_messages, 'results' => $results];
            }

            //CHECK VENDOR ORDER ID UNIQUNESS SINCE GOOGLE PURCHASE TOKEN OR APPLE RECIPT CAN BE UNIQUE BUT VENDOR ORDER ID MIGHT ALREADY EXIST
            $vendorOrderIdAlreadyExist = \App\Models\Passbook::where("txn_meta_info.vendor", $vendor)->where("txn_meta_info.vendor_txn_id", trim($vendor_order_id))->first();
            if($vendorOrderIdAlreadyExist){
                $parent_order_id        = (isset($vendorOrderIdAlreadyExist['_id'])) ? trim($vendorOrderIdAlreadyExist['_id']) : 'NOT_FIND';
            }
        }

        if($valid_transaction == 1){
            $order_status = ($parent_order_id == '') ? 'success' : 'duplicate';
        }else{
            $order_status = 'failed';
        }

        if(!empty($selected_package) && empty($selected_package['_id'])){
            $selected_package       =   $selected_package;
        }else{
            $selected_package       =   $this->packageRep->find($package_id);
        }

        $selected_packageSku    =   (!empty($selected_package) && isset($selected_package['sku'])) ? ($selected_package['sku']) : 0;
        $selected_packagePrice  =   (!empty($selected_package) && isset($selected_package['price'])) ? float_value($selected_package['price']) : 0;
        $selected_packageCoins  =   (!empty($selected_package) && isset($selected_package['coins'])) ? ($selected_package['coins']) : 0;
        $selected_packageXp     =   (!empty($selected_package) && isset($selected_package['xp'])) ? ($selected_package['xp']) : 0;

        if(!empty($failed_payload)){
            array_set($orderData, 'failed_payload', $failed_payload);
        }

        if ($parent_order_id != '') {
            array_set($orderData, 'parent_order_id', $parent_order_id);
        }

        if (empty($selected_package)) {
            $error_messages[] = 'Wrong Package';
        }

        $customerObj = \App\Models\Customer::where('_id', $customer_id)->first();
        if($order_status == 'success'){
            $coins_before_purchase = (isset($customerObj->coins)) ? $customerObj->coins : 0;
            $coins_after_purchase = (isset($customerObj->coins)) ? $customerObj->coins + $selected_packageCoins : $selected_packageCoins;
        }else{
            $coins_before_purchase = (isset($customerObj->coins)) ? $customerObj->coins : 0;
            $coins_after_purchase = $coins_before_purchase;
        }

        if(!empty($service_account)){
            array_set($orderData, 'service_account', $service_account);
        }

        array_set($orderData, 'package_id', $package_id);
        array_set($orderData, 'package_sku', $selected_packageSku);
        array_set($orderData, 'package_price', $selected_packagePrice);
        array_set($orderData, 'package_coins', $selected_packageCoins);
        array_set($orderData, 'package_xp', $selected_packageXp);
        array_set($orderData, 'order_status', $order_status);
        array_set($orderData, 'coins_before_purchase', $coins_before_purchase);
        array_set($orderData, 'coins_after_purchase', $coins_after_purchase);
        array_set($orderData, 'purchase_payload', $purchase_payload);
        array_set($orderData, 'artist_id', $artist_id);
        array_set($orderData, 'reference_id', 'NOT_EXIST');
        array_set($orderData, 'passbook_applied', true);



//        print_pretty($orderData);exit;


        if (empty($error_messages)) {
            $passbookSaveData               =   $this->passbookService->convertOrderDataToOrderPassbookData($orderData);
//            print_pretty($passbookSaveData);exit;
            $savePassbookResult             =   $this->passbookService->saveToPassbook($passbookSaveData);
            $purchase_package               =   (!empty($savePassbookResult) && !empty($savePassbookResult['results']) && !empty($savePassbookResult['results']['passbook'])) ? $savePassbookResult['results']['passbook'] : [];
            $results['order']               =   $purchase_package;
            $results['valid_transaction']   =   $valid_transaction;
            $results['available_coins']     =   $coins_after_purchase;

            if ($order_status == 'success') {

                //UPDATE CUSTOMER CONIS ON SUCCESSFUL ORDER
                $customerObj = $this->customerRep->coinsDeposit($customer_id, $selected_packageCoins);

                //UPDATE CUSTOMER XP ON SUCCESSFUL ORDER
                $customerXpObj = $this->customerRep->xpDeposit($customer_id, $artist_id, $selected_packageXp);

                //UPDATE CUSTOMER CONIS ON CACHE/REDIS SUCCESSFUL ORDER
                $this->redisdb->saveCustomerCoins($customer_id, $coins_after_purchase);


                //########### PURGE CACHE
                $purge_result = $this->awsElasticCacheRedis->purgeCustomerPassbookListsCache(['customer_id' => $customer_id]);


                $non_emailer_artist = ['598aa3d2af21a2355d686de2'];
                //########### EMAIL PROCCES ON SUCCESSFUL ORDER SART
                if(!empty($purchase_package) && !empty($purchase_package['_id']) && !in_array($artist_id, $non_emailer_artist)){


                        $order_id = $purchase_package['_id'];
                        $order_info = \App\Models\Passbook::with('package', 'customer', 'artist')->where('_id', $order_id)->first();
                        $order_info = $order_info ? $order_info->toArray() : [];

/*
                        // Old Code For sending email

                        $celeb_direct_app_download_link = '';
                        $celeb_ios_app_download_link = '';
                        $celeb_android_app_download_link = '';
                        $celeb_recharge_wallet_link = '';

                        if ($artist_id != '') {
                            $artist_config_info = \App\Models\Artistconfig::where('artist_id', $artist_id)->first();
                            $celeb_android_app_download_link = ($artist_config_info && !empty($artist_config_info['android_app_download_link'])) ? trim($artist_config_info['android_app_download_link']) : '';
                            $celeb_ios_app_download_link = ($artist_config_info && !empty($artist_config_info['ios_app_download_link'])) ? trim($artist_config_info['ios_app_download_link']) : '';
                            $celeb_direct_app_download_link = ($artist_config_info && !empty($artist_config_info['direct_app_download_link'])) ? trim($artist_config_info['direct_app_download_link']) : '';

                            $artistname = strtolower(@$order_info['artist']['first_name']) . '' . strtolower(@$order_info['artist']['last_name']);
                            $celeb_recharge_wallet_link = "https://recharge.bollyfame.com/wallet-recharge/$artistname/$artist_id";
                        }

                        $celebname = Ucfirst(@$order_info['artist']['first_name']) . ' ' . Ucfirst(@$order_info['artist']['last_name']);
                        $customer_name = Ucfirst(@$order_info['customer']['first_name']) . ' ' . Ucfirst(@$order_info['customer']['last_name']);
                        $customer_email = strtolower(trim($order_info['customer']['email']));
                        $subject_line = "Your BOLLYFAME order id $order_id for $celebname has been completed";


                        $payload = Array(
                            'celeb_name' => $celebname,
                            'celeb_photo' => @$order_info['artist']['photo'],

                            'celeb_android_app_download_link' => $celeb_android_app_download_link,
                            'celeb_ios_app_download_link' => $celeb_ios_app_download_link,
                            'celeb_direct_app_download_link' => $celeb_direct_app_download_link,
                            'celeb_recharge_wallet_link' => $celeb_recharge_wallet_link,

                            'customer_email' => $order_info['customer']['email'],
                            'customer_coins' => @$coins_after_purchase,
                            'customer_name' => $customer_name,

                            'package_name' => $order_info['package']['name'],

                            'transaction_id' => $order_info['txn_meta_info']['vendor_txn_id'],
                            'currency_code' => $order_info['txn_meta_info']['currency_code'],
                            'vendor' => $order_info['txn_meta_info']['vendor'],
                            'transaction_price' => $order_info['txn_meta_info']['transaction_price'],
                            'transaction_date' => Carbon::parse($order_info['created_at'])->format('M j\\, Y h:i A'),

                            'email_header_template' => 'emails.common.header',
                            'email_body_template' => 'emails.customer.customerorder',
                            'email_footer_template' => 'emails.common.footer',
                            'email_subject' => $subject_line,
                            'user_email' => $customer_email,
                            'user_name' => $customer_name,
                            'bcc_emailids' => Config::get('mail.bcc_email_ids')

                        );
*/

                        // New Code to send email

                        $celebname      = '';
                        $customer_name  = generate_fullname($order_info['customer']);
                        $customer_email = strtolower(trim($order_info['customer']['email']));

                        $payload = [];
                        // Get Email Default Template Data
                        if($artist_id) {
                            $payload = $this->artistService->getEmailTemplateDefaultData($artist_id);
                            if($payload) {
                                $celebname = isset($payload['celeb_name']) ? $payload['celeb_name'] : '';
                            }
                        }

                        $subject_line   = "Your BOLLYFAME order id $order_id for $celebname has been completed";
                        if(env('PRODUCT') && (trim(strtolower(env('PRODUCT'))) == 'hsw')) {
                            $subject_line   = "Your BollyFame order id $order_id has been completed";
                        }

                        // Generate Email Template specific data
                        //$payload['']  = '';
                        $payload['customer_email']      = $customer_email;
                        $payload['customer_name']       = $customer_name;
                        $payload['customer_coins']      = @$coins_after_purchase;

                        $payload['package_name']        = $order_info['package']['name'];
                        $payload['transaction_id']      = $order_info['txn_meta_info']['vendor_txn_id'];
                        $payload['currency_code']       = $order_info['txn_meta_info']['currency_code'];
                        $payload['vendor']              = $order_info['txn_meta_info']['vendor'];
                        $payload['transaction_price']   = $order_info['txn_meta_info']['transaction_price'];
                        $payload['transaction_date']    = Carbon::parse($order_info['created_at'])->format('M j\\, Y h:i A');

                        $payload['email_header_template']   = 'emails.' . env('PRODUCT') . '.common.header';
                        $payload['email_body_template']     = 'emails.' . env('PRODUCT') . '.customer.customerorder';
                        $payload['email_footer_template']   = 'emails.' . env('PRODUCT') . '.common.footer';
                        $payload['email_subject']           =  $subject_line;
                        $payload['user_email']              =  $customer_email;
                        $payload['user_name']               =  $customer_name;
                        $payload['bcc_emailids']            =  Config::get('product.' . env('PRODUCT') . '.mail.bcc_for_transaction');
                        $payload['send_from']               =  Config::get('product.' . env('PRODUCT') . '.mail.from_for_transaction');

                        $jobData = [
                            'label'     => 'CustomerOrderConfirm',
                            'type'      => 'process_email',
                            'payload'   => $payload,
                            'status'    => "scheduled",
                            'delay'     => 0,
                            'retries'   => 0
                        ];

                        \Log::info(__METHOD__ . ' $jobData:', $jobData);

                        $recodset = new \App\Models\Job($jobData);
                        $recodset->save();

                }
                //########### EMAIL PROCCES FOR SUCCESSFUL ORDER SART

            }

        }

        \Log::info(__METHOD__ . ' ::: END  $error_messages:', $error_messages);
        \Log::info(__METHOD__ . ' ::: END  $results:', $results);
        \Log::info(PHP_EOL);

        return ['error_messages' => $error_messages, 'results' => $results];

    }





}
