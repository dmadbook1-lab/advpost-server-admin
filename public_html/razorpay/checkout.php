<?php
$amount  = $_GET['amount'] ?? 0;
$post_id = $_GET['post_id'] ?? 0;

if ($amount <= 0 || $post_id <= 0) {
    http_response_code(400);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Redirecting…</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>
<body>

<script>
(async function () {
    try {
        const res = await fetch(
            "create_order.php?amount=<?= $amount ?>&post_id=<?= $post_id ?>"
        );
        const data = await res.json();

        if (!data.order_id) return;

        const options = {
            key: "rzp_test_SHtjbZaBfQbQDw",
            amount: data.amount,
            currency: "INR",
            name: "Vihaanshika",
            description: "Post Boost",
            order_id: data.order_id,

            handler: function (response) {
                const paymentId = response.razorpay_payment_id;

                // ONLY deep link – nothing else
                window.location.replace(
                    "advpost://payment-success?payment_id=" + paymentId
                );
            },

            modal: {
                ondismiss: function () {}
            }
        };

        new Razorpay(options).open();
    } catch (e) {}
})();
</script>

</body>
</html>