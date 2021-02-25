<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width">
<title>Test - Razorpay Payment Gateway - Order & Payment</title>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>
<body>
    <h1>Test - Razorpay Payment Gateway - Order & Payment</h1>
<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
require_once("./Razorpay.php");

use Razorpay\Api\Api;

$api_key    = 'rzp_test_p4cvQnSrWnsfWo';
$api_secret = 'far2XoRiAoeu6V2AhXFSbUYN';
$time       = time();
$receipt    = 'Receipt #' . $time;
$amount     = 100;
$currency   = 'INR';

$order_data = array(
    'receipt' => $receipt,
    'amount' => $amount,
    'currency' => $currency,
    'payment_capture' => 1,
    'notes' => array('artist_id' => $time, 'artist_name' => 'Artist'.$time),
);

?>
    <h3>Order Details</h3>
    <fieldset>
        <legend>Order</legend>
        Receipt : <?php echo $receipt;?><br />
        Amount : <?php echo $amount;?><br />
        Currency : <?php echo $currency;?><br />
    </fieldset>

    <h3>Create Order</h3>
<?php

    $api = new Api($api_key, $api_secret);
    // Create Order
    $order  = $api->order->create($order_data); // Creates order
    if($order) {
        $order_id = $order['id'];
        echo '$order_id :' . $order_id . "<br />";
    }
    else {
        echo 'Creates order failed.' . "<br />";
    }

    // Make Payment
?>
    <h3>Make Order Payment</h3>
</body>
<script>
var options = {
    "key": "<?php echo $api_key;?>",
    "amount": "<?php echo $amount;?>",
    "name": "Merchant Name",
    "order_id": "<?php echo $order_id;?>",
    "currency": "<?php echo $currency;?>",
    "handler": function (response){
        alert(response.razorpay_payment_id);
    },
};
console.log(options);

var rzp1 = new Razorpay(options);
rzp1.open();
</script>

</html>