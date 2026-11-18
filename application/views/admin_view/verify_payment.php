<?php
require 'vendor/autoload.php';
require 'config.php';
require 'db.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

$data = json_decode(file_get_contents("php://input"), true);

$api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

try {
    $api->utility->verifyPaymentSignature([
        'razorpay_order_id' => $data['razorpay_order_id'],
        'razorpay_payment_id' => $data['razorpay_payment_id'],
        'razorpay_signature' => $data['razorpay_signature']
    ]);

    // ✅ Payment Success
    $q = $db->prepare("
        UPDATE orders 
        SET payment_status='paid', razorpay_payment_id=? 
        WHERE id=?
    ");
    $q->execute([
        $data['razorpay_payment_id'],
        $data['order_id']
    ]);

    echo json_encode([
        "status" => "success",
        "message" => "Payment Successful"
    ]);

} catch (SignatureVerificationError $e) {
    echo json_encode([
        "status" => "failed",
        "message" => "Payment Verification Failed"
    ]);
}
