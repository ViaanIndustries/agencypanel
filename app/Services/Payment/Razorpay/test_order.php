<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
/**
 * Test - Razorpay Payment Gateway
 *
 * @package  Test
 * @author   Shekhar <chandrashekhar.thalkar@bollyfame.com>
 */

require_once("./Razorpay.php");

use Razorpay\Api\Api;

echo "<pre>";
echo 'Test - Razorpay Payment Gateway' . "<br />";

$api_key 	= 'rzp_test_p4cvQnSrWnsfWo';
$api_secret	= 'far2XoRiAoeu6V2AhXFSbUYN';

$time 		= time();
$receipt 	= 'Test_receipt_' . $time;
echo '$receipt :' . $receipt . "<br />";
$amount 	= rand(100, 500);
echo '$amount :' . $amount . "<br />";
$order_id 	= '';
$order_data = array(
	'receipt' => $receipt,
	'amount' => $amount,
	'currency' => 'INR',
	'payment_capture' => 1,
	'notes' => array('artist_id' => $time, 'artist_name' => 'Artist Two Hundred'),
);
print_r($order_data);


try {
	$api = new Api($api_key, $api_secret);

	echo 'Creates order' . "<br />";
	// Orders
	$order  = $api->order->create($order_data); // Creates order
	if($order) {
		//var_dump($order);
		$order_id = $order['id'];
		echo '$order_id :' . $order_id . "<br />";
	}
	else {
		echo 'Creates order failed.' . "<br />";
	}
}
catch (Exception $e) {
    throw new Exception($e->getMessage());
}



exit('END TESTING...');