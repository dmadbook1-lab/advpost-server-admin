<?php
require 'vendor/autoload.php';
require 'config.php';
require 'db.php';

use Razorpay\Api\Api;

$id = $_GET['id'];

$q = $db->prepare("SELECT amount FROM orders WHERE id = ?");
$q->execute([$id]);
$order = $q->fetch();

$amount = $order['amount'] * 100; // paisa

$api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

$razorpayOrder = $api->order->create([
    'receipt' => 'order_'.$id,
    'amount' => $amount,
    'currency' => 'INR'
]);

// Save razorpay order id
$upd = $db->prepare("UPDATE orders SET razorpay_order_id=? WHERE id=?");
$upd->execute([$razorpayOrder['id'], $id]);

echo json_encode([
    "key" => RAZORPAY_KEY_ID,
    "amount" => $amount,
    "razorpay_order_id" => $razorpayOrder['id']
]);
