<?php

namespace App\Services\Payment;

/**
 * Razorpay class.
 * Razorpay is extended from Razorpay\Api\Api
 *
 * @author Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since 2019-04-11
 * @link http://bollyfame.com/
 * @copyright 2019 BOLLYFAME Media Pvt. Ltd
 * @license http://bollyfame.com/license/
 */

// Razorpay Library
include(app_path() . '/Services/Payment/Razorpay/Razorpay.php');

use Razorpay\Api\Api as RazorpayApi;

class Razorpay extends RazorpayApi
{

}