<?php
require('config.php');

$data = json_decode(file_get_contents("php://input"), true);

$generated_signature = hash_hmac(
    "sha256",
    $data['razorpay_order_id']."|".$data['razorpay_payment_id'],
    RAZORPAY_KEY_SECRET
);

if ($generated_signature === $data['razorpay_signature']) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "failed"]);
}
?>