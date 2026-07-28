<?php
require_once 'config.php';

// Validate amount
$amount = isset($_GET['amount']) ? (int)$_GET['amount'] : 0;

if ($amount <= 0) {
    die('Invalid amount');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Wallet Recharge</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>

<body onload="payNow()" style="background:#f5f6fa;">

<script>
function payNow() {

    var options = {
        key: "<?php echo rzp_live_SHDZuqRAFAYkXg; ?>",
        amount: <?php echo $amount * 100; ?>, // rupees → paise (NUMBER)
        currency: "INR",
        name: "AdvPost Wallet",
        description: "Wallet Recharge",

      "handler": function (response){

    var paymentId = response.razorpay_payment_id;

    // Try opening the app
    window.location.href =
    "advpost://payment-success-wallet?payment_id=" + paymentId;

    // Fallback if app not installed
    setTimeout(function () {
        document.body.innerHTML = `
            <h2>Payment Successful 🎉</h2>
            <p>Your payment ID: <b>${paymentId}</b></p>
            <p>You can now return to the app.</p>
        `;
    }, 3000);
},

        modal: {
            ondismiss: function () {
                alert("Payment cancelled");
            }
        },

        theme: {
            color: "#6C5CE7"
        }
    };

    var rzp = new Razorpay(options);
    rzp.open();
}
</script>

</body>
</html>
