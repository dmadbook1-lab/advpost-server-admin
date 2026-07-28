<?php
// ===============================
// Razorpay Webhook Handler
// ===============================

// ⚠️ Webhook secret (Dashboard se milega)
$webhookSecret = "YOUR_WEBHOOK_SECRET_HERE";

// Raw POST body
$payload = file_get_contents("php://input");

// Razorpay signature header
$razorpaySignature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

// Generate expected signature
$expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);

// Signature verify
if (!hash_equals($expectedSignature, $razorpaySignature)) {
    http_response_code(400);
    echo "Invalid signature";
    exit;
}

// Decode payload
$data = json_decode($payload, true);

// Event type
$event = $data['event'] ?? '';

/*
|--------------------------------------------------------------------------
| Handle Events
|--------------------------------------------------------------------------
*/
if ($event === 'payment.captured') {

    $payment = $data['payload']['payment']['entity'];

    $payment_id   = $payment['id'];
    $order_id     = $payment['order_id'];
    $amount       = $payment['amount'] / 100; // paise → rupees
    $currency     = $payment['currency'];
    $status       = $payment['status'];
    $method       = $payment['method'];
    $email        = $payment['email'];
    $contact      = $payment['contact'];
    $created_at   = date('Y-m-d H:i:s', $payment['created_at']);

    // ===============================
    // 🔴 DATABASE SAVE HERE
    // ===============================
    /*
    Example:
    INSERT INTO payments
    (payment_id, order_id, amount, status, method, email, contact, created_at)
    VALUES (...)
    */

    // Temporary log (debug)
    file_put_contents(
        __DIR__ . "/webhook_log.txt",
        "CAPTURED | $payment_id | ₹$amount | $created_at" . PHP_EOL,
        FILE_APPEND
    );

}

// Success response (MANDATORY)
http_response_code(200);
echo "Webhook handled successfully";
