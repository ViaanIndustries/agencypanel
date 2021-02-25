<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width">
<title>Test - Razorpay Payment Gateway - Payment</title>
</head>

<body>
<form action="/test_payment_status.php" method="POST">
<!-- Note that the amount is in its subunit value = 599 -->
<script
    src="https://checkout.razorpay.com/v1/checkout.js"
    data-key="rzp_test_p4cvQnSrWnsfWo"
    data-amount="164" // The amount is shown in currency subunits. Actual amount is ₹599.
    data-order_id="order_CHYegRc3j6V3Xf" // Pass the order ID if you are using Razorpay Orders.
    data-currency="INR" // Optional. Same as Order currency
    data-buttontext="Pay with Razorpay"
    data-name="BOLLYFAME Pvt. Ltd."
    data-description="Purchase Description"
    data-image="http://bollyfame.com/images/logo.png"
    data-theme.color="#F37254"
></script>
<input type="hidden" value="Hidden Element" name="hidden">
</form>
</body>

</html>